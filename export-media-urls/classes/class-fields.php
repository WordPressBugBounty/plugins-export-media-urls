<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';
require_once plugin_dir_path(__FILE__) . 'class-text.php';

/**
 * Single source of truth for export columns.
 *
 * Every field is declared once with its label, UI group and a value callback.
 * The export loop and the header row both iterate the *same* selected-key list,
 * so columns and headers can never drift out of alignment.
 *
 * Original field keys (id, title, file_name, file_size, caption, alt,
 * description, url, date, type) are preserved; new keys are appended.
 */
class EMU_Fields
{
    /**
     * UI groups, in display order.
     *
     * @return array group_key => array(label, collapsed)
     */
    public function groups()
    {
        return array(
            'core'      => array('label' => __('Core', 'export-media-urls'),       'collapsed' => false),
            'file'      => array('label' => __('File', 'export-media-urls'),       'collapsed' => true),
            'meta'      => array('label' => __('Meta', 'export-media-urls'),       'collapsed' => true),
            'relations' => array('label' => __('Relations', 'export-media-urls'),  'collapsed' => true),
            'dates'     => array('label' => __('Dates', 'export-media-urls'),      'collapsed' => true),
        );
    }

    /**
     * Field presets surfaced as quick-select buttons in the UI.
     *
     * @return array preset_key => array(label, fields[])
     */
    public function presets()
    {
        return array(
            'migration' => array('label' => __('Migration', 'export-media-urls'),          'fields' => array('file_name', 'file_size', 'type', 'url')),
            'audit'     => array('label' => __('SEO / Accessibility', 'export-media-urls'), 'fields' => array('url', 'title', 'alt', 'alt_missing')),
            'where_used' => array('label' => __('Where Used', 'export-media-urls'),         'fields' => array('url', 'title', 'used_in_count', 'used_in_titles', 'is_unused')),
            'cleanup'   => array('label' => __('Cleanup', 'export-media-urls'),             'fields' => array('url', 'file_size', 'used_in_count', 'is_unused')),
            'full'      => array('label' => __('Full', 'export-media-urls'),                'fields' => array_keys($this->fields())),
            'none'      => array('label' => __('None', 'export-media-urls'),                'fields' => array()),
        );
    }

    /**
     * The ordered field registry.
     *
     * 'text' => true routes the field through EMU_Text normalisation. File names
     * and URLs are deliberately unmarked: their characters must survive as
     * stored, though ensure_utf8() still applies to every field.
     *
     * 'html_decode' => true preserves the pre-3.2 htmlspecialchars_decode()
     * behaviour when full entity decoding is switched off.
     *
     * @return array key => array(label, group, cb, text, html_decode)
     */
    public function fields()
    {
        return array(
            // --- Core ---
            'id'          => array('label' => __('ID', 'export-media-urls'),            'group' => 'core',      'cb' => 'f_id'),
            'title'       => array('label' => __('Title', 'export-media-urls'),         'group' => 'core',      'cb' => 'f_title',      'text' => true, 'html_decode' => true),
            'file_name'   => array('label' => __('File Name', 'export-media-urls'),     'group' => 'core',      'cb' => 'f_file_name'),
            'url'         => array('label' => __('URL', 'export-media-urls'),           'group' => 'core',      'cb' => 'f_url'),

            // --- File ---
            'file_size'   => array('label' => __('File Size', 'export-media-urls'),     'group' => 'file',      'cb' => 'f_file_size'),
            'type'        => array('label' => __('MIME Type', 'export-media-urls'),     'group' => 'file',      'cb' => 'f_type'),
            'dimensions'  => array('label' => __('Dimensions', 'export-media-urls'),    'group' => 'file',      'cb' => 'f_dimensions'),
            'url_thumbnail' => array('label' => __('Thumbnail URL', 'export-media-urls'), 'group' => 'file',    'cb' => 'f_url_thumbnail'),
            'url_medium'  => array('label' => __('Medium URL', 'export-media-urls'),    'group' => 'file',      'cb' => 'f_url_medium'),
            'url_large'   => array('label' => __('Large URL', 'export-media-urls'),     'group' => 'file',      'cb' => 'f_url_large'),

            // --- Meta ---
            'caption'     => array('label' => __('Caption', 'export-media-urls'),       'group' => 'meta',      'cb' => 'f_caption',     'text' => true, 'html_decode' => true),
            'alt'         => array('label' => __('Alt Text', 'export-media-urls'),      'group' => 'meta',      'cb' => 'f_alt',         'text' => true),
            'alt_missing' => array('label' => __('Alt Missing', 'export-media-urls'),   'group' => 'meta',      'cb' => 'f_alt_missing'),
            'description' => array('label' => __('Description', 'export-media-urls'),   'group' => 'meta',      'cb' => 'f_description', 'text' => true),

            // --- Relations ---
            'parent'        => array('label' => __('Uploaded To (Parent)', 'export-media-urls'), 'group' => 'relations', 'cb' => 'f_parent', 'text' => true, 'html_decode' => true),
            'parent_url'    => array('label' => __('Uploaded To URL', 'export-media-urls'),      'group' => 'relations', 'cb' => 'f_parent_url'),
            'used_in_count' => array('label' => __('Used In (count)', 'export-media-urls'),      'group' => 'relations', 'cb' => 'f_used_in_count'),
            'used_in_titles' => array('label' => __('Used In (posts)', 'export-media-urls'),     'group' => 'relations', 'cb' => 'f_used_in_titles', 'text' => true, 'html_decode' => true),
            'used_in_urls'  => array('label' => __('Used In (URLs)', 'export-media-urls'),       'group' => 'relations', 'cb' => 'f_used_in_urls'),
            'used_in_id'    => array('label' => __('Used In (post ID)', 'export-media-urls'),    'group' => 'relations', 'cb' => 'f_used_in_id'),
            'used_in_type'  => array('label' => __('Used In (post type)', 'export-media-urls'),  'group' => 'relations', 'cb' => 'f_used_in_type'),
            'is_unused'     => array('label' => __('Unused', 'export-media-urls'),               'group' => 'relations', 'cb' => 'f_is_unused'),

            // --- Dates ---
            'date'        => array('label' => __('Date Uploaded', 'export-media-urls'), 'group' => 'dates',     'cb' => 'f_date'),
        );
    }

    /**
     * Labels for the selected fields, in the order given.
     *
     * @param array $selected_fields Selected field keys.
     * @param bool  $hash            Prepend a '#' counter column (HTML table).
     * @return array
     */
    public function labels_for($selected_fields, $hash = false)
    {
        $all = $this->fields();
        $labels = $hash ? array('#') : array();

        foreach ($selected_fields as $key) {
            if (isset($all[$key])) {
                $labels[] = $all[$key]['label'];
            }
        }

        return $labels;
    }

    /**
     * Resolve a single field's value for an attachment.
     *
     * @param string $key     Field key.
     * @param int    $post_id Attachment ID (loop is active, globals are set).
     * @param array  $options Sanitized request options.
     * @param array  $context Per-item precomputed data.
     * @return string
     */
    public function value($key, $post_id, $options, $context)
    {
        $all = $this->fields();
        if (!isset($all[$key])) {
            return '';
        }

        $def = $all[$key];
        $value = (string) call_user_func(array($this, $def['cb']), $post_id, $options, $context);

        // Applying both would decode twice, turning "&amp;lt;b&amp;gt;" into live markup.
        if (!empty($def['html_decode']) && empty($options['text_entities'])) {
            $value = htmlspecialchars_decode($value);
        }

        // Prose is normalised; identifiers, URLs and file names keep their
        // characters, but every field is still made well-formed UTF-8.
        if (!empty($def['text'])) {
            return EMU_Text::normalize($value, $options);
        }

        return EMU_Text::ensure_utf8($value);
    }

    /**
     * Whether any selected field needs the wp_get_attachment_metadata() blob
     * (dimensions and the sized-image URLs all read from it).
     *
     * @param array $selected_fields
     * @return bool
     */
    public function needs_meta($selected_fields)
    {
        foreach (array('dimensions', 'url_thumbnail', 'url_medium', 'url_large') as $field) {
            if (in_array($field, $selected_fields, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The field keys that trigger the (expensive) where-used content scan.
     *
     * @return string[]
     */
    public function usage_field_keys()
    {
        return array('used_in_count', 'used_in_titles', 'used_in_urls', 'used_in_id', 'used_in_type', 'is_unused');
    }

    /**
     * Whether any selected field needs the (expensive) where-used index built.
     * When false the export never scans post content, so nothing changes for
     * users who don't ask for usage columns.
     *
     * @param array $selected_fields
     * @return bool
     */
    public function needs_usage($selected_fields)
    {
        foreach ($this->usage_field_keys() as $field) {
            if (in_array($field, $selected_fields, true)) {
                return true;
            }
        }
        return false;
    }

    /* --------------------------------------------------------------------- */
    /* Value callbacks. Signature: ($post_id, $options, $context) => string. */
    /* --------------------------------------------------------------------- */

    public function f_id($id, $o, $c)
    {
        return (string) $id;
    }

    public function f_title($id, $o, $c)
    {
        return get_the_title($id);
    }

    public function f_file_name($id, $o, $c)
    {
        $path = get_attached_file($id);
        return $path ? wp_basename($path) : '';
    }

    public function f_url($id, $o, $c)
    {
        $url = wp_get_attachment_url($id);
        return $url ? esc_url($url) : '';
    }

    public function f_file_size($id, $o, $c)
    {
        $path = get_attached_file($id);
        if (!$path || !file_exists($path)) {
            return '';
        }
        return size_format(filesize($path));
    }

    public function f_type($id, $o, $c)
    {
        return get_post_mime_type($id);
    }

    public function f_dimensions($id, $o, $c)
    {
        if (!empty($c['meta']) && isset($c['meta']['width'], $c['meta']['height'])) {
            return (int) $c['meta']['width'] . ' x ' . (int) $c['meta']['height'];
        }
        return '';
    }

    public function f_url_thumbnail($id, $o, $c)
    {
        return $this->sized_url($id, 'thumbnail');
    }

    public function f_url_medium($id, $o, $c)
    {
        return $this->sized_url($id, 'medium');
    }

    public function f_url_large($id, $o, $c)
    {
        return $this->sized_url($id, 'large');
    }

    public function f_caption($id, $o, $c)
    {
        // The caption is stored as the attachment's post_excerpt; read it
        // directly so the plugin keeps working on WordPress 3.6
        // (wp_get_attachment_caption() is 4.6+).
        $post = get_post($id);
        return $post ? $post->post_excerpt : '';
    }

    public function f_alt($id, $o, $c)
    {
        return (string) get_post_meta($id, '_wp_attachment_image_alt', true);
    }

    /**
     * "Yes" when this is an image with no alt text (accessibility/SEO flag).
     * Empty for non-images (alt text is not applicable) and for images that
     * already have alt text.
     */
    public function f_alt_missing($id, $o, $c)
    {
        if (strpos((string) get_post_mime_type($id), 'image/') !== 0) {
            return '';
        }
        $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
        return ('' === trim((string) $alt)) ? __('Yes', 'export-media-urls') : '';
    }

    public function f_description($id, $o, $c)
    {
        $post = get_post($id);
        return $post ? $post->post_content : '';
    }

    public function f_parent($id, $o, $c)
    {
        $parent = (int) wp_get_post_parent_id($id);
        if (!$parent) {
            return '';
        }
        return get_the_title($parent);
    }

    public function f_parent_url($id, $o, $c)
    {
        $parent = (int) wp_get_post_parent_id($id);
        if (!$parent) {
            return '';
        }
        $url = get_permalink($parent);
        return $url ? esc_url($url) : '';
    }

    /**
     * The posts that actually reference this attachment (featured image, editor
     * content, galleries, or a third-party filter). This is the *dynamic*
     * where-used relationship, unlike f_parent()'s static upload origin. See
     * EMU_Usage_Index for what is and is not detected.
     */
    public function f_used_in_count($id, $o, $c)
    {
        // Always the item total, so it still reads as 3 on each of 3 expanded rows.
        return (string) count($this->used_in($c));
    }

    public function f_used_in_titles($id, $o, $c)
    {
        $titles = array();
        foreach ($this->row_posts($c) as $pid) {
            $titles[] = get_the_title($pid);
        }
        return implode(' | ', $titles);
    }

    public function f_used_in_urls($id, $o, $c)
    {
        $urls = array();
        foreach ($this->row_posts($c) as $pid) {
            $url = get_permalink($pid);
            if ($url) {
                $urls[] = esc_url($url);
            }
        }
        return implode(' | ', $urls);
    }

    public function f_used_in_id($id, $o, $c)
    {
        return implode(' | ', array_map('strval', $this->row_posts($c)));
    }

    public function f_used_in_type($id, $o, $c)
    {
        $types = array();
        foreach ($this->row_posts($c) as $pid) {
            $type = get_post_type($pid);
            if ($type && !in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        return implode(' | ', $types);
    }

    /**
     * "Yes" when no scanned post references this attachment. A hint for cleanup,
     * NOT proof the file is safe to delete — references in page builders, custom
     * fields, widgets or CSS are not scanned. Empty when a usage was found.
     */
    public function f_is_unused($id, $o, $c)
    {
        return count($this->used_in($c)) === 0 ? __('Yes', 'export-media-urls') : '';
    }

    public function f_date($id, $o, $c)
    {
        return get_the_date('Y-m-d H:i:s', $id);
    }

    /* ----------------------------- helpers ------------------------------- */

    /**
     * Post IDs referencing the current attachment, taken from the precomputed
     * context. Empty when the where-used index was not built for this run.
     *
     * @param array $c Per-item context.
     * @return int[]
     */
    private function used_in($c)
    {
        return (isset($c['used_in']) && is_array($c['used_in'])) ? $c['used_in'] : array();
    }

    /**
     * The referencing posts this row is about: every one by default, or the
     * single post the orchestrator picked when expanding.
     *
     * @param array $c Per-item context.
     * @return int[]
     */
    private function row_posts($c)
    {
        if (isset($c['usage_row']) && $c['usage_row'] !== null) {
            return array((int) $c['usage_row']);
        }
        return $this->used_in($c);
    }

    /**
     * URL of a registered image size, or '' when the attachment has no such size
     * (e.g. non-images, or images smaller than the size).
     */
    private function sized_url($id, $size)
    {
        $src = wp_get_attachment_image_src($id, $size, false);
        if (is_array($src) && !empty($src[0])) {
            return esc_url($src[0]);
        }
        return '';
    }
}
