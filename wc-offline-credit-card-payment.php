<?php
/**
 * Plugin Name: Accept Card Offline
 * Plugin URI: https://github.com/callsimba
 * Description: WooCommerce payment gateway for accepting offline credit card payments
 * Version: 1.0.0
 * Author: Call Simba
 * Author URI: https://github.com/callsimba
 * Requires at least: 4.0
 *
 * Text Domain: accept_payment_offline
 * Domain Path: /languages/
 */

if (!defined('ABSPATH')) exit;

// Global Constants
define('ACCEPT_PAYMENT_OFFLINE_PATH', plugin_dir_path(__FILE__));
define('ACCEPT_PAYMENT_OFFLINE_URL', plugins_url('/', __FILE__));

// Load plugin textdomain
load_textdomain('accept_payment_offline', ACCEPT_PAYMENT_OFFLINE_PATH . 'languages/' . get_locale() . '.mo');

// Global options
$global_options = json_encode(array(
    'name' => __('Accept Card Offline', 'accept_payment_offline'),
    'slug' => 'accept_payment_offline',
    'domain' => 'accept_payment_offline',
    'version' => '1.0.0',
    'wc_version' => '2.6.8',
    'wp_version' => '4.6'
));

define('ACCEPT_PAYMENT_OFFLINE_OPTIONS', $global_options);

if (!class_exists('wc_variations_layouts')):

    // Check if WooCommerce is active
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (is_plugin_active('woocommerce/woocommerce.php')):
        require_once(ACCEPT_PAYMENT_OFFLINE_PATH . 'encryption-manager.php');

        class AcceptCardOffline_Payment
        {
            public $version = '1.0';
            protected static $_instance = null;

            function __construct()
            {
                add_action('plugins_loaded', array($this, 'init_gateway'));
                add_filter('woocommerce_payment_gateways', array($this, 'register_gateway'));
                add_action('add_meta_boxes', array($this, 'accept_payment_offline_metabox'));
                add_action('wp_ajax_accept_payment_offline_decrypt_card_data', array($this, 'accept_payment_offline_decrypt_card_data'));
                add_action('wp_ajax_accept_payment_offline_delete_credit_card', array($this, 'accept_payment_offline_delete_credit_card'));

                // Add decryption password field to user profile
                add_action('show_user_profile', array($this, 'user_decrypt_pwd_field'));
                add_action('personal_options_update', array($this, 'save_user_decrypt_pwd_field'));

                // Add setting links
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'accept_payment_offline_setting_link'));
            }

            // Ensure only one instance of the plugin is loaded or can be loaded.
            public static function instance()
            {
                if (is_null(self::$_instance)) {
                    self::$_instance = new self();
                }
                return self::$_instance;
            }

            // Prevent cloning
            public function __clone()
            {
                _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'accept_payment_offline'), '1.0');
            }

            // Prevent unserializing
            public function __wakeup()
            {
                _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'accept_payment_offline'), '1.0');
            }

            // Initialize the payment gateway
            public function init_gateway()
            {
                if (class_exists('AcceptCardOffline_Payment_Gateway_Init')) return;

                // Load the gateway class
                require_once(ACCEPT_PAYMENT_OFFLINE_PATH . 'class-offline-credit-card-payment.php');
            }

            // Register the payment gateway with WooCommerce
            public function register_gateway($methods)
            {
                $methods[] = 'AcceptCardOffline_Payment_Gateway_Init';
                return $methods;
            }

            // Add a metabox to display credit card details in the order admin page
            public function accept_payment_offline_metabox()
            {
                global $post;
                if ($post->post_type == "shop_order") {
                    $order = new WC_Order($post->ID);
                    // Add the metabox only if the order was paid using Offline Credit Card Method
                    if ('accept_payment_offline_payment_method' == get_post_meta($order->get_id(), '_payment_method', true)) {
                        add_meta_box(
                            'accept_payment_offline-credit-card-details',
                            __('Accept Card Offline Details', 'accept_payment_offline'),
                            array($this, 'get_accept_payment_offline_details'),
                            'shop_order'
                        );
                    }
                }
            }

            // Add setting links to the plugin page
            public function accept_payment_offline_setting_link($links)
            {
                $setting_links = array(
                    '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=accept_payment_offline_payment_method') . '">' . __('Setting', 'accept_payment_offline') . '</a>',
                    '<a href="' . admin_url('profile.php') . '">' . __('Password Setting', 'accept_payment_offline') . '</a>',
                );
                return array_merge($links, $setting_links);
            }

            // Display credit card details in the metabox
            public function get_accept_payment_offline_details($post)
            {
                $order = new WC_Order($post);
                if ($order) {
                    $accept_payment_offline_setting = get_option('woocommerce_accept_payment_offline_payment_method_settings', 'no');
                    $card_holder_name = $accept_payment_offline_setting['ch_text'] ?: 'Card Holder Name';
                    $card_number = $accept_payment_offline_setting['ca_nobmber'] ?: 'Card Number';
                    $card_type = $accept_payment_offline_setting['c_type'] ?: 'Card Type';
                    $expiry_date = $accept_payment_offline_setting['ce_date'] ?: 'Expiry (MM/YY)';
                    $cvv_no = $accept_payment_offline_setting['cc_label'] ?: 'Card Code';

                    ?>
                    <div id="wc-offline-card-details">
                        <span class="wc_valid_errors"></span>
                        <div class="wc_description_credit_card_form">
                            <input type="password" name="description_password" id="description_password" class="text-input"
                                   placeholder="<?php echo esc_attr__('Decryption Password', 'accept_payment_offline'); ?>"
                                   autocomplete="off">
                            <input type="button" name="decrypt_encrypted_data" id="decrypt_encrypted_data"
                                   class="button button-primary"
                                   value="<?php echo esc_attr__('Decrypt Credit Card Details', 'accept_payment_offline'); ?>"
                                   data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <input type="button" name="accept_payment_offline_delete_credit_card" id="accept_payment_offline_delete_credit_card"
                                   class="button button-primary"
                                   value="<?php echo esc_attr__('Delete credit card', 'accept_payment_offline'); ?>"
                                   data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                        </div>
                        <table class="widefat striped description_password_table">
                            <tr>
                                <td><strong><?php echo esc_html($card_holder_name); ?></strong></td>
                                <td>:</td>
                                <td class="wc_card_name"><?php echo esc_html(get_post_meta($order->get_id(), '_card_holder', true)); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html($card_number); ?></strong></td>
                                <td>:</td>
                                <td class="wc_card_no"><?php echo esc_html(get_post_meta($order->get_id(), '_card_no_plain', true)); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html($card_type); ?></strong></td>
                                <td>:</td>
                                <td class="wc_card_type"><?php echo esc_html(get_post_meta($order->get_id(), '_card_type', true)); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html($expiry_date); ?></strong></td>
                                <td>:</td>
                                <td class="wc_card_exp"><?php echo esc_html('**/**'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php echo esc_html($cvv_no); ?></strong></td>
                                <td>:</td>
                                <td class="wc_card_cvv"><?php echo esc_html('***'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <style type="text/css">
                        #accept_payment_offline-credit-card-details {
                            box-shadow: 0px 0px 5px 5px #ddd;
                        }

                        #wc-offline-card-details {
                            margin-bottom: 1em;
                        }

                        #wc-offline-card-details .errors {
                            color: red;
                        }

                        #wc-offline-card-details .wc_description_credit_card_form {
                            margin-bottom: 1em;
                        }

                        #accept_payment_offline-credit-card-details h2 {
                            background: #f9f9f9
                        }

                        #wc-offline-card-details .wc_description_credit_card_form .text-input {
                            padding: 6px 10px;
                            width: 30%;
                        }

                        #wc-offline-card-details .wc_description_credit_card_form .button {
                            height: auto !important;
                            padding: 2px 15px;
                        }

                        .description_password_table tr td:nth-child(1) {
                            width: 30%;
                        }

                        .description_password_table tr td:nth-child(2) {
                            width: 20%;
                        }

                        .description_password_table tr td {
                            color: #444 !important;
                        }

                        .wc_valid_errors {
                            color: red;
                        }
                    </style>
                    <script type="text/javascript">
                        (function ($) {
                            $(document).ready(function () {
                                // Decrypt data
                                $('#decrypt_encrypted_data').click(function () {
                                    $('.wc_valid_errors').html('');
                                    var req = {
                                        "action": "accept_payment_offline_decrypt_card_data",
                                        "order_id": $(this).data('order-id'),
                                        "pwd": $('#description_password').val(),
                                        "nonce": "<?php echo wp_create_nonce('decrypt_cc'); ?>"
                                    };

                                    $('#wc-offline-card-details').block({
                                        overlayCSS: {backgroundColor: '#fff', opacity: 0.5, cursor: 'wait'},
                                        css: {color: '#999', border: 'none', backgroundColor: 'transparent', fontSize: '22px', width: '35%'},
                                        message: ''
                                    });

                                    $.post(ajaxurl, req, function (res) {
                                        $('#wc-offline-card-details').unblock();
                                        if (res.success) {
                                            $('.wc_card_no').text(res.data.wc_card_no);
                                            $('.wc_card_exp').text(res.data.wc_card_exp);
                                            $('.wc_card_cvv').text(res.data.wc_card_cvc);
                                        } else {
                                            $('.wc_valid_errors').html(res.data.msg);
                                        }
                                    }, 'json');
                                });

                                // Delete Credit Card
                                $('#accept_payment_offline_delete_credit_card').click(function () {
                                    if (confirm('Are you sure you want to delete the credit card?')) {
                                        $('.wc_valid_errors').html('');
                                        var req = {
                                            "action": "accept_payment_offline_delete_credit_card",
                                            "order_id": $(this).data('order-id')
                                        };

                                        $('#wc-offline-card-details').block({
                                            overlayCSS: {backgroundColor: '#fff', opacity: 0.5, cursor: 'wait'},
                                            css: {color: '#999', border: 'none', backgroundColor: 'transparent', fontSize: '22px', width: '35%'},
                                            message: ''
                                        });

                                        $.post(ajaxurl, req, function (res) {
                                            $('#wc-offline-card-details').unblock();
                                            $('.wc_valid_errors').html(res.data.msg);
                                        }, 'json');
                                    }
                                });
                            });
                        })(jQuery);
                    </script>
                    <?php
                }
            }

            // Delete credit card data
            public function accept_payment_offline_delete_credit_card()
            {
                if (isset($_POST['order_id']) && $_POST['order_id'] != "") {
                    $order_id = wc_clean($_POST['order_id']);
                    update_post_meta($order_id, '_card_holder', '-----');
                    update_post_meta($order_id, '_card_number', '-----');
                    update_post_meta($order_id, '_card_type', '-----');
                    update_post_meta($order_id, '_card_expiry', '-----');
                    update_post_meta($order_id, '_card_cvc', '-----');
                    update_post_meta($order_id, '_card_no_plain', '-----');
                    wp_send_json_error(array('msg' => __('<span style="color:green">Credit card deleted!</span>', 'accept_payment_offline')));
                }
            }

            // Decrypt credit card data
            public function accept_payment_offline_decrypt_card_data()
            {
                if (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'decrypt_cc')) {
                    $order_id = wc_clean($_POST['order_id']);
                    $order = new WC_Order($order_id);
                    $pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';

                    $e = SecureEncryption::instance();

                    if (!$pwd) {
                        wp_send_json_error(array('msg' => __('Password is required.', 'accept_payment_offline')));
                    }

                    if (!$order) {
                        wp_send_json_error(array('msg' => __('Invalid order.', 'accept_payment_offline')));
                    }

                    // Check user password
                    $user_id = get_current_user_id();

                    if ($user_id == 0 || (!current_user_can('administrator') && !current_user_can('shop_manager'))) {
                        wp_send_json_error(array('msg' => __('You are not allowed to decrypt data.', 'accept_payment_offline')));
                    }

                    $user_decrypt_pwd = $e->accept_payment_offline_decrypt(get_user_meta($user_id, '_decrypt_pwd', true));

                    if ('' == $user_decrypt_pwd) {
                        wp_send_json_error(array('msg' => __('You have not set a decryption password. To decrypt data, you must set a password first from your profile.', 'accept_payment_offline')));
                    }

                    if ($pwd != $user_decrypt_pwd) {
                        wp_send_json_error(array('msg' => __('Incorrect password.', 'accept_payment_offline')));
                    }

                    // Decrypt credit card details
                    $cc = array();
                    $cc['wc_card_no'] = $e->accept_payment_offline_decrypt(get_post_meta($order->get_id(), '_card_number', true));
                    $cc['wc_card_exp'] = $e->accept_payment_offline_decrypt(get_post_meta($order->get_id(), '_card_expiry', true));
                    $cc['wc_card_cvc'] = $e->accept_payment_offline_decrypt(get_post_meta($order->get_id(), '_card_cvc', true));

                    $cc['wc_card_no'] = $cc['wc_card_no'] ?: '-----';
                    $cc['wc_card_exp'] = $cc['wc_card_exp'] ?: '-----';
                    $cc['wc_card_cvc'] = $cc['wc_card_cvc'] ?: '-----';

                    if (!empty($cc)) {
                        wp_send_json_success($cc);
                    } else {
                        wp_send_json_error(array('msg' => __('Error occurred while decrypting.', 'accept_payment_offline')));
                    }
                }

                wp_send_json_error(array('msg' => __('Invalid Request.', 'accept_payment_offline')));
            }

            // Add decryption password field to the user profile
            public function user_decrypt_pwd_field($user)
            {
                if (!is_super_admin() && !current_user_can('administrator') && !current_user_can('shop_manager')) {
                    return;
                }
                $d_pwd = get_user_meta($user->ID, '_decrypt_pwd', true);
                $e = SecureEncryption::instance();
                ?>
                <div style="background:#fff; border-radius:5px; padding:5px 15px; box-shadow:0px 0px 8px 3px #ddd;">
                    <h3 style="border-bottom: 1px solid #eee; margin-bottom: 0; padding-bottom: 15px;"><?php _e('Decryption Credit Card Details Authentication On Each Order', 'accept_payment_offline'); ?></h3>
                    <table class="form-table" style="margin-top:0;">
                        <tr>
                            <th><label for="_decrypt_pwd"><?php _e('Password', 'accept_payment_offline'); ?></label></th>
                            <td>
                                <input type="password" name="_decrypt_pwd" id="_decrypt_pwd"
                                       class="regular-text"
                                       value="<?php echo esc_attr($e->accept_payment_offline_decrypt($d_pwd)); ?>">
                                <a href="javascript:void(0)" class="button button-secondary show-hide"><?php _e('Show', 'accept_payment_offline'); ?></a><br>
                                <span class="description"><?php _e('This password will be used for decrypting credit card data on order.', 'accept_payment_offline'); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function ($) {
                        jQuery('.show-hide').click(function () {
                            var $this = $(this);
                            var input = $('#_decrypt_pwd');
                            if ($this.hasClass('shown')) {
                                input.attr('type', 'password');
                                $this.text('<?php _e('Show', 'accept_payment_offline'); ?>');
                                $this.removeClass('shown');
                            } else {
                                input.attr('type', 'text');
                                $this.text('<?php _e('Hide', 'accept_payment_offline'); ?>');
                                $this.addClass('shown');
                            }
                        });
                    });
                </script>
                <?php
            }

            // Save decryption password from user profile
            public function save_user_decrypt_pwd_field($user_id)
            {
                if (!current_user_can('edit_user', $user_id)) return false;

                $e = SecureEncryption::instance();
                $encrypted_pwd = $e->accept_payment_offline_encrypt($_POST['_decrypt_pwd']);
                update_user_meta($user_id, '_decrypt_pwd', $encrypted_pwd);
            }
        }

        // Main plugin instance
        function accept_payment_offline()
        {
            return AcceptCardOffline_Payment::instance();
        }

        accept_payment_offline();
    else:
        // Add Admin Notice if WooCommerce is not activated
        function accept_payment_offline_gateway_admin_notice()
        {
            $wcvl_options = json_decode(ACCEPT_PAYMENT_OFFLINE_OPTIONS);
            $class = 'notice notice-error';
            $message = __('<b>' . esc_html($wcvl_options->name) . '</b> ' . __('requires WooCommerce to be installed & activated!', $wcvl_options->domain));

            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        }

        add_action('admin_notices', 'accept_payment_offline_gateway_admin_notice');
    endif;
endif;
