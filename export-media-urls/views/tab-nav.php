<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'classes/constants.php';

// This template is only ever included from a class method, so its variables are
// method-scoped, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation.
$emu_active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'export';

$emu_base = admin_url('tools.php');

// A single, flat tab bar. The first tab is the exporter; the rest are the
// Media Tools audits. Each carries a dashicon for quick visual scanning.
$emu_tabs = array(
    'export'     => array('label' => __('Export', 'export-media-urls'),        'icon' => 'download'),
    'summary'    => array('label' => __('Summary', 'export-media-urls'),        'icon' => 'dashboard'),
    'missing'    => array('label' => __('Missing Files', 'export-media-urls'),  'icon' => 'warning'),
    'duplicates' => array('label' => __('Duplicates', 'export-media-urls'),     'icon' => 'admin-page'),
    'heavy'      => array('label' => __('Heavy Images', 'export-media-urls'),   'icon' => 'format-image'),
    'alt'        => array('label' => __('Alt Text', 'export-media-urls'),       'icon' => 'universal-access-alt'),
);

if (!isset($emu_tabs[$emu_active_tab])) {
    $emu_active_tab = 'export';
}
?>
<div class="emu-header">
    <span class="dashicons dashicons-format-gallery emu-header-icon"></span>
    <h1 class="emu-page-title"><?php echo esc_html(Constants::PLUGIN_NAME); ?></h1>
</div>
<nav class="nav-tab-wrapper emu-tab-nav">
    <?php foreach ($emu_tabs as $emu_key => $emu_tab) : ?>
        <a href="<?php echo esc_url(add_query_arg(array('page' => Constants::PLUGIN_SETTINGS_PAGE_SLUG, 'tab' => $emu_key), $emu_base)); ?>"
           class="nav-tab <?php echo ($emu_active_tab === $emu_key) ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-<?php echo esc_attr($emu_tab['icon']); ?>"></span>
            <?php echo esc_html($emu_tab['label']); ?>
        </a>
    <?php endforeach; ?>
</nav>
