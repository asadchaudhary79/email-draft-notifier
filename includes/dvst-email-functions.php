<?php

// Exit if accessed directly
defined('ABSPATH') || exit;


register_activation_hook(__FILE__, 'Dvst_draft_notifier_cron_activation');
add_action('Dvst_draft_notifier_event', 'Dvst_draft_notifier_send_email');

register_deactivation_hook(__FILE__, 'Dvst_draft_notifier_cron_deactivation');

function Dvst_draft_notifier_frequency_callback()
{
    $frequency = get_option('Dvst_draft_notifier_frequency', 7);
    printf(
        '<input type="number" name="Dvst_draft_notifier_frequency" value="%s" /> (Days)',
        esc_attr($frequency)
    );
}

function Dvst_draft_notifier_email_option_callback()
{
    $option = get_option('Dvst_draft_notifier_email_option', 'admin');
    $options = array(
        'admin' => __('Site Admin', 'Dvst-email-notifier'),
        'author' => __('Post Author Only', 'Dvst-email-notifier'),
        'both' => __('Both', 'Dvst-email-notifier')
    );
    echo '<select name="Dvst_draft_notifier_email_option">';
    foreach ($options as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($option, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

function Dvst_draft_notifier_email_cc_callback()
{
    $email_cc = get_option('Dvst_draft_notifier_email_cc', '');
    echo '<input type="email" name="Dvst_draft_notifier_email_cc" value="' . esc_attr($email_cc) . '" placeholder="example@example.com" />';
}


// Cron jobs for sending email notifications

function Dvst_draft_notifier_cron_activation()
{
    if (!wp_next_scheduled('Dvst_draft_notifier_event')) {
        wp_schedule_event(time(), 'daily', 'Dvst_draft_notifier_event');
    }
}


add_action('init', 'Dvst_draft_notifier_cron_deactivation');

function Dvst_draft_notifier_cron_deactivation()
{
    wp_clear_scheduled_hook('Dvst_draft_notifier_event');
}

function Dvst_draft_notifier_get_old_drafts()
{
    $frequency = get_option('Dvst_draft_notifier_frequency', 7);
    $date_threshold = date('Y-m-d', strtotime("-{$frequency} days"));

    // Get all public post types
    $post_types = get_post_types(array('public' => true), 'names');

    // Exclude 'attachment' from the post types as it's not relevant here
    unset($post_types['attachment']);

    $args = array(
        'post_type' => $post_types,
        'post_status' => 'draft',
        'date_query' => array(
            array(
                'before' => $date_threshold
            )
        ),
        'posts_per_page' => -1
    );

    return get_posts($args);
}


function Dvst_draft_notifier_send_email()
{
    $drafts = Dvst_draft_notifier_get_old_drafts();

    if (empty($drafts)) return;

    $subject = esc_html__('Reminder: Unpublished Drafts', 'Dvst-email-notifier');

    // Fetch the custom template or use a default message if no custom template is set
    $template = get_option('Dvst_draft_notifier_email_template');
    if (!$template) {
        $template = esc_html__('You have drafts that have been unpublished for a while.<br> Please review the following drafts: [drafts_list]', 'Dvst-email-notifier');
    }
    $template = nl2br(esc_html($template));
    // Replace global tags
    $template = str_replace('[site_name]', esc_html(get_bloginfo('name')), $template);
    $template = str_replace('[drafts_count]', esc_html(count($drafts)), $template);

    // Construct the list of drafts in an unordered list
    $drafts_list = "<ul>";
    foreach ($drafts as $draft) {
        $edit_link = esc_url(admin_url("post.php?post={$draft->ID}&action=edit"));
        $author_name = esc_html(get_the_author_meta('display_name', $draft->post_author));
        $draft_date = esc_html(get_the_date('F j, Y', $draft));

        $draft_info = "<a href='{$edit_link}'>" . esc_html($draft->post_title) . "</a> (" . esc_html__('Author', 'Dvst-email-notifier') . ": {$author_name} - " . esc_html__('Date', 'Dvst-email-notifier') . ": {$draft_date})";
        $drafts_list .= "<li>{$draft_info}</li>";
    }
    $drafts_list .= "</ul>";

    // Replace the [drafts_list] tag in the template with the actual drafts list
    $message = str_replace('[drafts_list]', $drafts_list, $template);

    $option = get_option('Dvst_draft_notifier_email_option', 'admin');
    $admin_email = esc_html(get_option('admin_email'));
    $headers = array('Content-Type: text/html; charset=UTF-8');

    $email_cc = esc_html(get_option('Dvst_draft_notifier_email_cc', ''));
    if (!empty($email_cc)) {
        $headers[] = 'Cc: ' . $email_cc;
    }

    $author_emails = [];
    foreach ($drafts as $draft) {
        $author_email = esc_html(get_the_author_meta('user_email', $draft->post_author));
        $author_emails[$author_email] = true;  // Using an associative array to keep unique emails
    }

    switch ($option) {
        case 'admin':
            wp_mail($admin_email, $subject, $message, $headers);
            break;

        case 'author':
            foreach (array_keys($author_emails) as $author_email) {
                wp_mail($author_email, $subject, $message, $headers);
            }
            break;

        case 'both':
            // Send to admin
            wp_mail($admin_email, $subject, $message, $headers);

            // Remove admin email from the list of author emails to prevent duplicates
            unset($author_emails[$admin_email]);

            // Send to unique authors
            foreach (array_keys($author_emails) as $author_email) {
                wp_mail($author_email, $subject, $message, $headers);
            }
            break;
    }
}
