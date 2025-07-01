<?php
/**
 * Plugin Name: Twilio SMS Notifications
 * Description: Send SMS updates to customers based on WooCommerce order status using Twilio.
 * Version: 1.0
 * Author: Abdullah Qureshi
 */

if (!defined('ABSPATH')) exit;

// Load Twilio SDK
require_once plugin_dir_path(__FILE__) . 'twilio-sdk/Twilio/autoload.php';
use Twilio\Rest\Client;

// Add admin menu
add_action('admin_menu', function () {
    add_menu_page('Twilio SMS', 'Twilio SMS', 'manage_options', 'twilio-sms-settings', 'twilio_sms_settings_page');
});

// Register settings
add_action('admin_init', function () {
    register_setting('twilio_sms_options', 'twilio_sms_sid');
    register_setting('twilio_sms_options', 'twilio_sms_token');
    register_setting('twilio_sms_options', 'twilio_sms_from');
    register_setting('twilio_sms_options', 'twilio_sms_templates');
});

function twilio_sms_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Twilio SMS Settings</h1>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('twilio_sms_options');
            do_settings_sections('twilio_sms_options');
            ?>
            <table class="form-table">
                <tr><th scope="row">Twilio SID</th><td><input type="text" name="twilio_sms_sid" value="<?php echo esc_attr(get_option('twilio_sms_sid')); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row">Twilio Auth Token</th><td><input type="text" name="twilio_sms_token" value="<?php echo esc_attr(get_option('twilio_sms_token')); ?>" class="regular-text" /></td></tr>
                <tr><th scope="row">Sender Number</th><td><input type="text" name="twilio_sms_from" value="<?php echo esc_attr(get_option('twilio_sms_from')); ?>" class="regular-text" /></td></tr>
            </table>
           <h2>Order Status SMS Templates</h2>
<p><strong>Use these placeholders in your message:</strong>  
<br><code>{customer_name}</code>, <code>{order_id}</code>, <code>{order_total}</code>, <code>{site_name}</code>
</p>

            <?php
            $statuses = wc_get_order_statuses();
            $templates = get_option('twilio_sms_templates', []);
            foreach ($statuses as $slug => $label) {
                echo '<p><strong>' . esc_html($label) . '</strong><br />';
                echo '<textarea name="twilio_sms_templates[' . esc_attr($slug) . ']" rows="2" cols="80">' . esc_textarea($templates[$slug] ?? '') . '</textarea></p>';
            }
            ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Hook into all status changes
add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status) {
    $templates = get_option('twilio_sms_templates', []);
    $template = $templates['wc-' . $new_status] ?? '';
    if (!$template) return;

    $sid = get_option('twilio_sms_sid');
    $token = get_option('twilio_sms_token');
    $from = get_option('twilio_sms_from');

    $client = new Client($sid, $token);

    $order = wc_get_order($order_id);
    $to = $order->get_billing_phone();

    $replacements = [
        '{customer_name}' => $order->get_billing_first_name(),
        '{order_id}' => $order->get_id(),
        '{order_total}' => $order->get_total(),
        '{site_name}' => get_bloginfo('name')
    ];

    $body = str_replace(array_keys($replacements), array_values($replacements), $template);

    try {
        $client->messages->create($to, [
            'from' => $from,
            'body' => $body
        ]);
    } catch (Exception $e) {
        error_log('Twilio SMS error: ' . $e->getMessage());
    }
}, 10, 3);
