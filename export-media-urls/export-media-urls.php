<?php

/**
 * Plugin Name: Export Media URLs
 * Plugin URI:  https://wordpress.org/plugins/export-media-urls/
 * Description: Extract every Media Library URL along with title, file name, size, dimensions, alt text, caption, MIME type, parent post and more. Filter by media type, attachment status, author and date range. Display in the dashboard or download as CSV or JSON. Useful for migrations, SEO/accessibility audits and library cleanup.
 * Version:     3.2
 * Author:      Atlas Gondal
 * Author URI:  https://AtlasGondal.com/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: export-media-urls
 * Domain Path: /languages
 * Requires PHP: 5.4
 * Requires at least: 3.6
 *
 * @package Export_Media_URLs
 */

/*
    Copyright (c) 2020- Atlas Gondal (contact : https://atlasgondal.com/contact-me/)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/

namespace Export_Media_URLs;

defined('WPINC') || exit;

require_once plugin_dir_path(__FILE__) . 'classes/constants.php';
require_once plugin_dir_path(__FILE__) . 'classes/class-request.php';
require_once plugin_dir_path(__FILE__) . 'classes/class-functions.php';

/**
 * Plugin bootstrap. Wires up hooks; all real work lives in the classes/ layer
 * and the views/ templates.
 */
class ExportMediaURLs
{
    public function __construct()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'add_plugin_page'));
        register_activation_hook(__FILE__, array($this, 'on_activate'));
        add_action('admin_init', array($this, 'redirect_on_activation'));
        add_filter('admin_footer_text', array($this, 'footer_text'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_post_' . Constants::EXPORT_ACTION, array($this, 'handle_export'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('export-media-urls', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_plugin_page()
    {
        add_management_page(
            Constants::PLUGIN_NAME,
            Constants::PLUGIN_NAME,
            Constants::PLUGIN_SETTINGS_PAGE_CAPABILITY,
            Constants::PLUGIN_SETTINGS_PAGE_SLUG,
            array($this, 'include_settings_page')
        );
    }

    public function include_settings_page()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'export';
        $tool_tabs = array('summary', 'missing', 'duplicates', 'heavy', 'alt');
        if (in_array($tab, $tool_tabs, true)) {
            include plugin_dir_path(__FILE__) . 'views/tools-tab.php';
        } else {
            include plugin_dir_path(__FILE__) . 'views/settings-page.php';
        }
    }

    public function enqueue_scripts($hook)
    {
        if (strpos((string) $hook, Constants::PLUGIN_SETTINGS_PAGE_SLUG) === false) {
            return;
        }

        $dir = plugin_dir_path(__FILE__);
        $css = $dir . 'assets/css/style.css';
        $js = $dir . 'assets/js/script.js';

        // Version assets by file mtime so updated CSS/JS is never served stale.
        $css_ver = file_exists($css) ? filemtime($css) : Constants::PLUGIN_VERSION;
        $js_ver = file_exists($js) ? filemtime($js) : Constants::PLUGIN_VERSION;

        wp_enqueue_style('emu-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), $css_ver, 'all');
        wp_enqueue_script('emu-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array(), $js_ver, true);
    }

    /**
     * admin-post.php handler for streamed CSV / JSON downloads. Runs before any
     * output so headers can be sent and the body streamed row by row.
     */
    public function handle_export()
    {
        if (!current_user_can(Constants::PLUGIN_SETTINGS_PAGE_CAPABILITY)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'export-media-urls'));
        }

        check_admin_referer(Constants::EXPORT_NONCE_ACTION);

        $options = EMU_Request::from_post();
        if (null === $options) {
            wp_die(
                esc_html__('Security token validation failed!', 'export-media-urls'),
                esc_html__('Export Media URLs', 'export-media-urls'),
                array('back_link' => true)
            );
        }

        $valid = EMU_Request::validate($options);
        if ($valid !== true) {
            wp_die(wp_kses_post($valid), esc_html__('Export Media URLs', 'export-media-urls'), array('back_link' => true));
        }

        if ($options['export_type'] !== 'csv' && $options['export_type'] !== 'json') {
            wp_die(esc_html__('Invalid export type.', 'export-media-urls'), esc_html__('Export Media URLs', 'export-media-urls'), array('back_link' => true));
        }

        update_user_meta(get_current_user_id(), Constants::LAST_FIELDS_META, $options['export_fields']);

        $functions = new EMU_Functions();
        $functions->stream($options);
        exit;
    }

    public function on_activate()
    {
        if (version_compare(PHP_VERSION, Constants::MIN_PHP_VERSION, '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            $plugin_data = get_plugin_data(__FILE__);
            $plugin_version = $plugin_data['Version'];
            $plugin_name = $plugin_data['Name'];
            wp_die(
                '<h1>' . esc_html__('Could not activate plugin: PHP version error', 'export-media-urls') . '</h1>'
                . '<h2>' . esc_html__('PLUGIN:', 'export-media-urls') . ' <i>' . esc_html($plugin_name . ' ' . $plugin_version) . '</i></h2>'
                . '<p><strong>' . esc_html__('You are using PHP version', 'export-media-urls') . ' ' . esc_html(PHP_VERSION) . '</strong>. '
                /* translators: %s: minimum required PHP version. */
                . sprintf(esc_html__('This plugin requires PHP version %s or greater.', 'export-media-urls'), esc_html(Constants::MIN_PHP_VERSION)) . '</p>'
                . '<p>' . esc_html__('WordPress itself recommends using PHP version 7.4 or greater', 'export-media-urls') . ': '
                . '<a href="https://wordpress.org/about/requirements/" target="_blank">' . esc_html__('Official WordPress requirements', 'export-media-urls') . '</a>. '
                . esc_html__('Please upgrade your PHP version or contact your Server administrator.', 'export-media-urls') . '</p>',
                esc_html__('Could not activate plugin: PHP version error', 'export-media-urls'),
                array('back_link' => true)
            );
        }

        set_transient('export_media_urls_activation_redirect', true, 30);
    }

    public function redirect_on_activation()
    {
        if (!get_transient('export_media_urls_activation_redirect')) {
            return;
        }

        delete_transient('export_media_urls_activation_redirect');

        // Don't hijack the screen when several plugins are activated at once.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading WordPress' own activation flag; no state change.
        if (isset($_GET['activate-multi'])) {
            return;
        }

        wp_safe_redirect(add_query_arg(array('page' => Constants::PLUGIN_SETTINGS_PAGE_SLUG), admin_url('tools.php')));
        exit;
    }

    public function footer_text($footer_text)
    {
        $current_screen = get_current_screen();
        $is_plugin_screen = ($current_screen && false !== strpos($current_screen->id, Constants::PLUGIN_SETTINGS_PAGE_SLUG));

        if ($is_plugin_screen) {
            $footer_text = sprintf(
                /* translators: 1: plugin name, 2: five-star rating link. */
                __('Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'export-media-urls'),
                '<strong>Export Media URLs</strong>',
                '<a href="https://wordpress.org/support/plugin/export-media-urls/reviews/" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
            );
        }

        return $footer_text;
    }
}

new ExportMediaURLs();
