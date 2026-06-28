<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'classes/constants.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/class-media-tools.php';

// This template is only ever included from within ExportMediaURLs::include_settings_page(),
// so its variables are method-scoped, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if (!current_user_can(Constants::PLUGIN_SETTINGS_PAGE_CAPABILITY)) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'export-media-urls'));
}

$emu_tools = new EMU_Media_Tools();

/* ---------------------------------------------------------------------- */
/* Section + pagination state (all read-only GET params).                 */
/* The flat tab bar passes the section directly as ?tab=.                  */
/* ---------------------------------------------------------------------- */

$emu_tool_tabs = array('summary', 'missing', 'duplicates', 'heavy', 'alt');

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only section routing.
$section = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'summary';
if (!in_array($section, $emu_tool_tabs, true)) {
    $section = 'summary';
}

$emu_per_page_options = array('100', '250', '500', '1000', 'all');
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-size selector.
$per_page_raw = isset($_GET['per_page']) ? sanitize_key(wp_unslash($_GET['per_page'])) : (string) Constants::DEFAULT_PER_PAGE;
if (!in_array($per_page_raw, $emu_per_page_options, true)) {
    $per_page_raw = (string) Constants::DEFAULT_PER_PAGE;
}
$limit = ('all' === $per_page_raw) ? 0 : (int) $per_page_raw;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
$paged = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;

/* ---------------------------------------------------------------------- */
/* Handle the bulk alt-text save (POST to self, nonce-guarded).           */
/* ---------------------------------------------------------------------- */
$emu_saved = -1;
if (isset($_POST['save_alt'])) {
    if (
        isset($_POST['_wpnonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), Constants::ALT_NONCE_ACTION)
    ) {
        $emu_alt_input = (isset($_POST['alt']) && is_array($_POST['alt']))
            ? array_map('sanitize_text_field', wp_unslash($_POST['alt']))
            : array();
        $emu_saved = $emu_tools->save_alt($emu_alt_input);
    } else {
        $emu_saved = -2;
    }
}

/*
 * Build each section's dataset and its true item total.
 *
 *  - Alt lists every image: $total is the image count (SQL); the page itself is
 *    fetched after the pagination maths via window().
 *  - Missing / Heavy / Duplicates are sparse findings: scan the whole library
 *    once and paginate the findings, so the count reflects what is shown.
 */
$emu_findings = null; // full findings list for the scan-based sections
if ('missing' === $section) {
    $emu_findings = $emu_tools->all_missing();
} elseif ('heavy' === $section) {
    $emu_findings = $emu_tools->all_heavy();
} elseif ('duplicates' === $section) {
    $emu_findings = $emu_tools->all_duplicates();
}

if ('summary' === $section) {
    $total = 0;
} elseif ('alt' === $section) {
    $total = $emu_tools->total(true);
} else {
    $total = count($emu_findings);
}

$total_pages = ($limit > 0 && $total > 0) ? (int) ceil($total / $limit) : 1;
if ($paged > $total_pages) {
    $paged = $total_pages;
}
$offset = ($limit > 0) ? ($paged - 1) * $limit : 0;

/* The current page slice of the findings (scan-based sections only). */
$emu_page = ($emu_findings === null)
    ? array()
    : (($limit > 0) ? array_slice($emu_findings, $offset, $limit) : $emu_findings);

$emu_base = admin_url('tools.php');
$emu_args = array(
    'page'     => Constants::PLUGIN_SETTINGS_PAGE_SLUG,
    'tab'      => $section,
    'per_page' => $per_page_raw,
);

/* Paging is only "in effect" once the base set exceeds the smallest page size. */
$emu_paging_active = ($total > (int) Constants::DEFAULT_PER_PAGE);

/**
 * Top-right "Items per page" selector. Rendered only when paging is in effect,
 * so single-page sections show no toolbar at all.
 */
$emu_perpage_top = function () use ($section, $emu_per_page_options, $per_page_raw, $emu_paging_active) {
    if (!$emu_paging_active) {
        return;
    }
    ?>
    <div class="emu-tools-toolbar">
        <form method="get" class="emu-perpage-form">
            <input type="hidden" name="page" value="<?php echo esc_attr(Constants::PLUGIN_SETTINGS_PAGE_SLUG); ?>" />
            <input type="hidden" name="tab" value="<?php echo esc_attr($section); ?>" />
            <label class="emu-perpage-label"><?php esc_html_e('Items per page:', 'export-media-urls'); ?>
                <select name="per_page" onchange="this.form.submit()">
                    <?php foreach ($emu_per_page_options as $opt) : ?>
                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($opt, $per_page_raw); ?>>
                            <?php echo ('all' === $opt) ? esc_html__('All', 'export-media-urls') : esc_html($opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript><button type="submit" class="button"><?php esc_html_e('Apply', 'export-media-urls'); ?></button></noscript>
        </form>
    </div>
    <?php
};

/**
 * Bottom, centered prev / page / next pager. Rendered only when there is more
 * than one page — so it never appears on single-page or empty sections.
 */
$emu_pager_bottom = function () use ($paged, $total_pages, $total, $emu_base, $emu_args) {
    if ($total_pages <= 1) {
        return;
    }
    ?>
    <div class="emu-pager-bottom">
        <?php if ($paged > 1) : ?>
            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($emu_args, array('paged' => $paged - 1)), $emu_base)); ?>">&lsaquo; <?php esc_html_e('Prev', 'export-media-urls'); ?></a>
        <?php else : ?>
            <span class="button disabled">&lsaquo; <?php esc_html_e('Prev', 'export-media-urls'); ?></span>
        <?php endif; ?>
        <span class="emu-pager-page">
            <?php
            /* translators: 1: current page, 2: total pages, 3: total items. */
            echo esc_html(sprintf(__('Page %1$s of %2$s (%3$s items)', 'export-media-urls'), number_format_i18n($paged), number_format_i18n($total_pages), number_format_i18n($total)));
            ?>
        </span>
        <?php if ($paged < $total_pages) : ?>
            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($emu_args, array('paged' => $paged + 1)), $emu_base)); ?>"><?php esc_html_e('Next', 'export-media-urls'); ?> &rsaquo;</a>
        <?php else : ?>
            <span class="button disabled"><?php esc_html_e('Next', 'export-media-urls'); ?> &rsaquo;</span>
        <?php endif; ?>
    </div>
    <?php
};
?>

<div class="wrap">

    <?php include plugin_dir_path(__FILE__) . 'tab-nav.php'; ?>

    <p class="emu-subtitle"><?php esc_html_e('Audit and clean up your Media Library.', 'export-media-urls'); ?></p>
    <hr class="emu-header-divider" />

    <?php if ($emu_saved >= 0) : ?>
        <div class="notice notice-success is-dismissible"><p>
            <?php
            /* translators: %d: number of images whose alt text was updated. */
            echo esc_html(sprintf(_n('Updated alt text for %d image.', 'Updated alt text for %d images.', $emu_saved, 'export-media-urls'), $emu_saved));
            ?>
        </p></div>
    <?php elseif (-2 === $emu_saved) : ?>
        <div class="notice notice-error"><p><?php esc_html_e('Security token validation failed!', 'export-media-urls'); ?></p></div>
    <?php endif; ?>

    <?php
    /* ====================================================================== */
    /* SUMMARY (dashboard landing)                                            */
    /* ====================================================================== */
    if ('summary' === $section) :
        $emu_summary = $emu_tools->summary();

        $emu_features = array(
            'missing'    => array(
                'icon'  => 'warning',
                'title' => __('Missing Files', 'export-media-urls'),
                'desc'  => __('Find library entries whose file is gone from disk.', 'export-media-urls'),
            ),
            'duplicates' => array(
                'icon'  => 'admin-page',
                'title' => __('Duplicates', 'export-media-urls'),
                'desc'  => __('Spot byte-for-byte identical files wasting space.', 'export-media-urls'),
            ),
            'heavy'      => array(
                'icon'  => 'format-image',
                'title' => __('Heavy Images', 'export-media-urls'),
                'desc'  => __('Catch oversized images that slow your pages.', 'export-media-urls'),
            ),
            'alt'        => array(
                'icon'  => 'universal-access-alt',
                'title' => __('Alt Text', 'export-media-urls'),
                'desc'  => __('Fix missing alt text for SEO and accessibility.', 'export-media-urls'),
            ),
        );
        ?>
        <h2 class="emu-tool-heading"><?php esc_html_e('At a Glance', 'export-media-urls'); ?></h2>
        <div class="emu-cards">
            <?php foreach ($emu_summary['groups'] as $group => $count) : ?>
                <div class="emu-card">
                    <span class="emu-card-label"><?php echo esc_html($group); ?></span>
                    <span class="emu-card-num"><?php echo esc_html(number_format_i18n($count)); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="emu-card emu-card-total">
                <span class="emu-card-label"><?php esc_html_e('Total Items', 'export-media-urls'); ?></span>
                <span class="emu-card-num"><?php echo esc_html(number_format_i18n($emu_summary['total'])); ?></span>
            </div>
            <?php if (null !== $emu_summary['bytes']) : ?>
                <div class="emu-card emu-card-total">
                    <span class="emu-card-label"><?php esc_html_e('Disk Usage (uploads)', 'export-media-urls'); ?></span>
                    <span class="emu-card-num"><?php echo esc_html(size_format($emu_summary['bytes'])); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <p class="description"><?php esc_html_e('Counts are read straight from the database, so this stays instant on large libraries.', 'export-media-urls'); ?></p>

        <h2 class="emu-tool-heading"><?php esc_html_e('Maintenance Tools', 'export-media-urls'); ?></h2>
        <div class="emu-feature-grid">
            <?php foreach ($emu_features as $emu_fkey => $emu_feature) : ?>
                <a class="emu-feature-card" href="<?php echo esc_url(add_query_arg(array('page' => Constants::PLUGIN_SETTINGS_PAGE_SLUG, 'tab' => $emu_fkey), $emu_base)); ?>">
                    <span class="emu-feature-icon dashicons dashicons-<?php echo esc_attr($emu_feature['icon']); ?>"></span>
                    <span class="emu-feature-body">
                        <span class="emu-feature-title"><?php echo esc_html($emu_feature['title']); ?></span>
                        <span class="emu-feature-desc"><?php echo esc_html($emu_feature['desc']); ?></span>
                    </span>
                    <span class="emu-feature-arrow dashicons dashicons-arrow-right-alt2"></span>
                </a>
            <?php endforeach; ?>
        </div>

    <?php
    /* ====================================================================== */
    /* Paged sections                                                         */
    /* ====================================================================== */
    else :

        if ('missing' === $section) :
            $emu_rows = $emu_page;
            ?>
            <h2 class="emu-tool-heading"><?php esc_html_e('Missing Files', 'export-media-urls'); ?></h2>
            <p class="description"><?php esc_html_e('Media items that exist in the database but whose file is no longer on disk.', 'export-media-urls'); ?></p>
            <?php $emu_perpage_top(); ?>
            <?php if (empty($emu_rows)) : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e('No missing files found. Every attachment has its file on disk.', 'export-media-urls'); ?></p></div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped emu-tool-table">
                    <thead><tr>
                        <th class="emu-col-id"><?php esc_html_e('ID', 'export-media-urls'); ?></th>
                        <th><?php esc_html_e('Title', 'export-media-urls'); ?></th>
                        <th><?php esc_html_e('Expected File Path', 'export-media-urls'); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($emu_rows as $row) : ?>
                            <tr>
                                <td class="emu-col-id"><?php echo esc_html($row['id']); ?></td>
                                <td><a href="<?php echo esc_url(get_edit_post_link($row['id'])); ?>"><?php echo esc_html($row['title']); ?></a></td>
                                <td><code><?php echo esc_html($row['file']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php $emu_pager_bottom(); ?>
            <?php endif; ?>

        <?php elseif ('duplicates' === $section) :
            $emu_dupes = $emu_page;
            ?>
            <h2 class="emu-tool-heading"><?php esc_html_e('Duplicate Files', 'export-media-urls'); ?></h2>
            <p class="description"><?php esc_html_e('Sets of attachments whose files are byte-for-byte identical (same size and content hash) — keep one and delete the rest to reclaim space.', 'export-media-urls'); ?></p>
            <?php $emu_perpage_top(); ?>
            <?php if (empty($emu_dupes)) : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e('No duplicate files found.', 'export-media-urls'); ?></p></div>
            <?php else : ?>
                <?php foreach ($emu_dupes as $i => $dupe) : ?>
                    <table class="wp-list-table widefat fixed striped emu-tool-table emu-dupe-group">
                        <thead><tr>
                            <th colspan="3">
                                <?php
                                /* translators: 1: group number, 2: human-readable file size. */
                                echo esc_html(sprintf(__('Duplicate group %1$d — %2$s each', 'export-media-urls'), $offset + $i + 1, size_format($dupe['bytes'])));
                                ?>
                            </th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($dupe['files'] as $file) : ?>
                                <tr>
                                    <td class="emu-col-id"><?php echo esc_html($file['id']); ?></td>
                                    <td><a href="<?php echo esc_url(get_edit_post_link($file['id'])); ?>"><?php echo esc_html($file['title']); ?></a></td>
                                    <td><a href="<?php echo esc_url($file['url']); ?>" target="_blank"><?php echo esc_html($file['url']); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                <?php $emu_pager_bottom(); ?>
            <?php endif; ?>

        <?php elseif ('heavy' === $section) :
            $emu_rows = $emu_page;
            ?>
            <h2 class="emu-tool-heading"><?php esc_html_e('Heavy Images', 'export-media-urls'); ?></h2>
            <p class="description">
                <?php
                /* translators: 1: file size threshold, 2: pixel dimension threshold. */
                echo esc_html(sprintf(__('Images larger than %1$s, or wider/taller than %2$d px — likely candidates for optimization.', 'export-media-urls'), size_format(Constants::HEAVY_IMAGE_BYTES), Constants::HEAVY_IMAGE_DIMENSION));
                ?>
            </p>
            <?php $emu_perpage_top(); ?>
            <?php if (empty($emu_rows)) : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e('No oversized images found.', 'export-media-urls'); ?></p></div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped emu-tool-table">
                    <thead><tr>
                        <th class="emu-col-id"><?php esc_html_e('ID', 'export-media-urls'); ?></th>
                        <th><?php esc_html_e('Title', 'export-media-urls'); ?></th>
                        <th><?php esc_html_e('File Size', 'export-media-urls'); ?></th>
                        <th><?php esc_html_e('Dimensions', 'export-media-urls'); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($emu_rows as $row) : ?>
                            <tr>
                                <td class="emu-col-id"><?php echo esc_html($row['id']); ?></td>
                                <td><a href="<?php echo esc_url(get_edit_post_link($row['id'])); ?>"><?php echo esc_html($row['title']); ?></a></td>
                                <td><?php echo esc_html(size_format($row['bytes'])); ?></td>
                                <td><?php echo esc_html($row['w'] . ' x ' . $row['h']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php $emu_pager_bottom(); ?>
            <?php endif; ?>

        <?php elseif ('alt' === $section) :
            $emu_posts = ($total > 0) ? $emu_tools->window($offset, $limit, true) : array();
            $emu_images = $emu_tools->images_in($emu_posts);
            ?>
            <h2 class="emu-tool-heading"><?php esc_html_e('Bulk Alt-Text Editing', 'export-media-urls'); ?></h2>
            <p class="description"><?php esc_html_e('Edit alt text for the images on this page, then save. Rows highlighted in yellow have no alt text.', 'export-media-urls'); ?></p>
            <?php $emu_perpage_top(); ?>
            <?php if (empty($emu_images)) : ?>
                <div class="notice notice-info inline"><p><?php esc_html_e('No images on this page.', 'export-media-urls'); ?></p></div>
            <?php else : ?>
                <form method="post" action="">
                    <table class="wp-list-table widefat fixed striped emu-tool-table emu-alt-table">
                        <thead><tr>
                            <th class="emu-col-thumb"><?php esc_html_e('Image', 'export-media-urls'); ?></th>
                            <th class="emu-col-title"><?php esc_html_e('Title', 'export-media-urls'); ?></th>
                            <th><?php esc_html_e('Alt Text', 'export-media-urls'); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($emu_images as $img) : ?>
                                <tr class="<?php echo ('' === trim($img['alt'])) ? 'emu-alt-missing' : ''; ?>">
                                    <td class="emu-col-thumb">
                                        <?php if ($img['thumb']) : ?>
                                            <img src="<?php echo esc_url($img['thumb']); ?>" alt="" width="50" height="50" />
                                        <?php endif; ?>
                                    </td>
                                    <td class="emu-col-title"><a href="<?php echo esc_url(get_edit_post_link($img['id'])); ?>"><?php echo esc_html($img['title']); ?></a></td>
                                    <td><input type="text" class="large-text" name="alt[<?php echo esc_attr($img['id']); ?>]" value="<?php echo esc_attr($img['alt']); ?>" /></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php wp_nonce_field(Constants::ALT_NONCE_ACTION); ?>
                    <p class="emu-alt-actions">
                        <button type="submit" name="save_alt" value="1" class="button button-primary button-hero"><?php esc_html_e('Save Alt Text', 'export-media-urls'); ?></button>
                    </p>
                </form>
                <?php $emu_pager_bottom(); ?>
            <?php endif; ?>

        <?php endif; ?>
    <?php endif; ?>

</div>
