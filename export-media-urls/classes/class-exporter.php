<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';
require_once plugin_dir_path(__FILE__) . 'class-text.php';

/**
 * Output helpers for the three export targets: streamed CSV, streamed JSON,
 * and an in-page HTML table.
 *
 * The streaming helpers expose header senders plus per-row encoders so the
 * orchestrator can echo one row at a time (bounded memory) instead of building
 * the whole file in memory. Nothing is written to wp-content/uploads.
 */
class EMU_Exporter
{
    /** @var string Character written between CSV fields. */
    private $delimiter = ',';

    /** @var bool Whether line breaks inside a CSV field are collapsed. */
    private $flatten = true;

    /** @var string Text substituted for each run of line breaks when flattening. */
    private $flatten_with = ' ';

    /**
     * Configure CSV output. Called once per run.
     *
     * @param array $o Sanitized options (csv_delimiter / csv_flatten).
     */
    public function configure_csv($o)
    {
        $this->delimiter = self::delimiter_char(isset($o['csv_delimiter']) ? $o['csv_delimiter'] : 'comma');
        $this->flatten = !empty($o['csv_flatten']);
    }

    /**
     * Delimiter choice to character. Unrecognised values fall back to a comma,
     * so a tampered value can never emit an arbitrary byte.
     *
     * @param string $choice
     * @return string
     */
    public static function delimiter_char($choice)
    {
        switch ($choice) {
            case 'semicolon':
                return ';';
            case 'tab':
                return "\t";
            case 'comma':
            default:
                return ',';
        }
    }

    /**
     * Neutralize spreadsheet formula injection.
     *
     * A cell beginning with = + - @ (or a leading tab/carriage return) can be
     * read as a formula by Excel / Google Sheets. Prefixing a single quote
     * forces it to be treated as text. Applied to CSV only.
     *
     * @param string $value
     * @return string
     */
    public function neutralize($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return $value;
        }

        $first = substr($value, 0, 1);
        if (in_array($first, array('=', '+', '-', '@', "\t", "\r"), true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Send the HTTP headers for a file download.
     *
     * @param string $content_type e.g. 'text/csv'.
     * @param string $filename     Base filename (no extension).
     * @param string $ext          Extension to append.
     */
    public function send_download_headers($content_type, $filename, $ext)
    {
        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            $filename = 'export-media-urls';
        }

        // A downloaded CSV/JSON file must contain nothing but our data.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        add_filter('doing_it_wrong_trigger_error', '__return_false', 99);

        nocache_headers();
        header('Content-Type: ' . $content_type . '; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.' . $ext . '"');
    }

    /**
     * Encode one CSV record (RFC 4180): every field quoted, inner quotes doubled.
     * Quoting handles delimiters and quotes, but not line breaks inside a value
     * — flattening collapses those so one record is always one line.
     *
     * @param array $fields
     * @return string
     */
    public function csv_line($fields)
    {
        $cells = array();
        foreach ($fields as $field) {
            $value = (string) $field;
            if ($this->flatten) {
                $value = EMU_Text::flatten($value, $this->flatten_with);
            } else {
                // Line breaks are kept on purpose; stray control characters are not.
                $value = EMU_Text::strip_controls($value);
            }
            $value = $this->neutralize($value);
            $cells[] = '"' . str_replace('"', '""', $value) . '"';
        }
        return implode($this->delimiter, $cells) . "\r\n";
    }

    /**
     * Encode one row as a JSON object keyed by the column labels.
     *
     * json_encode() returns false on invalid UTF-8, which used to leave a hole
     * in the array and break the whole download. Values are repaired first.
     *
     * @param array $labels
     * @param array $row
     * @return string
     */
    public function json_record($labels, $row)
    {
        $record = array();
        foreach ($labels as $i => $label) {
            $key = EMU_Text::ensure_utf8((string) $label);
            $record[$key] = isset($row[$i]) ? EMU_Text::ensure_utf8((string) $row[$i]) : '';
        }

        // json_encode() (PHP 5.2+) is used directly so the plugin supports WordPress 3.6 (wp_json_encode is 4.1+).
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- intentional for WordPress 3.6 compatibility.
        $encoded = json_encode($record);
        if (!is_string($encoded)) {
            // Last resort: same keys, empty values, so the array stays valid.
            $safe = array();
            foreach (array_keys($record) as $key) {
                $safe[$key] = '';
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- intentional for WordPress 3.6 compatibility.
            $encoded = json_encode($safe);
        }

        return is_string($encoded) ? $encoded : '{}';
    }

    /**
     * Render the on-page results table with client-side pagination controls.
     *
     * @param array $labels Header labels (already prefixed with the '#' column).
     * @param array $rows   Array of row arrays.
     * @param int   $total  Number of rows being shown.
     */
    public function render_html_table($labels, $rows, $total)
    {
        echo "<h1 align='center' style='padding: 10px 0;'><strong>" . esc_html__('Below is a list of Exported Media Data:', 'export-media-urls') . '</strong></h1>';
        echo "<h2 align='center' style='font-weight: normal;'>" . esc_html__('Total number of items', 'export-media-urls') . ': <strong>' . esc_html($total) . '</strong>.</h2>';

        // Results-per-page selector (handled in the browser by script.js).
        echo '<div class="emu-results-toolbar">';
        echo '<label class="emu-perpage-label">' . esc_html__('Results per page:', 'export-media-urls') . ' ';
        echo '<select class="emu-perpage">';
        foreach (array('100', '250', '500', '750', '1000') as $size) {
            echo '<option value="' . esc_attr($size) . '"' . selected($size, (string) Constants::DEFAULT_PER_PAGE, false) . '>' . esc_html($size) . '</option>';
        }
        echo '<option value="all">' . esc_html__('All', 'export-media-urls') . '</option>';
        echo '</select></label>';
        echo '</div>';

        echo '<table id="outputData" class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        foreach ($labels as $label) {
            echo '<th>' . esc_html($label) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $counter = 1;
        foreach ($rows as $row) {
            echo '<tr><td>' . (int) $counter . '</td>';
            foreach ($row as $cell) {
                echo '<td>' . esc_html($cell) . '</td>';
            }
            echo '</tr>';
            $counter++;
        }

        echo '</tbody></table>';

        // Pagination controls — wired up and shown by script.js only when needed.
        echo '<div class="emu-pagination" style="display:none">';
        echo '<button type="button" class="button emu-prev">' . esc_html__('Previous', 'export-media-urls') . '</button>';
        echo '<span class="emu-page-indicator"></span>';
        echo '<button type="button" class="button emu-next">' . esc_html__('Next', 'export-media-urls') . '</button>';
        echo '</div>';
    }
}
