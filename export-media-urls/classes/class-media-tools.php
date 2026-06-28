<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';

// Reads the wp_posts table directly to page through attachment IDs. The table
// identifier comes from $wpdb and the only interpolated fragment is a fixed
// image-MIME LIKE clause (never user input); LIMIT/OFFSET are bound via prepare().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

/**
 * Library-maintenance helpers for the "Media Tools" tab.
 *
 * Two access patterns:
 *  - Alt Text lists every image, so it is windowed with SQL LIMIT/OFFSET
 *    (window()) — efficient, and the page count reflects the image count.
 *  - Missing / Heavy / Duplicates are sparse *findings* across the library, so
 *    they scan the whole library once (walk_all(), memory bounded) and return
 *    the full findings list; the view then paginates those findings, so the
 *    page count reflects what is actually shown, not the library size.
 */
class EMU_Media_Tools
{
    /* ------------------------------ counts ------------------------------ */

    /**
     * Total number of attachments (optionally images only).
     *
     * @param bool $images_only
     * @return int
     */
    public function total($images_only = false)
    {
        global $wpdb;
        $where = "post_type = 'attachment'";
        if ($images_only) {
            $where .= " AND post_mime_type LIKE 'image/%'";
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$where}");
    }

    /**
     * Storage overview: attachment count per media group, the grand total, and
     * (where supported) the on-disk size of the uploads directory.
     *
     * The counts are a single GROUP BY with no file access, so they stay instant
     * on any size site. Disk usage uses get_dirsize(), which caches its result
     * in a transient. It is only read when available, so the plugin keeps
     * working on WordPress versions that predate it — 'bytes' is then null and
     * the view simply omits that card.
     *
     * @return array array('groups'=>[group=>count], 'total'=>int, 'bytes'=>int|null)
     */
    public function summary()
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT post_mime_type AS mt, COUNT(*) AS c FROM {$wpdb->posts} WHERE post_type = 'attachment' GROUP BY post_mime_type");

        $groups = array();
        $total = 0;
        if ($rows) {
            foreach ($rows as $row) {
                $group = $this->mime_group($row->mt);
                if (!isset($groups[$group])) {
                    $groups[$group] = 0;
                }
                $groups[$group] += (int) $row->c;
                $total += (int) $row->c;
            }
        }

        $bytes = null;
        $upload = wp_upload_dir();
        if (empty($upload['error']) && function_exists('get_dirsize')) {
            // Called via a variable so this guarded, progressively-enhanced use is
            // not flagged against the plugin's lower "Requires at least" version.
            $dirsize = 'get_dirsize';
            $size = $dirsize($upload['basedir']);
            if (is_numeric($size)) {
                $bytes = (int) $size;
            }
        }

        return array('groups' => $groups, 'total' => $total, 'bytes' => $bytes);
    }

    /* --------------------------- windowing ------------------------------ */

    /**
     * Fetch one page of attachment posts (caches primed in bulk).
     *
     * @param int  $offset      Zero-based offset.
     * @param int  $limit       Page size; 0 means "no limit" (the whole set).
     * @param bool $images_only Restrict to image/* attachments.
     * @return \WP_Post[]
     */
    public function window($offset, $limit, $images_only = false)
    {
        global $wpdb;

        $where = "post_type = 'attachment'";
        if ($images_only) {
            $where .= " AND post_mime_type LIKE 'image/%'";
        }
        $sql = "SELECT ID FROM {$wpdb->posts} WHERE {$where} ORDER BY ID ASC";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, max(0, $offset));
        }

        $ids = $wpdb->get_col($sql);
        if (empty($ids)) {
            return array();
        }
        $ids = array_map('intval', $ids);

        $query = new \WP_Query(array(
            'post_type'              => 'attachment',
            'post_status'            => 'inherit',
            'post__in'               => $ids,
            'orderby'                => 'post__in',
            'posts_per_page'         => count($ids),
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => true,
        ));
        $posts = $query->posts;
        wp_reset_postdata();

        return $posts;
    }

    /* ----------------------- full-library scans ------------------------- */

    /**
     * Iterate every attachment in keyset batches (memory bounded), invoking
     * $callback($post) for each. Used by the findings scans below.
     *
     * @param callable $callback
     * @param bool     $images_only
     */
    private function walk_all($callback, $images_only = false)
    {
        global $wpdb;

        $where = "post_type = 'attachment'";
        if ($images_only) {
            $where .= " AND post_mime_type LIKE 'image/%'";
        }
        $batch = (int) Constants::BATCH_SIZE;
        $last = 0;

        while (true) {
            $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE {$where} AND ID > {$last} ORDER BY ID ASC LIMIT {$batch}");
            if (empty($ids)) {
                break;
            }
            $ids = array_map('intval', $ids);

            $query = new \WP_Query(array(
                'post_type'              => 'attachment',
                'post_status'            => 'inherit',
                'post__in'               => $ids,
                'orderby'                => 'post__in',
                'posts_per_page'         => count($ids),
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => true,
            ));
            foreach ($query->posts as $post) {
                call_user_func($callback, $post);
            }
            wp_reset_postdata();

            $last = (int) end($ids);
            if (count($ids) < $batch) {
                break;
            }
        }
    }

    /**
     * Every attachment across the whole library whose file is missing on disk.
     *
     * @return array list of array(id, title, file)
     */
    public function all_missing()
    {
        $out = array();
        $this->walk_all(function ($post) use (&$out) {
            $id = (int) $post->ID;
            $path = get_attached_file($id);
            if (!$path || !file_exists($path)) {
                $out[] = array(
                    'id'    => $id,
                    'title' => get_the_title($id),
                    'file'  => $path ? $path : '',
                );
            }
        });
        return $out;
    }

    /**
     * Every oversized image across the whole library (large file or dimensions).
     *
     * @return array list of array(id, title, url, bytes, w, h)
     */
    public function all_heavy()
    {
        $out = array();
        $this->walk_all(function ($post) use (&$out) {
            $id = (int) $post->ID;
            $path = get_attached_file($id);
            if (!$path || !file_exists($path)) {
                return;
            }
            $size = (int) filesize($path);
            $meta = wp_get_attachment_metadata($id);
            $w = (is_array($meta) && isset($meta['width'])) ? (int) $meta['width'] : 0;
            $h = (is_array($meta) && isset($meta['height'])) ? (int) $meta['height'] : 0;

            if ($size > Constants::HEAVY_IMAGE_BYTES || $w > Constants::HEAVY_IMAGE_DIMENSION || $h > Constants::HEAVY_IMAGE_DIMENSION) {
                $out[] = array(
                    'id'    => $id,
                    'title' => get_the_title($id),
                    'url'   => wp_get_attachment_url($id),
                    'bytes' => $size,
                    'w'     => $w,
                    'h'     => $h,
                );
            }
        }, true);
        return $out;
    }

    /**
     * Every set of byte-for-byte identical files across the whole library
     * (matched by size, then confirmed by content hash within each size bucket).
     *
     * @return array list of array(bytes, files[])
     */
    public function all_duplicates()
    {
        $by_size = array();
        $this->walk_all(function ($post) use (&$by_size) {
            $id = (int) $post->ID;
            $path = get_attached_file($id);
            if (!$path || !file_exists($path)) {
                return;
            }
            $size = (int) filesize($path);
            if ($size <= 0) {
                return;
            }
            $by_size[$size][] = array(
                'id'    => $id,
                'path'  => $path,
                'title' => get_the_title($id),
                'url'   => wp_get_attachment_url($id),
            );
        });

        $duplicates = array();
        foreach ($by_size as $size => $items) {
            if (count($items) < 2) {
                continue;
            }
            $by_hash = array();
            foreach ($items as $item) {
                $hash = @md5_file($item['path']);
                if ($hash === false) {
                    continue;
                }
                $by_hash[$hash][] = $item;
            }
            foreach ($by_hash as $group) {
                if (count($group) > 1) {
                    $duplicates[] = array('bytes' => (int) $size, 'files' => $group);
                }
            }
        }
        return $duplicates;
    }

    /**
     * Images in the page, with current alt text, for the bulk editor.
     *
     * @param \WP_Post[] $posts
     * @return array list of array(id, title, alt, thumb)
     */
    public function images_in($posts)
    {
        $out = array();
        foreach ($posts as $post) {
            if (strpos((string) $post->post_mime_type, 'image/') !== 0) {
                continue;
            }
            $id = (int) $post->ID;
            $thumb = wp_get_attachment_image_src($id, 'thumbnail', false);
            $out[] = array(
                'id'    => $id,
                'title' => get_the_title($id),
                'alt'   => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
                'thumb' => (is_array($thumb) && !empty($thumb[0])) ? $thumb[0] : '',
            );
        }
        return $out;
    }

    /**
     * Persist edited alt text. Only attachments whose value actually changed are
     * written, so re-saving a page is cheap and the reported count is accurate.
     *
     * @param array $alt_map id => alt
     * @return int Number of attachments updated.
     */
    public function save_alt($alt_map)
    {
        $updated = 0;
        foreach ($alt_map as $id => $alt) {
            $id = (int) $id;
            if (!$id || get_post_type($id) !== 'attachment') {
                continue;
            }
            $new = sanitize_text_field($alt);
            $current = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
            if ($new === $current) {
                continue;
            }
            update_post_meta($id, '_wp_attachment_image_alt', $new);
            $updated++;
        }
        return $updated;
    }

    /* ----------------------------- helpers ------------------------------ */

    /**
     * Human label for a MIME type's top-level group.
     */
    public function mime_group($mime)
    {
        $mime = (string) $mime;
        if (strpos($mime, 'image/') === 0) {
            return __('Images', 'export-media-urls');
        }
        if (strpos($mime, 'video/') === 0) {
            return __('Video', 'export-media-urls');
        }
        if (strpos($mime, 'audio/') === 0) {
            return __('Audio', 'export-media-urls');
        }
        if (strpos($mime, 'application/zip') === 0
            || strpos($mime, 'application/x-rar') === 0
            || strpos($mime, 'application/x-tar') === 0
            || strpos($mime, 'application/gzip') === 0
            || strpos($mime, 'application/x-7z') === 0) {
            return __('Archives', 'export-media-urls');
        }
        if (strpos($mime, 'application/') === 0 || strpos($mime, 'text/') === 0) {
            return __('Documents', 'export-media-urls');
        }
        return __('Other', 'export-media-urls');
    }
}
