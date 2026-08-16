<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(__FILE__) . 'constants.php';
require_once plugin_dir_path(__FILE__) . 'class-request.php';
require_once plugin_dir_path(__FILE__) . 'class-fields.php';
require_once plugin_dir_path(__FILE__) . 'class-query.php';
require_once plugin_dir_path(__FILE__) . 'class-exporter.php';
require_once plugin_dir_path(__FILE__) . 'class-usage-index.php';

/**
 * Orchestrates an export run: query (in batches) -> build rows from the field
 * registry -> stream to the chosen writer.
 *
 * Attachments are fetched in fixed-size batches and streamed out one at a time,
 * so peak memory stays bounded no matter how large the media library is. The
 * attachment metadata cache is only primed when a field that needs it (size or
 * dimensions) is actually selected.
 */
class EMU_Functions
{
    /** @var EMU_Fields */
    private $fields;
    /** @var EMU_Exporter */
    private $exporter;

    public function __construct()
    {
        $this->fields = new EMU_Fields();
        $this->exporter = new EMU_Exporter();
    }

    public function fields()
    {
        return $this->fields;
    }

    /**
     * Iterate matched attachments in batches, invoking $callback($row) for each.
     *
     * @param array    $options  Sanitized options.
     * @param callable $callback Receives one row array per matched attachment.
     * @return int Number of rows produced.
     */
    public function each_row($options, $callback)
    {
        $options = EMU_Request::with_defaults($options);
        $need_meta = $this->fields->needs_meta($options['export_fields']);

        // Build the where-used index once per run, and only when a usage column
        // is actually selected (it scans all post content, so it is not free).
        $usage = null;
        if ($this->fields->needs_usage($options['export_fields'])) {
            $usage = new EMU_Usage_Index();
            $usage->build(!empty($options['usage_deep']));
        }

        $expand = ($usage !== null) && !empty($options['usage_expand']);
        $only_used = ($usage !== null) && !empty($options['usage_only']);

        $args_base = EMU_Query::build($options);
        $args_base['no_found_rows'] = true;                 // skip SQL_CALC_FOUND_ROWS (slow on big tables)
        $args_base['update_post_term_cache'] = false;       // attachments carry no taxonomy we export
        $args_base['update_post_meta_cache'] = true;        // alt text / metadata live in post meta

        $batch = Constants::BATCH_SIZE;
        $start = ($options['offset'] === 'all' || $options['offset'] === '') ? 0 : (int) $options['offset'];
        $limit = ($options['post_per_page'] === 'all') ? -1 : (int) $options['post_per_page'];

        // $fetched counts attachments, not rows, so "Number of items" keeps
        // meaning "media items" when one item expands to many rows.
        $fetched = 0;
        $emitted = 0;
        while (true) {
            $this_batch = $batch;
            if ($limit !== -1) {
                $remaining = $limit - $fetched;
                if ($remaining <= 0) {
                    break;
                }
                $this_batch = min($this_batch, $remaining);
            }

            $args = $args_base;
            $args['posts_per_page'] = $this_batch;
            $args['offset'] = $start + $fetched;

            $query = new \WP_Query($args);
            if (!$query->have_posts()) {
                wp_reset_postdata();
                break;
            }

            $in_batch = 0;
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $context = $this->build_context($post_id, $need_meta, $usage);

                $in_batch++;
                $fetched++;

                if ($only_used && empty($context['used_in'])) {
                    continue; // skipped, but still counted as fetched
                }

                // One row per referencing post when expanding, otherwise one
                // combined row. Items with no usages still get one blank row.
                $rows_for_item = ($expand && !empty($context['used_in']))
                    ? $context['used_in']
                    : array(null);

                foreach ($rows_for_item as $usage_row) {
                    $context['usage_row'] = $usage_row;

                    $row = array();
                    foreach ($options['export_fields'] as $key) {
                        $row[] = $this->fields->value($key, $post_id, $options, $context);
                    }
                    call_user_func($callback, $row);
                    $emitted++;
                }
            }
            wp_reset_postdata();

            if ($in_batch < $this_batch) {
                break; // last (partial) batch — nothing more to fetch
            }
        }

        return $emitted;
    }

    /**
     * Stream a CSV/JSON download. Must run before any output (admin-post.php).
     *
     * @param array $options
     */
    public function stream($options)
    {
        $options = EMU_Request::with_defaults($options);

        if (!$this->has_any($options)) {
            wp_die(
                esc_html__('No result found in that range, please reselect and try again!', 'export-media-urls'),
                esc_html__('Export Media URLs', 'export-media-urls'),
                array('back_link' => true)
            );
        }

        $labels = $this->fields->labels_for($options['export_fields']);
        $exporter = $this->exporter;
        $exporter->configure_csv($options);

        if ($options['export_type'] === 'json') {
            $exporter->send_download_headers('application/json', $options['csv_name'], 'json');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body, not HTML.
            echo '[';
            $first = true;
            $this->each_row($options, function ($row) use ($exporter, $labels, &$first) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body, not HTML.
                echo ($first ? '' : ',') . $exporter->json_record($labels, $row);
                $first = false;
            });
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body, not HTML.
            echo ']';
        } else {
            $exporter->send_download_headers('text/csv', $options['csv_name'], 'csv');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV BOM + header row, not HTML.
            echo "\xEF\xBB\xBF" . $exporter->csv_line($labels);
            $this->each_row($options, function ($row) use ($exporter) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download body, not HTML.
                echo $exporter->csv_line($row);
            });
        }

        exit;
    }

    /**
     * Render every matched row inline as an HTML table, paginated in the browser.
     *
     * @param array $options
     */
    public function render_here($options)
    {
        $options = EMU_Request::with_defaults($options);

        $rows = array();
        $this->each_row($options, function ($row) use (&$rows) {
            $rows[] = $row;
        });

        if (empty($rows)) {
            echo "<div class='notice notice-error' style='width: 93%'>" . esc_html__('No result found in that range, please reselect and try again!', 'export-media-urls') . '</div>';
            return;
        }

        $labels = $this->fields->labels_for($options['export_fields'], true);
        $this->exporter->render_html_table($labels, $rows, count($rows));
    }

    /**
     * Cheap existence check (one ID, no caches) so we can fail before sending
     * download headers when nothing matches.
     */
    public function has_any($options)
    {
        $args = EMU_Query::build($options);
        $args['posts_per_page'] = 1;
        $args['fields'] = 'ids';
        $args['no_found_rows'] = true;
        $args['update_post_term_cache'] = false;
        $args['update_post_meta_cache'] = false;
        $args['offset'] = ($options['offset'] === 'all' || $options['offset'] === '') ? 0 : (int) $options['offset'];

        $query = new \WP_Query($args);
        $has = $query->have_posts();
        wp_reset_postdata();

        return $has;
    }

    /**
     * Precompute the per-item data shared by the field callbacks. The attachment
     * metadata blob (dimensions, sized images) is only fetched when needed, and
     * the where-used list is only present when the usage index was built.
     *
     * @param int                  $post_id
     * @param bool                 $need_meta
     * @param EMU_Usage_Index|null $usage
     * @return array
     */
    private function build_context($post_id, $need_meta, $usage = null)
    {
        $meta = array();
        if ($need_meta) {
            $found = wp_get_attachment_metadata($post_id);
            if (is_array($found)) {
                $meta = $found;
            }
        }

        $used_in = ($usage !== null) ? $usage->used_in($post_id) : array();

        // 'usage_row' is set per emitted row by each_row(); null means the row
        // covers every referencing post at once.
        return array('meta' => $meta, 'used_in' => $used_in, 'usage_row' => null);
    }
}
