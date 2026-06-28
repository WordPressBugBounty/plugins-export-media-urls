<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

require_once plugin_dir_path(dirname(__FILE__)) . 'classes/constants.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/class-functions.php';
require_once plugin_dir_path(dirname(__FILE__)) . 'classes/class-request.php';

// This template is only ever included from within ExportMediaURLs::include_settings_page(),
// so its variables are method-scoped, not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if (!current_user_can(Constants::PLUGIN_SETTINGS_PAGE_CAPABILITY)) {
    wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'export-media-urls'));
}

$emu_functions = new EMU_Functions();
$emu_fields = $emu_functions->fields();

/* ---------------------------------------------------------------------- */
/* Trust $_POST only after the nonce verifies. Every sticky value below is */
/* read once here (after verification) and reused when rendering the form. */
/* ---------------------------------------------------------------------- */

$emu_nonce_ok = isset($_POST['_wpnonce'])
    && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), Constants::EXPORT_NONCE_ACTION);
$emu_posted = $emu_nonce_ok;
$form_submitted = $emu_posted && isset($_POST['form_submitted']);

$last_fields = get_user_meta(get_current_user_id(), Constants::LAST_FIELDS_META, true);
if (!is_array($last_fields) || empty($last_fields)) {
    $last_fields = array('url');
}

$selected_fields    = ($emu_posted && isset($_POST['export_fields']))
    ? array_map('sanitize_text_field', (array) wp_unslash($_POST['export_fields']))
    : ($form_submitted ? array() : $last_fields);
$selected_media     = ($emu_posted && isset($_POST['media-type'])) ? sanitize_text_field(wp_unslash($_POST['media-type'])) : 'all';
$selected_attach    = ($emu_posted && isset($_POST['attachment-status'])) ? sanitize_text_field(wp_unslash($_POST['attachment-status'])) : 'all';
$selected_user      = ($emu_posted && isset($_POST['post-author'])) ? sanitize_text_field(wp_unslash($_POST['post-author'])) : 'all';
$selected_format    = ($emu_posted && isset($_POST['export-type'])) ? sanitize_text_field(wp_unslash($_POST['export-type'])) : 'csv';

$selected_range     = ($emu_posted && isset($_POST['date-range'])) ? sanitize_text_field(wp_unslash($_POST['date-range'])) : 'all';
$start_date         = ($emu_posted && isset($_POST['start-date'])) ? sanitize_text_field(wp_unslash($_POST['start-date'])) : '';
$end_date           = ($emu_posted && isset($_POST['end-date'])) ? sanitize_text_field(wp_unslash($_POST['end-date'])) : '';

$number_of_items    = ($emu_posted && isset($_POST['number-of-items'])) ? sanitize_text_field(wp_unslash($_POST['number-of-items'])) : 'all';
$starting_point     = ($emu_posted && isset($_POST['starting-point'])) ? absint(wp_unslash($_POST['starting-point'])) : '';
$ending_point       = ($emu_posted && isset($_POST['ending-point'])) ? absint(wp_unslash($_POST['ending-point'])) : '';

$default_name = 'export-media-urls-' . wp_generate_password(20, false);
$posted_name = ($emu_posted && isset($_POST['csv-file-name'])) ? sanitize_file_name(wp_unslash($_POST['csv-file-name'])) : '';
$csv_name = ('' !== $posted_name) ? $posted_name : $default_name;

/* ---------------------------------------------------------------------- */
/* Form option sources                                                    */
/* ---------------------------------------------------------------------- */

$media_types = array(
    'all'      => __('All Media', 'export-media-urls'),
    'image'    => __('Images', 'export-media-urls'),
    'video'    => __('Video', 'export-media-urls'),
    'audio'    => __('Audio', 'export-media-urls'),
    'document' => __('Documents', 'export-media-urls'),
    'archive'  => __('Archives', 'export-media-urls'),
);

$attachment_statuses = array(
    'all'        => __('All', 'export-media-urls'),
    'attached'   => __('Attached (used by a post)', 'export-media-urls'),
    'unattached' => __('Unattached (orphaned)', 'export-media-urls'),
);

$export_formats = array(
    'csv'  => __('CSV File', 'export-media-urls'),
    'json' => __('JSON File', 'export-media-urls'),
);

$users_list = array('all' => __('All', 'export-media-urls'));
foreach (get_users() as $user) {
    $users_list[$user->ID] = $user->user_login;
}

/* Registry-driven field groups. */
$groups = $emu_fields->groups();
$fields = $emu_fields->fields();
$presets = $emu_fields->presets();

$fields_by_group = array();
$group_has_selected = array();
foreach ($fields as $key => $def) {
    $fields_by_group[$def['group']][$key] = $def;
    if (in_array($key, $selected_fields, true)) {
        $group_has_selected[$def['group']] = true;
    }
}

/* Collapsible section state — keep open whatever the user was working with. */
$show_filters  = ('all' !== $selected_user && '' !== $selected_user) || 'range' === $selected_range || 'all' !== $selected_attach;
$show_advanced = ('range' === $number_of_items);
$show_range    = ('range' === $number_of_items);
$show_dates    = ('range' === $selected_range);

$filter_label   = $show_filters ? __('Hide Filter Options', 'export-media-urls') : __('Show Filter Options', 'export-media-urls');
$filter_onclick = $show_filters ? 'lessFilterOptions()' : 'moreFilterOptions()';
$advanced_label   = $show_advanced ? __('Hide Advanced Options', 'export-media-urls') : __('Show Advanced Options', 'export-media-urls');
$advanced_onclick = $show_advanced ? 'hideAdvanceOptions()' : 'showAdvanceOptions()';

$admin_post_url = admin_url('admin-post.php');
?>

<div class="wrap">

    <?php include plugin_dir_path(__FILE__) . 'tab-nav.php'; ?>

    <p class="emu-subtitle"><?php esc_html_e('Export every Media Library URL as CSV or JSON, or view them right here.', 'export-media-urls'); ?></p>
    <hr class="emu-header-divider" />

    <div class="emuWrapper">
        <div id="emuMainContainer" class="postbox emucolumns">
            <div class="inside">

                <form id="infoForm" method="post" action="">

                    <table class="form-table">

                        <tr>
                            <th><?php esc_html_e('Export Fields:', 'export-media-urls'); ?></th>
                            <td>
                                <div class="emu-presets">
                                    <span class="emu-presets-label"><?php esc_html_e('Presets:', 'export-media-urls'); ?></span>
                                    <?php foreach ($presets as $preset) : ?>
                                        <button type="button" class="button button-secondary emu-preset" data-emu-preset="<?php echo esc_attr(implode(',', $preset['fields'])); ?>"><?php echo esc_html($preset['label']); ?></button>
                                    <?php endforeach; ?>
                                </div>

                                <?php foreach ($groups as $group_key => $group) : ?>
                                    <?php
                                    if (empty($fields_by_group[$group_key])) {
                                        continue;
                                    }
                                    $open = empty($group['collapsed']) || !empty($group_has_selected[$group_key]);
                                    ?>
                                    <details class="emu-field-group" <?php echo $open ? 'open' : ''; ?>>
                                        <summary><?php echo esc_html($group['label']); ?></summary>
                                        <div class="emu-group-tools">
                                            <a href="#" class="emu-group-all" data-emu-group="<?php echo esc_attr($group_key); ?>"><?php esc_html_e('Select all', 'export-media-urls'); ?></a>
                                            &nbsp;|&nbsp;
                                            <a href="#" class="emu-group-none" data-emu-group="<?php echo esc_attr($group_key); ?>"><?php esc_html_e('None', 'export-media-urls'); ?></a>
                                        </div>
                                        <div class="emu-field-grid">
                                            <?php foreach ($fields_by_group[$group_key] as $key => $def) : ?>
                                                <label><input type="checkbox" name="export_fields[]" value="<?php echo esc_attr($key); ?>" data-emu-group="<?php echo esc_attr($group_key); ?>" <?php checked(in_array($key, $selected_fields, true)); ?>> <?php echo esc_html($def['label']); ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr>
                            <th><?php esc_html_e('Media Type:', 'export-media-urls'); ?></th>
                            <td>
                                <?php foreach ($media_types as $value => $label) : ?>
                                    <label><input type="radio" name="media-type" value="<?php echo esc_attr($value); ?>" required="required" <?php checked($value, $selected_media); ?>> <?php echo esc_html($label); ?></label><br />
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr>
                            <th></th>
                            <td><a href="#" id="moreFilterOptionsLabel" onclick="<?php echo esc_attr($filter_onclick); ?>; return false;"><?php echo esc_html($filter_label); ?></a></td>
                        </tr>

                        <tr class="filter-options" style="display: <?php echo $show_filters ? 'table-row' : 'none'; ?>">
                            <th><?php esc_html_e('Attachment Status:', 'export-media-urls'); ?></th>
                            <td>
                                <?php foreach ($attachment_statuses as $value => $label) : ?>
                                    <label><input type="radio" name="attachment-status" value="<?php echo esc_attr($value); ?>" <?php checked($value, $selected_attach); ?>> <?php echo esc_html($label); ?></label><br />
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr class="filter-options" style="display: <?php echo $show_filters ? 'table-row' : 'none'; ?>">
                            <th><?php esc_html_e('By Author:', 'export-media-urls'); ?></th>
                            <td>
                                <?php foreach ($users_list as $value => $label) : ?>
                                    <label><input type="radio" name="post-author" value="<?php echo esc_attr($value); ?>" required="required" <?php checked($value, $selected_user); ?>> <?php echo esc_html($label); ?></label><br />
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr class="filter-options" style="display: <?php echo $show_filters ? 'table-row' : 'none'; ?>">
                            <th><?php esc_html_e('Date Range:', 'export-media-urls'); ?></th>
                            <td>
                                <label><input type="radio" name="date-range" value="all" <?php checked($selected_range, 'all'); ?> onclick="hideDateFields()" /> <?php esc_html_e('All', 'export-media-urls'); ?></label><br />
                                <label><input type="radio" name="date-range" value="range" <?php checked($selected_range, 'range'); ?> onclick="showDateFields()" /> <?php esc_html_e('Between Dates', 'export-media-urls'); ?></label><br />
                                <div id="dateRange" style="display: <?php echo $show_dates ? 'block' : 'none'; ?>">
                                    <?php esc_html_e('From:', 'export-media-urls'); ?> <input type="date" name="start-date" value="<?php echo esc_attr($start_date); ?>" />
                                    <?php esc_html_e('To:', 'export-media-urls'); ?> <input type="date" name="end-date" value="<?php echo esc_attr($end_date); ?>" />
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th></th>
                            <td><a href="#" id="advanceOptionsLabel" onclick="<?php echo esc_attr($advanced_onclick); ?>; return false;"><?php echo esc_html($advanced_label); ?></a></td>
                        </tr>

                        <tr class="advance-options" style="display: <?php echo $show_advanced ? 'table-row' : 'none'; ?>">
                            <th><?php esc_html_e('Number of Items:', 'export-media-urls'); ?> <a href="#" title="<?php echo esc_attr__('Specify a range to extract. Useful in case of a Memory Out error on large libraries!', 'export-media-urls'); ?>" onclick="return false">?</a></th>
                            <td>
                                <label><input type="radio" name="number-of-items" value="all" required="required" onclick="hideRangeFields()" <?php checked($number_of_items, 'all'); ?> /> <?php esc_html_e('All', 'export-media-urls'); ?></label><br />
                                <label><input type="radio" name="number-of-items" value="range" required="required" onclick="showRangeFields()" <?php checked($number_of_items, 'range'); ?> /> <?php esc_html_e('Specify Range', 'export-media-urls'); ?></label><br />
                                <div id="postRange" style="display: <?php echo $show_range ? 'block' : 'none'; ?>">
                                    <?php esc_html_e('From:', 'export-media-urls'); ?> <input type="number" name="starting-point" placeholder="0" value="<?php echo esc_attr($starting_point); ?>">
                                    <?php esc_html_e('To:', 'export-media-urls'); ?> <input type="number" name="ending-point" placeholder="500" value="<?php echo esc_attr($ending_point); ?>">
                                </div>
                            </td>
                        </tr>

                        <tr class="advance-options" style="display: <?php echo $show_advanced ? 'table-row' : 'none'; ?>">
                            <th><?php esc_html_e('Download File Name:', 'export-media-urls'); ?></th>
                            <td>
                                <label><input type="text" name="csv-file-name" value="<?php echo esc_attr($csv_name); ?>" size="40" /></label>
                            </td>
                        </tr>

                        <tr>
                            <th><?php esc_html_e('Download Format:', 'export-media-urls'); ?></th>
                            <td>
                                <?php foreach ($export_formats as $value => $label) : ?>
                                    <label><input type="radio" name="export-type" value="<?php echo esc_attr($value); ?>" <?php checked($value, $selected_format); ?>> <?php echo esc_html($label); ?></label><br />
                                <?php endforeach; ?>
                            </td>
                        </tr>

                        <tr>
                            <td></td>
                            <td class="emu-actions">
                                <button type="submit" name="export" class="button button-primary button-hero" formaction="<?php echo esc_url($admin_post_url); ?>"><?php esc_html_e('Download', 'export-media-urls'); ?></button>
                                <button type="submit" name="display" class="button button-secondary button-hero"><?php esc_html_e('Display Here', 'export-media-urls'); ?></button>
                            </td>
                        </tr>

                    </table>

                    <?php wp_nonce_field(Constants::EXPORT_NONCE_ACTION); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(Constants::EXPORT_ACTION); ?>">
                    <input type="hidden" name="form_submitted" value="1">

                </form>

            </div>
        </div>

        <div id="emuSideContainer" class="emucolumns">
            <div class="postbox">
                <h3><?php esc_html_e('Want to Support?', 'export-media-urls'); ?></h3>
                <div class="inside">
                    <p><?php esc_html_e('If you enjoyed the plugin, and want to support:', 'export-media-urls'); ?></p>
                    <ul>
                        <li><a href="https://AtlasGondal.com/contact-me/?utm_source=self&utm_medium=wp&utm_campaign=export-media-urls&utm_term=hire-me" target="_blank"><?php esc_html_e('Hire me', 'export-media-urls'); ?></a> <?php esc_html_e('on a project', 'export-media-urls'); ?></li>
                        <li><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YWT3BFURG6SGS&source=url" target="_blank"><?php esc_html_e('Buy me a Coffee', 'export-media-urls'); ?></a></li>
                    </ul>
                    <hr>
                    <h3><?php esc_html_e('Wanna say Thanks?', 'export-media-urls'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Leave', 'export-media-urls'); ?> <a href="https://wordpress.org/support/plugin/export-media-urls/reviews/" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a> <?php esc_html_e('rating', 'export-media-urls'); ?></li>
                        <li><?php esc_html_e('Follow me on X:', 'export-media-urls'); ?> <a href="https://x.com/atlas_gondal" target="_blank">@Atlas_Gondal</a></li>
                    </ul>
                    <hr>
                    <h3><?php esc_html_e('Got a Problem?', 'export-media-urls'); ?></h3>
                    <p><?php esc_html_e('Want to report a bug or suggest a feature? You can:', 'export-media-urls'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Create', 'export-media-urls'); ?> <a href="https://wordpress.org/support/plugin/export-media-urls/" target="_blank"><?php esc_html_e('Support Ticket', 'export-media-urls'); ?></a></li>
                        <li><?php esc_html_e('Write me an', 'export-media-urls'); ?> <a href="https://AtlasGondal.com/contact-me/?utm_source=self&utm_medium=wp&utm_campaign=export-media-urls&utm_term=write-an-email" target="_blank"><?php esc_html_e('Email', 'export-media-urls'); ?></a></li>
                    </ul>
                    <hr>
                    <h4 id="emuDevelopedBy"><?php esc_html_e('Developed by:', 'export-media-urls'); ?> <a href="https://AtlasGondal.com/?utm_source=self&utm_medium=wp&utm_campaign=export-media-urls&utm_term=developed-by" target="_blank">Atlas Gondal</a></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
/* ---------------------------------------------------------------------- */
/* In-page handler: "Display Here" only. CSV/JSON downloads are routed to  */
/* admin-post.php (streamed) by the Download button's formaction.          */
/* ---------------------------------------------------------------------- */
if (isset($_POST['display'])) {

    if (!$emu_nonce_ok) {
        echo "<div class='notice notice-error' style='width: 93%'>" . esc_html__('Security token validation failed!', 'export-media-urls') . '</div>';
        return;
    }

    $options = EMU_Request::from_post();
    if (null === $options) {
        echo "<div class='notice notice-error' style='width: 93%'>" . esc_html__('Security token validation failed!', 'export-media-urls') . '</div>';
        return;
    }

    $options['export_type'] = 'here';

    $valid = EMU_Request::validate($options);
    if ($valid !== true) {
        echo "<div class='notice notice-error' style='width: 93%'>" . wp_kses_post($valid) . '</div>';
        return;
    }

    update_user_meta(get_current_user_id(), Constants::LAST_FIELDS_META, $options['export_fields']);

    $emu_functions->render_here($options);
}
