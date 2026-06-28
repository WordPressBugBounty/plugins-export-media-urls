<?php

namespace Export_Media_URLs;

// Run only via WordPress' uninstall mechanism.
defined('WP_UNINSTALL_PLUGIN') || exit;

require_once plugin_dir_path(__FILE__) . 'classes/constants.php';

// Remove the per-user "last export fields" preference for every user.
delete_metadata('user', 0, Constants::LAST_FIELDS_META, '', true);
