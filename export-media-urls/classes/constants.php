<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

/**
 * Plugin-wide constants.
 *
 * NOTE: class constants are declared without a visibility modifier on purpose.
 * `public const` requires PHP 7.1+, but this plugin targets PHP 5.4 onward.
 */
class Constants
{
    const PLUGIN_NAME = 'Export Media URLs';
    const PLUGIN_VERSION = '3.1';
    const PLUGIN_SLUG = 'export-media-urls';
    const PLUGIN_FILE = 'export-media-urls/export-media-urls.php';
    const PLUGIN_DIR = 'export-media-urls';
    const PLUGIN_URL = 'https://wordpress.org/plugins/export-media-urls/';
    const PLUGIN_AUTHOR = 'Atlas Gondal';
    const PLUGIN_AUTHOR_URI = 'https://AtlasGondal.com/';
    const PLUGIN_LICENSE = 'GPLv2 or later';
    const PLUGIN_LICENSE_URI = 'http://www.gnu.org/licenses/gpl-2.0.html';
    const PLUGIN_TEXT_DOMAIN = 'export-media-urls';
    const PLUGIN_SETTINGS_PAGE_CAPABILITY = 'manage_options';
    const PLUGIN_SETTINGS_PAGE_SLUG = 'export-media-urls-settings';

    /** Minimum supported PHP version. Keep in sync with readme.txt "Requires PHP". */
    const MIN_PHP_VERSION = '5.4';

    /** Nonce action used by the export form. */
    const EXPORT_NONCE_ACTION = 'export_media_urls';

    /** admin-post.php action used for streamed downloads (CSV / JSON). */
    const EXPORT_ACTION = 'emu_export';

    /** User-meta key that remembers the last-used field selection. */
    const LAST_FIELDS_META = '_emu_last_export_fields';

    /** Attachments fetched per batch when exporting (keeps memory bounded). */
    const BATCH_SIZE = 500;

    /** Default rows-per-page for the on-screen results table. */
    const DEFAULT_PER_PAGE = 100;

    /* --- Media Tools tab --- */

    /** Nonce action for the bulk alt-text editor. */
    const ALT_NONCE_ACTION = 'emu_save_alt';

    /** An image is flagged "heavy" when its file exceeds this many bytes... */
    const HEAVY_IMAGE_BYTES = 1048576; // 1 MB

    /** ...or when either dimension exceeds this many pixels. */
    const HEAVY_IMAGE_DIMENSION = 2500;
}
