<?php

/**
 * Plugin Name: Email Draft Notifier
 * Description: Sends email notifications for drafts that have been unpublished for a while. You can customize the email template and choose to send notifications to the site admin, post authors, or both.
 * Version: 1.0.0
 * Author: Muhammad Asad Mushtaq
 * Author URI: https://devstitch.com
 * License: GPL-2.0+
 * Text Domain: Dvst-email-notifier
 * Domain Path: /languages/
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// Include the email handling and cron job functionalities
require_once plugin_dir_path(__FILE__) . 'includes/dvst-email-functions.php';
// Admin menu for the plugin settings
add_action('admin_menu', 'Dvst_draft_notifier_admin_menu');
function Dvst_draft_notifier_admin_menu()
{
    add_submenu_page(
        'options-general.php',
        esc_html__('Email Draft Notifier', 'Dvst-email-notifier'),
        esc_html__('Email Draft Notifier', 'Dvst-email-notifier'),
        'manage_options',
        'email-notifier',
        'Dvst_draft_notifier_settings_page'
    );
}

function Dvst_draft_notifier_add_settings_link($links)
{
    $settings_link = '<a href="admin.php?page=Dvst-email-notifier">' . esc_html__('Settings', 'Dvst-email-notifier') . '</a>';
    array_unshift($links, $settings_link); // Add the settings link to the beginning of the array
    return $links;
}
$plugin_basename = plugin_basename(__FILE__);
add_filter('plugin_action_links_' . $plugin_basename, 'Dvst_draft_notifier_add_settings_link');

function Dvst_draft_notifier_settings_page()
{
?>
    <div class="wrap">
        <h2><?php esc_html_e('Dvst Draft Notifier Settings', 'Dvst-email-notifier'); ?></h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('Dvst-email-notifier');
            do_settings_sections('Dvst-email-notifier');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings for reminder frequency
add_action('admin_init', 'Dvst_draft_notifier_register_settings');
function Dvst_draft_notifier_register_settings()
{
    register_setting('Dvst-email-notifier', 'Dvst_draft_notifier_frequency');
    register_setting('Dvst-email-notifier', 'Dvst_draft_notifier_email_option');
    register_setting('Dvst-email-notifier', 'Dvst_draft_notifier_email_cc');
    register_setting('Dvst-email-notifier', 'Dvst_draft_notifier_email_template', 'wp_kses_post');
    register_setting('Dvst-email-notifier', 'Dvst_draft_notifier_remove_cron');

    add_settings_section(
        'Dvst_draft_notifier_settings_section',
        esc_html__('Draft Notifier Settings', 'Dvst-email-notifier'),
        null,
        'Dvst-email-notifier'
    );

    add_settings_field(
        'Dvst_draft_notifier_frequency',
        esc_html__('Notify for Drafts Older Than', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_frequency_callback',
        'Dvst-email-notifier',
        'Dvst_draft_notifier_settings_section'
    );

    add_settings_field(
        'Dvst_draft_notifier_email_option',
        esc_html__('Email Recipient Option', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_email_option_callback',
        'Dvst-email-notifier',
        'Dvst_draft_notifier_settings_section'
    );

    add_settings_field(
        'Dvst_draft_notifier_email_cc',
        esc_html__('Additional Email CC', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_email_cc_callback',
        'Dvst-email-notifier',
        'Dvst_draft_notifier_settings_section'
    );
    add_settings_section(
        'Dvst_custom_email_section',
        esc_html__('Customize Email Template', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_custom_email_description',
        'Dvst-email-notifier'
    );
    add_settings_section(
        'Dvst_draft_notifier_deactivation_section',
        esc_html__('Plugin Deactivation Settings', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_deactivation_section_description',
        'Dvst-email-notifier'
    );



    function Dvst_draft_notifier_custom_email_description()
    {
        esc_html_e('Customize the body of the email sent as a reminder. You can use the following placeholders:', 'Dvst-email-notifier');
        echo '<ul>';
        echo '<li><code>[drafts_list]</code> - ' . esc_html__('For where the list of drafts will appear.', 'Dvst-email-notifier') . '</li>';
        echo '<li><code>[site_name]</code> - ' . esc_html__('For the name of the site.', 'Dvst-email-notifier') . '</li>';
        echo '<li><code>[drafts_count]</code> - ' . esc_html__('For the count of drafts.', 'Dvst-email-notifier') . '</li>';
        echo '</ul>';
    }

    add_settings_field(
        'Dvst_draft_notifier_email_template',
        esc_html__('Email Template', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_email_template_callback',
        'Dvst-email-notifier',
        'Dvst_custom_email_section'
    );
    add_settings_field(
        'Dvst_draft_notifier_remove_cron',
        esc_html__('Remove scheduled tasks upon deactivation?', 'Dvst-email-notifier'),
        'Dvst_draft_notifier_remove_cron_render',
        'Dvst-email-notifier',
        'Dvst_draft_notifier_deactivation_section'
    );

    function Dvst_draft_notifier_deactivation_section_description()
    {
        esc_html_e('Settings related to actions taken upon plugin deactivation.', 'Dvst-email-notifier');
    }



    function Dvst_draft_notifier_remove_cron_render()
    {
        $remove_cron = get_option('Dvst_draft_notifier_remove_cron');
    ?>
        <input type='checkbox' name='Dvst_draft_notifier_remove_cron' <?php checked($remove_cron, 1); ?> value='1'>
        <p class="description">
            By checking this option, any scheduled tasks related to the Draft Notifier (like automated draft reminders) will be removed when you deactivate the plugin. If you plan to reactivate the plugin in the future and want the tasks to continue without reconfiguration, leave this option unchecked.
        </p>
<?php
    }
    register_deactivation_hook(__FILE__, 'Dvst_draft_notifier_deactivate');
    function Dvst_draft_notifier_deactivate()
    {
        if (get_option('Dvst_draft_notifier_remove_cron')) {
            wp_clear_scheduled_hook('Dvst_draft_notifier_cron_hook');
        }
    }


    function dvst_draft_notifier_email_template_callback()
    {
        $default_template = esc_html__("Hello,\n\nYou have [drafts_count] drafts that have been unpublished for a while on [site_name].\n\nPlease review the following drafts:\n\n[drafts_list]\n\nRegards,\nThe [site_name] Team", 'dvst-email-notifier');
        $value = get_option('dvst_draft_notifier_email_template', $default_template);
        wp_editor($value, 'dvst_draft_notifier_email_template', ['textarea_rows' => 10, 'teeny' => true, 'media_buttons' => false]);
    }
}
