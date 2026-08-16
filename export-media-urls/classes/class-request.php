<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';

/**
 * Reads and sanitizes the export form submission into a plain options array,
 * and validates it. Used by both request paths (the in-page "Display Here"
 * handler and the admin-post.php streaming handler) so sanitization lives in
 * exactly one place.
 */
class EMU_Request
{
    /**
     * Build a sanitized options array from a nonce-verified $_POST.
     *
     * @return array|null Options array, or null when the nonce is missing/invalid.
     */
    public static function from_post()
    {
        if (
            !isset($_POST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), Constants::EXPORT_NONCE_ACTION)
        ) {
            return null;
        }

        $export_type   = isset($_POST['export-type']) ? sanitize_text_field(wp_unslash($_POST['export-type'])) : '';
        $export_fields = isset($_POST['export_fields'])
            ? array_values(array_map('sanitize_text_field', (array) wp_unslash($_POST['export_fields'])))
            : array();

        $media_type    = isset($_POST['media-type']) ? sanitize_text_field(wp_unslash($_POST['media-type'])) : 'all';
        $attachment    = isset($_POST['attachment-status']) ? sanitize_text_field(wp_unslash($_POST['attachment-status'])) : 'all';
        $post_author   = isset($_POST['post-author']) ? sanitize_text_field(wp_unslash($_POST['post-author'])) : 'all';

        $date_range    = isset($_POST['date-range']) ? sanitize_text_field(wp_unslash($_POST['date-range'])) : 'all';
        $start_date    = isset($_POST['start-date']) ? sanitize_text_field(wp_unslash($_POST['start-date'])) : '';
        $end_date      = isset($_POST['end-date']) ? sanitize_text_field(wp_unslash($_POST['end-date'])) : '';

        $number        = isset($_POST['number-of-items']) ? sanitize_text_field(wp_unslash($_POST['number-of-items'])) : 'all';
        if ($number === 'range') {
            $offset = isset($_POST['starting-point']) ? absint(wp_unslash($_POST['starting-point'])) : 0;
            $ending = isset($_POST['ending-point']) ? absint(wp_unslash($_POST['ending-point'])) : 0;
            $post_per_page = max(0, $ending - $offset);
        } else {
            $offset = 'all';
            $post_per_page = 'all';
        }

        $csv_name = isset($_POST['csv-file-name']) ? sanitize_file_name(wp_unslash($_POST['csv-file-name'])) : '';

        // Unticked checkboxes are absent from $_POST, so only a real submission
        // can distinguish "off" from "not set".
        $submitted = isset($_POST['form_submitted']);

        $text_repair   = self::checkbox('text-repair', $submitted, true);
        $text_entities = self::checkbox('text-entities', $submitted, true);
        $text_ascii    = self::checkbox('text-ascii', $submitted, false);
        $csv_flatten   = self::checkbox('csv-flatten', $submitted, true);

        $csv_delimiter = isset($_POST['csv-delimiter']) ? sanitize_key(wp_unslash($_POST['csv-delimiter'])) : 'comma';
        if (!in_array($csv_delimiter, array('comma', 'semicolon', 'tab'), true)) {
            $csv_delimiter = 'comma';
        }

        $usage_expand = self::checkbox('usage-expand', $submitted, false);
        $usage_deep   = self::checkbox('usage-deep', $submitted, false);
        $usage_only   = self::checkbox('usage-only', $submitted, false);

        return array(
            'export_type'       => $export_type,
            'export_fields'     => $export_fields,
            'media_type'        => $media_type,
            'attachment_status' => $attachment,
            'post_author'       => $post_author,
            'date_range'        => $date_range,
            'start_date'        => $start_date,
            'end_date'          => $end_date,
            'number'            => $number,
            'offset'            => $offset,
            'post_per_page'     => $post_per_page,
            'csv_name'          => $csv_name,

            /* Text handling. */
            'text_repair'       => $text_repair,
            'text_entities'     => $text_entities,
            'text_ascii'        => $text_ascii,

            /* CSV shape. */
            'csv_delimiter'     => $csv_delimiter,
            'csv_flatten'       => $csv_flatten,

            /* "Used In" reporting. */
            'usage_expand'      => $usage_expand,
            'usage_deep'        => $usage_deep,
            'usage_only'        => $usage_only,
        );
    }

    /**
     * Read one checkbox from the (already nonce-verified) $_POST.
     *
     * @param string $name      Field name.
     * @param bool   $submitted Whether this really is a form submission.
     * @param bool   $default   Value to use when the form was not submitted.
     * @return bool
     */
    private static function checkbox($name, $submitted, $default)
    {
        if (!$submitted) {
            return $default;
        }
        return isset($_POST[$name]); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verified the nonce.
    }

    /**
     * Fill in omitted options so consumers can read the keys unconditionally.
     *
     * @param array $o
     * @return array
     */
    public static function with_defaults($o)
    {
        $defaults = array(
            'text_repair'   => true,
            'text_entities' => true,
            'text_ascii'    => false,
            'csv_delimiter' => 'comma',
            'csv_flatten'   => true,
            'usage_expand'  => false,
            'usage_deep'    => false,
            'usage_only'    => false,
        );

        foreach ($defaults as $key => $value) {
            if (!isset($o[$key])) {
                $o[$key] = $value;
            }
        }

        return $o;
    }

    /**
     * Validate options.
     *
     * @param array $o
     * @return true|string True when valid, otherwise an error message.
     */
    public static function validate($o)
    {
        if ($o['export_type'] === '' || empty($o['export_fields'])) {
            return __('Sorry, you missed something. Please select at least one <strong>field</strong> to export, and try again. :)', 'export-media-urls');
        }

        if (($o['export_type'] === 'csv' || $o['export_type'] === 'json') && $o['csv_name'] === '') {
            return __('Invalid/Missing download File Name!', 'export-media-urls');
        }

        if ($o['date_range'] === 'range') {
            if ($o['start_date'] === '' || $o['end_date'] === '') {
                return __('Please select both dates!', 'export-media-urls');
            }
            if (strtotime($o['start_date']) > strtotime($o['end_date'])) {
                return __('Start date cannot be greater than end date!', 'export-media-urls');
            }
        }

        return true;
    }
}
