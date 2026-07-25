<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';

// Reads wp_posts / wp_postmeta directly to build a usage index in a single
// pass. Table identifiers come from $wpdb; the only interpolated values are a
// fixed meta-key string and post-type placeholders bound through prepare().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Builds a "where used" index: for each attachment, the set of posts that
 * actually reference it. This is the dynamic, content-derived counterpart to
 * the static "Parent Post" column (which only records where a file was first
 * uploaded, not where it is used).
 *
 * The index is built by walking every content-bearing post ONCE and recording
 * the attachments each one references — O(posts), not O(attachments x posts).
 * The export loop then does plain array lookups against the finished index.
 *
 * References are detected from:
 *  - the featured image (_thumbnail_id post meta)
 *  - editor image classes ("wp-image-123", emitted by both the classic and
 *    block editors)
 *  - block attributes ("id":123 and "ids":[1,2,3] inside Gutenberg blocks)
 *  - the [gallery ids="1,2,3"] shortcode
 *  - raw upload URLs pasted into content (resolved back to an ID via the
 *    attached-file map, after stripping any -WxH size suffix)
 *  - anything a third party contributes through the 'emu_usage_extra_ids'
 *    filter (the extension point for page builders, ACF, etc.)
 *
 * IMPORTANT: this is deliberately a best-effort report, not an authority.
 * References buried in serialized page-builder blobs, custom fields, widgets,
 * theme options or CSS are not parsed here unless a filter surfaces them, so an
 * empty "Used In" result means "not found in post content", NOT "safe to
 * delete". Callers must treat "Unused" as a hint, never as permission to purge.
 */
class EMU_Usage_Index
{
    /** @var array attachment_id => array(post_id => true) */
    private $index = array();

    /** @var array attachment_id => true (every real attachment; the guard set) */
    private $att_set = array();

    /** @var array relative uploads path => attachment_id (from _wp_attached_file) */
    private $file_to_id = array();

    /** @var array post_id => attachment_id (from _thumbnail_id) */
    private $thumbs = array();

    /** @var string Path component of the uploads base URL, e.g. /wp-content/uploads */
    private $uploads_path = '';

    /** @var bool */
    private $built = false;

    /**
     * Build the index. Safe to call more than once; only the first call works.
     */
    public function build()
    {
        if ($this->built) {
            return;
        }
        $this->built = true;

        $this->prime_attachments();
        $this->prime_thumbnails();
        $this->prime_uploads_path();
        $this->walk_posts();
    }

    /**
     * Post IDs that reference the given attachment, ascending. Empty when the
     * attachment was not found in any scanned post's content.
     *
     * @param int $attachment_id
     * @return int[]
     */
    public function used_in($attachment_id)
    {
        $attachment_id = (int) $attachment_id;
        if (empty($this->index[$attachment_id])) {
            return array();
        }
        $ids = array_keys($this->index[$attachment_id]);
        sort($ids);
        return $ids;
    }

    /* ------------------------- priming (one query each) ------------------- */

    /**
     * The guard set of real attachment IDs, and the relative-path -> ID map used
     * to resolve raw upload URLs back to attachments.
     */
    private function prime_attachments()
    {
        global $wpdb;

        $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'");
        foreach ($ids as $id) {
            $this->att_set[(int) $id] = true;
        }

        $rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'");
        if ($rows) {
            foreach ($rows as $row) {
                $path = ltrim((string) $row->meta_value, '/');
                if ($path !== '') {
                    $this->file_to_id[$path] = (int) $row->post_id;
                }
            }
        }
    }

    /**
     * post_id -> featured-image attachment ID, for every post that has one.
     */
    private function prime_thumbnails()
    {
        global $wpdb;

        $rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'");
        if ($rows) {
            foreach ($rows as $row) {
                $tid = (int) $row->meta_value;
                if ($tid > 0) {
                    $this->thumbs[(int) $row->post_id] = $tid;
                }
            }
        }
    }

    /**
     * Cache the uploads-directory path so URL matching is domain- and
     * scheme-agnostic (handles http/https and same-path CDNs).
     */
    private function prime_uploads_path()
    {
        $upload = wp_upload_dir();
        if (!empty($upload['baseurl'])) {
            // parse_url() (PHP 5.x) is used directly so the plugin keeps working
            // on WordPress 3.6 (wp_parse_url() is 4.4+).
            // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- intentional for WordPress 3.6 compatibility.
            $path = parse_url($upload['baseurl'], PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $this->uploads_path = rtrim($path, '/');
            }
        }
    }

    /* --------------------------- the single pass ------------------------- */

    /**
     * Walk every content-bearing post in keyset batches (memory bounded) and
     * record the attachments each one references.
     */
    private function walk_posts()
    {
        global $wpdb;

        $types = $this->post_types();
        if (empty($types)) {
            return;
        }

        // Give third-party extensions a real WP_Post only when one is listening,
        // so the common case pays nothing for the hook.
        $has_extra = has_filter('emu_usage_extra_ids');

        $placeholders = implode(', ', array_fill(0, count($types), '%s'));
        $sql = "SELECT ID, post_content FROM {$wpdb->posts}
                WHERE post_type IN ($placeholders)
                  AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
                  AND ID > %d
                ORDER BY ID ASC
                LIMIT %d";

        $batch = (int) Constants::BATCH_SIZE;
        $last = 0;

        while (true) {
            $args = $types;
            $args[] = $last;
            $args[] = $batch;
            // call_user_func_array keeps prepare() happy on old WordPress, where
            // it expected variadic args rather than a single array.
            $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $args));
            $rows = $wpdb->get_results($prepared);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $this->index_post((int) $row->ID, (string) $row->post_content, $has_extra);
                $last = (int) $row->ID;
            }

            if (count($rows) < $batch) {
                break;
            }
        }
    }

    /**
     * Record every attachment referenced by a single post.
     *
     * @param int    $post_id
     * @param string $content
     * @param bool   $has_extra Whether the emu_usage_extra_ids filter has listeners.
     */
    private function index_post($post_id, $content, $has_extra)
    {
        $found = array();

        // Featured image.
        if (isset($this->thumbs[$post_id]) && isset($this->att_set[$this->thumbs[$post_id]])) {
            $found[$this->thumbs[$post_id]] = true;
        }

        // IDs mentioned in the content. Guarded against the attachment set so a
        // stray "id":123 that points at a post/block/menu item is ignored.
        foreach ($this->extract_ids($content) as $aid) {
            if (isset($this->att_set[$aid])) {
                $found[$aid] = true;
            }
        }

        // Raw upload URLs (already resolved to real attachment IDs).
        foreach ($this->extract_url_ids($content) as $aid) {
            $found[$aid] = true;
        }

        // Third-party contributions (page builders, ACF, custom fields, ...).
        if ($has_extra) {
            $extra = apply_filters('emu_usage_extra_ids', array(), get_post($post_id));
            if (is_array($extra)) {
                foreach ($extra as $aid) {
                    $aid = (int) $aid;
                    if ($aid > 0) {
                        $found[$aid] = true;
                    }
                }
            }
        }

        foreach ($found as $aid => $unused) {
            $this->index[$aid][$post_id] = true;
        }
    }

    /* ---------------------------- extraction ----------------------------- */

    /**
     * Attachment IDs referenced by class name, block attribute or gallery
     * shortcode. NOT yet filtered against the attachment set (the caller does
     * that) — a numeric match here is only a candidate.
     *
     * @param string $content
     * @return int[]
     */
    private function extract_ids($content)
    {
        $ids = array();

        // Editor image class: <img class="... wp-image-123">.
        if (preg_match_all('/wp-image-(\d+)/', $content, $m)) {
            foreach ($m[1] as $id) {
                $ids[] = (int) $id;
            }
        }

        // Block single id: {"id":123,...}.
        if (preg_match_all('/"id"\s*:\s*(\d+)/', $content, $m)) {
            foreach ($m[1] as $id) {
                $ids[] = (int) $id;
            }
        }

        // Block id list and gallery shortcode: "ids":[1,2,3] / [gallery ids="1,2"].
        if (preg_match_all('/"ids"\s*:\s*\[([0-9,\s]+)\]/', $content, $m)) {
            foreach ($m[1] as $list) {
                foreach ($this->split_ids($list) as $id) {
                    $ids[] = $id;
                }
            }
        }
        if (preg_match_all('/\[gallery[^\]]*\bids\s*=\s*["\']([0-9,\s]+)["\']/', $content, $m)) {
            foreach ($m[1] as $list) {
                foreach ($this->split_ids($list) as $id) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /**
     * Attachment IDs resolved from raw upload URLs in the content. These come
     * straight from the attached-file map, so they are already known real
     * attachments and need no further guarding.
     *
     * @param string $content
     * @return int[]
     */
    private function extract_url_ids($content)
    {
        $out = array();
        if ($this->uploads_path === '' || strpos($content, $this->uploads_path . '/') === false) {
            return $out;
        }

        $pattern = '#' . preg_quote($this->uploads_path, '#') . '/([^\s"\'<>()\\\\]+\.[A-Za-z0-9]+)#';
        if (!preg_match_all($pattern, $content, $m)) {
            return $out;
        }

        foreach ($m[1] as $rel) {
            $rel = ltrim($rel, '/');
            if (isset($this->file_to_id[$rel])) {
                $out[] = $this->file_to_id[$rel];
                continue;
            }
            // Content usually references a generated size (image-800x600.jpg);
            // fall back to the original by stripping the -WxH suffix.
            $original = preg_replace('/-\d+x\d+(?=\.[A-Za-z0-9]+$)/', '', $rel);
            if ($original !== $rel && isset($this->file_to_id[$original])) {
                $out[] = $this->file_to_id[$original];
            }
        }

        return $out;
    }

    /* ----------------------------- helpers ------------------------------- */

    /**
     * Split a comma/space separated id list into positive ints.
     *
     * @param string $list
     * @return int[]
     */
    private function split_ids($list)
    {
        $out = array();
        foreach (preg_split('/[,\s]+/', trim($list)) as $piece) {
            if ($piece !== '') {
                $id = (int) $piece;
                if ($id > 0) {
                    $out[] = $id;
                }
            }
        }
        return $out;
    }

    /**
     * The post types worth scanning for media references: every registered type
     * except attachments themselves and the internal/structural types that never
     * hold user content. Custom post types are included automatically.
     *
     * @return string[]
     */
    private function post_types()
    {
        $types = get_post_types(array(), 'names');
        $exclude = array(
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_global_styles',
            'wp_navigation',
            'wp_template',
            'wp_template_part',
            'wp_font_family',
            'wp_font_face',
        );

        $types = array_values(array_diff($types, $exclude));

        /**
         * Filter the post types scanned when building the "where used" index.
         *
         * @param string[] $types Post-type names to scan.
         */
        $types = apply_filters('emu_usage_post_types', $types);

        return array_values(array_filter(array_map('strval', (array) $types)));
    }
}
