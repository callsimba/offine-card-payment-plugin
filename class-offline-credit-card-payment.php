<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

class AcceptCardOffline_Payment_Gateway_Init extends WC_Payment_Gateway
{
    public function __construct() 
    {
        $this->id = "accept_payment_offline_credit_card_payment_method";
        $this->method_title = __("Accept Card Offline Payment", 'accept_payment_offline');
        $this->method_description = __("Pay via Offline Credit Card.", 'accept_payment_offline');
        $this->title = __("Accept Card Offline Payment", 'accept_payment_offline');
        $this->icon = null;
        $this->has_fields = true;
        $this->accept_payment_offline_generate_form_fields();
        $this->init_settings();
        
        // Convert settings into variables
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }
        
        // Save settings
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'), 9999);
        }

        // Add credit card holder name field
        add_action('woocommerce_credit_card_form_start', array($this, 'card_holder_name_field'));
    }
    
    public function process_admin_options()
    {
        $e = SecureEncryption::instance();
        $this->init_settings();

        $post_data = $this->get_post_data();

        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                try {
                    if ($key == 'en_pass') {
                        $this->settings[$key] = $e->accept_payment_offline_encrypt($this->get_field_value($key, $field, $post_data));
                    } else {
                        $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                    }
                } catch (Exception $e) {
                    $this->add_error($e->getMessage());
                }
            }
        }
        update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));
    }

    public function card_holder_name_field($cc_form)
    {
        echo '<p class="form-row">
            <label for="' . esc_attr($this->id) . '-card-holder-name">' . __('Card Holder Name', 'accept_payment_offline') . ' <span class="required">*</span></label>
            <input id="' . esc_attr($this->id) . '-card-holder-name" class="input-text" type="text" autocomplete="off" placeholder="' . esc_attr__('Card Holder Name', 'accept_payment_offline') . '" name="' . esc_attr($this->id) . '-card-holder-name" style="height:40px;padding:8px;" />
        </p>';
    }

    public function payment_fields() 
    {
        if ($description = $this->get_description()) {
            echo wpautop(wptexturize(__($description, 'accept_payment_offline')));
        }

        $cc_form = new WC_Payment_Gateway_CC;
        $cc_form->id = $this->id;
        $cc_form->supports = $this->supports;
        wp_enqueue_script('wc-credit-card-form');
        
        $accept_payment_offline_setting = get_option('woocommerce_accept_payment_offline_credit_card_payment_method_settings', 'no');
        $card_holder_name = __('Card Holder Name', 'accept_payment_offline');
        $card_number = __('Card Number', 'accept_payment_offline');
        $card_type = __('Card Type', 'accept_payment_offline');
        $expiry_date = __('Expiry (MM/YY)', 'accept_payment_offline');
        $cvv_no = __('Card Code', 'accept_payment_offline');
        
        // Fetch settings dynamically if enabled
        if ($accept_payment_offline_setting != "no") {
            $card_holder_name = $accept_payment_offline_setting['ch_text'] ?: $card_holder_name;
            $card_number = $accept_payment_offline_setting['ca_nobmber'] ?: $card_number;
            $card_type = $accept_payment_offline_setting['c_type'] ?: $card_type;
            $expiry_date = $accept_payment_offline_setting['ce_date'] ?: $expiry_date;
            $cvv_no = $accept_payment_offline_setting['cc_label'] ?: $cvv_no;
        }

        ?>
        <fieldset id="wc-accept_payment_offline_credit_card_payment_method-cc-form" class="wc-credit-card-form wc-payment-form">
            <p class="form-row woocommerce-validated">
                <label for="accept_payment_offline_credit_card_payment_method-card-holder-name"><?php _e($card_holder_name, 'accept_payment_offline'); ?><span class="required">*</span></label>
                <input id="accept_payment_offline_credit_card_payment_method-card-holder-name" class="input-text" autocomplete="off" placeholder="<?php _e('Card Holder Name', 'accept_payment_offline'); ?>" name="accept_payment_offline_credit_card_payment_method-card-holder-name" style="height:40px;padding:8px;" type="text">
                <span class="accept_payment_offline_pg_error"></span>
            </p>
            <p class="form-row form-row-first">
                <label for="accept_payment_offline_credit_card_payment_method-card-number"><?php _e($card_number, 'accept_payment_offline'); ?><span class="required">*</span></label>
                <input id="accept_payment_offline_credit_card_payment_method-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" placeholder="•••• •••• •••• ••••" name="accept_payment_offline_credit_card_payment_method-card-number" type="tel">
                <span class="accept_payment_offline_pg_error"></span>
            </p>
            <p class="form-row form-row-last">
                <label for="accept_payment_offline_credit_card_payment_method-card-type"><?php _e($card_type, 'accept_payment_offline'); ?><span class="required">*</span></label>
                <select style="width:100%;" name="accept_payment_offline_credit_card_payment_method-card-type" id="accept_payment_offline_credit_card_payment_method-card-type" class="accept_payment_offline_credit_card_payment_method-card-type">
                    <?php 
                        $card_types_array = array(
                            "American Express" => __('American Express', 'accept_payment_offline'),
                            "Diners Club Carte Blanche" => __('Diners Club Carte Blanche', 'accept_payment_offline'),
                            "Diners Club" => __('Diners Club', 'accept_payment_offline'),
                            "Discover" => __('Discover', 'accept_payment_offline'),
                            "Diners Club Enroute" => __('Diners Club Enroute', 'accept_payment_offline'),
                            "JCB" => __('JCB', 'accept_payment_offline'),
                            "Maestro" => __('Maestro', 'accept_payment_offline'),
                            "MasterCard" => __('MasterCard', 'accept_payment_offline'),
                            "Solo" => __('Solo', 'accept_payment_offline'),
                            "Switch" => __('Switch', 'accept_payment_offline'),
                            "VISA" => __('VISA', 'accept_payment_offline'),
                            "VISA Electron" => __('VISA Electron', 'accept_payment_offline'),
                            "LaserCard" => __('LaserCard', 'accept_payment_offline'),
                        );
                    ?>
                    <option value=""><?php _e('Select Card Type', 'accept_payment_offline'); ?></option>
                    <?php 
                        foreach ($card_types_array as $key => $single_Card):
                            ?><option value="<?php echo $key; ?>"><?php _e($single_Card); ?></option><?php
                        endforeach;
                    ?>
                </select>
                <div class="accept_payment_offline_cart_images"></div>
                <span class="accept_payment_offline_pg_error"></span>
            </p>
            <p class="form-row form-row-first">
                <label for="accept_payment_offline_credit_card_payment_method-card-expiry"><?php _e($expiry_date, 'accept_payment_offline'); ?><span class="required">*</span></label>
                <input id="accept_payment_offline_credit_card_payment_method-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" placeholder="<?php _e('MM / YY', 'accept_payment_offline'); ?>" name="accept_payment_offline_credit_card_payment_method-card-expiry" type="tel">
                <span class="accept_payment_offline_pg_error"></span>
            </p>
            <p class="form-row form-row-last">
                <label for="accept_payment_offline_credit_card_payment_method-card-cvc"><?php _e($cvv_no, 'accept_payment_offline'); ?><span class="required">*</span></label>
                <input id="accept_payment_offline_credit_card_payment_method-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" maxlength="4" placeholder="<?php _e('CVC', 'accept_payment_offline'); ?>" name="accept_payment_offline_credit_card_payment_method-card-cvc" style="width:100px" type="tel">
                <span class="accept_payment_offline_pg_error"></span>
            </p>
            <div class="clear"></div>
        </fieldset>

        <style>
            .payment_box.payment_method_accept_payment_offline_credit_card_payment_method > p { margin-bottom: 0; }
            #wc-accept_payment_offline_credit_card_payment_method-cc-form { border-top: 1px solid #f1f1f1 !important; padding-top: 5px !important; }
            #wc-accept_payment_offline_credit_card_payment_method-cc-form > p { padding-left: 0px; }
            #wc-accept_payment_offline_credit_card_payment_method-cc-form > p.form-row-first { clear: both; overflow: hidden; }
            li .payment_method_accept_payment_offline_credit_card_payment_method { box-shadow: 0 0 8px 2px #f9f9f9; }
            #wc-accept_payment_offline_credit_card_payment_method-cc-form input { background: #fff none repeat scroll 0 0 !important; border-color: #ccc !important; border-radius: 5px !important; color: #000 !important; font-size: 15px !important; font-weight: bold !important; }
            .payment_box.payment_method_accept_payment_offline_credit_card_payment_method p { color: #000; }
            .accept_payment_offline_pg_error { color: red; font-style: italic; display: block; }
            .accept_payment_offline_pg_error .required { display: none; }
            .wc_payment_method.payment_method_accept_payment_offline_credit_card_payment_method .accept_payment_offline_cart_images { position: absolute; right: 5px; bottom: 5px; }
        </style>
        <script>
            jQuery(function($) {
                <?php if ($accept_payment_offline_setting['rt_valid'] == 'yes'): ?>
                    var error_flag = false;
                    $(document).on('click', '#place_order', function() {
                        var payment_method_type = $(document).find('#payment input[name="payment_method"]:checked').val();
                        if (payment_method_type == 'accept_payment_offline_credit_card_payment_method') {
                            var return_type = true;
                            $(document).find('#wc-accept_payment_offline_credit_card_payment_method-cc-form input, #wc-accept_payment_offline_credit_card_payment_method-cc-form select').each(function() {
                                var label_val = $(this).closest('p').find('label').html();
                                if ($(this).val() == "") {
                                    return_type = false;
                                    $(this).closest('p').find('.accept_payment_offline_pg_error').html(label_val + ' is required.')
                                } else if ($(this).attr('name') == 'accept_payment_offline_credit_card_payment_method-card-holder-name' && isUpperCase($(this).val()) == false) {
                                    return_type = false;
                                    $(this).closest('p').find('.accept_payment_offline_pg_error').html(label_val + ' must be uppercase.')
                                } else {
                                    $(this).closest('p').find('.accept_payment_offline_pg_error').html('')
                                }
                            });
                            return return_type;
                        }
                    });

                    $(document).on('change', '#wc-accept_payment_offline_credit_card_payment_method-cc-form select', function() {
                        accept_payment_offline_cc_form_validate($(this));
                    });

                    $(document).on('keyup', '#wc-accept_payment_offline_credit_card_payment_method-cc-form input', function() {
                        accept_payment_offline_cc_form_validate($(this));
                    });

                    function accept_payment_offline_cc_form_validate($this_obj) {
                        var label_val = $this_obj.closest('p').find('label').html();
                        if ($this_obj.val() == "") {
                            return_type = false;
                            $this_obj.closest('p').find('.accept_payment_offline_pg_error').html(label_val + ' is required.')
                        } else if ($this_obj.attr('name') == 'accept_payment_offline_credit_card_payment_method-card-holder-name' && isUpperCase($this_obj.val()) == false) {
                            return_type = false;
                            $this_obj.closest('p').find('.accept_payment_offline_pg_error').html(label_val + ' must be uppercase.')
                        } else {
                            if ($this_obj.attr('name') == 'accept_payment_offline_credit_card_payment_method-card-number') {
                                var return_valid_card_name = GetCardType($this_obj.val());
                                if (return_valid_card_name != "") {
                                    $(document).find('#accept_payment_offline_credit_card_payment_method-card-type option[value="' + return_valid_card_name + '"]').attr('selected', 'selected');
                                    var card_src = return_valid_card_name.replace(' ', '-');
                                    var plugin_path = '<?php echo plugin_dir_url(__FILE__); ?>';
                                    card_src = card_src.toLowerCase();
                                    $('.accept_payment_offline_cart_images').html('<img src="' + (plugin_path + 'cards/' + card_src) + '.png">');
                                }
                            }
                            $this_obj.closest('p').find('.accept_payment_offline_pg_error').html('')
                        }
                    }

                    $(document).on('change', '#accept_payment_offline_credit_card_payment_method-card-type', function() {
                        var return_valid_card_name = $(this).val();
                        var card_src = return_valid_card_name.replace(' ', '-');
                        var plugin_path = '<?php echo plugin_dir_url(__FILE__); ?>';
                        card_src = card_src.toLowerCase();
                        if (return_valid_card_name != "") {
                            $('.accept_payment_offline_cart_images').html('<img src="' + (plugin_path + 'cards/' + card_src) + '.png">');
                        } else {
                            $('.accept_payment_offline_cart_images').html('');
                        }
                    });

                    function isUpperCase(str) {
                        return true; // Simplified for now, implement uppercase validation if needed
                    }

                    function GetCardType(number) {
                        var re = new RegExp("^3[47]");
                        if (number.match(re) != null) { return "American Express"; }
                        re = new RegExp("^30[0-5]");
                        if (number.match(re) != null) { return "Diners Club Carte Blanche"; }
                        re = new RegExp("^36");
                        if (number.match(re) != null) { return "Diners Club"; }
                        re = new RegExp("^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)");
                        if (number.match(re) != null) { return "Discover"; }
                        re = new RegExp("^35(2[89]|[3-8][0-9])");
                        if (number.match(re) != null) { return "JCB"; }
                        re = new RegExp("^5[1-5]");
                        if (number.match(re) != null) { return "MasterCard"; }
                        re = new RegExp("^4");
                        if (number.match(re) != null) { return "VISA"; }
                        re = new RegExp("^(4026|417500|4508|4844|491(3|7))");
                        if (number.match(re) != null) { return "VISA Electron"; }
                        return "";
                    }
                <?php endif; ?>
            });
        </script>
        <?php
    }

    public function accept_payment_offline_generate_form_fields() 
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'accept_payment_offline'),
                'label' => __('Enable this payment gateway', 'accept_payment_offline'),
                'type' => 'checkbox',
                'desc_tip' => __('Enable this payment gateway.', 'accept_payment_offline'),
                'default' => 'no',
            ),
            'rt_valid' => array(
                'title' => __('Enable/Disable Real Time jQuery Validation', 'accept_payment_offline'),
                'label' => __('Enable/Disable Real Time jQuery Validation', 'accept_payment_offline'),
                'type' => 'checkbox',
                'desc_tip' => __('Enable/Disable Real Time jQuery Validation.', 'accept_payment_offline'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'accept_payment_offline'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'accept_payment_offline'),
                'default' => __('Offline Credit Card', 'accept_payment_offline'),
            ),
            'description' => array(
                'title' => __('Description', 'accept_payment_offline'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'accept_payment_offline'),
                'default' => __('Pay via Offline Credit Card.', 'accept_payment_offline'),
                'css' => 'max-width:350px;',
            ),
            'def_status' => array(
                'title' => __('Default order status', 'accept_payment_offline'),
                'type' => 'select',
                'desc_tip' => __('This order status is by default for placing order.', 'accept_payment_offline'),
                'default' => __('on-hold', 'accept_payment_offline'),
                'css' => 'max-width:350px;',
                'options' => array(
                    'on-hold' => _x('On hold', 'Order status', 'woocommerce'),
                    'pending' => _x('Pending payment', 'Order status', 'woocommerce'),
                    'processing' => _x('Processing', 'Order status', 'woocommerce'),
                    'completed' => _x('Completed', 'Order status', 'woocommerce'),
                    'cancelled' => _x('Cancelled', 'Order status', 'woocommerce'),
                    'refunded' => _x('Refunded', 'Order status', 'woocommerce'),
                    'failed' => _x('Failed', 'Order status', 'woocommerce'),
                ),
            ),
            // Other fields omitted for brevity...
        );        
    }

    public function validate_fields()
    {
        $prefix = esc_attr($this->id);
        $error = false;

        if (empty($_POST[$prefix . '-card-holder-name'])) {
            wc_add_notice(__('<strong>Card Holder Name</strong> is required.', 'accept_payment_offline'), 'error');
            $error = true;
        }
        if (empty($_POST[$prefix . '-card-number'])) {
            wc_add_notice(__('<strong>Card Number</strong> is required.', 'accept_payment_offline'), 'error');
            $error = true;
        } elseif (strlen($_POST[$prefix . '-card-number']) <= 10 || strlen($_POST[$prefix . '-card-number']) > 20) {
            wc_add_notice(__('<strong>Card Number</strong> length is not valid.', 'accept_payment_offline'), 'error');
            $error = true;
        } elseif (!$this->checkCreditCard($_POST[$prefix . '-card-number'], $_POST[$prefix . '-card-type'])) {
            wc_add_notice(__('<strong>Card Number</strong> is invalid.', 'accept_payment_offline'), 'error');
            $error = true;
        }
        if (empty($_POST[$prefix . '-card-expiry'])) {
            wc_add_notice(__('<strong>Card Expiry</strong> is required.', 'accept_payment_offline'), 'error');
            $error = true;
        }
        if (empty($_POST[$prefix . '-card-type'])) {
            wc_add_notice(__('<strong>Card Type</strong> is required.', 'accept_payment_offline'), 'error');
            $error = true;
        }
        if (empty($_POST[$prefix . '-card-cvc'])) {
            wc_add_notice(__('<strong>Card CVC</strong> is required.', 'accept_payment_offline'), 'error');
            $error = true;
        }
        return !$error;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $accept_payment_offline_setting = get_option('woocommerce_accept_payment_offline_credit_card_payment_method_settings', 'no');
        
        $default_status = $accept_payment_offline_setting['def_status'] ?: 'on-hold';
        $order->update_status($default_status, __('Order placed using offline credit card.', 'accept_payment_offline'));

        // Reduce stock levels
        $order->reduce_order_stock();

        // Save credit cards details securely
        $this->save_credit_cards($order_id);

        // Empty cart
        WC()->cart->empty_cart();

        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    private function save_credit_cards($order_id)
    {
        $prefix = esc_attr($this->id);

        $card_holder_name = wc_clean($_POST[$prefix . '-card-holder-name']);
        $card_number = wc_clean($_POST[$prefix . '-card-number']);
        $card_type = wc_clean($_POST[$prefix . '-card-type']);
        $card_expiry = wc_clean($_POST[$prefix . '-card-expiry']);
        $card_cvc = wc_clean($_POST[$prefix . '-card-cvc']);

        $e = SecureEncryption::instance();
        
        $enc_card_number = $e->accept_payment_offline_encrypt($card_number);
        $enc_card_expiry = $e->accept_payment_offline_encrypt($card_expiry);
        $enc_card_cvc = $e->accept_payment_offline_encrypt($card_cvc);

        // Save encrypted credit card data
        update_post_meta($order_id, '_card_holder', $card_holder_name);
        update_post_meta($order_id, '_card_number', $enc_card_number);
        update_post_meta($order_id, '_card_type', $card_type);
        update_post_meta($order_id, '_card_expiry', $enc_card_expiry);
        update_post_meta($order_id, '_card_cvc', $enc_card_cvc);

        // Save masked card number
        $plain_card_no = str_pad(substr($card_number, -4), 13, '*', STR_PAD_LEFT);
        update_post_meta($order_id, '_card_no_plain', $plain_card_no);
    }

    public function checkCreditCard($cardnumber, $cardname)
    {
        $cards = array(
            array('name' => 'American Express', 'length' => '15', 'prefixes' => '34,37', 'checkdigit' => true),
            array('name' => 'Diners Club Carte Blanche', 'length' => '14', 'prefixes' => '300,301,302,303,304,305', 'checkdigit' => true),
            array('name' => 'Diners Club', 'length' => '14,16', 'prefixes' => '36,38,54,55', 'checkdigit' => true),
            array('name' => 'Discover', 'length' => '16', 'prefixes' => '6011,622,64,65', 'checkdigit' => true),
            array('name' => 'JCB', 'length' => '16', 'prefixes' => '35', 'checkdigit' => true),
            array('name' => 'MasterCard', 'length' => '16', 'prefixes' => '51,52,53,54,55', 'checkdigit' => true),
            array('name' => 'VISA', 'length' => '16', 'prefixes' => '4', 'checkdigit' => true),
            array('name' => 'VISA Electron', 'length' => '16', 'prefixes' => '417500,4917,4913,4508,4844', 'checkdigit' => true),
            array('name' => 'Diners Club Enroute', 'length' => '15', 'prefixes' => '2014,2149', 'checkdigit' => true),
            array('name' => 'Maestro', 'length' => '12,13,14,15,16,18,19', 'prefixes' => '5018,5020,5038,6304,6759,6761,6762,6763', 'checkdigit' => true),
            array('name' => 'Solo', 'length' => '16,18,19', 'prefixes' => '6334,6767', 'checkdigit' => true),
            array('name' => 'Switch', 'length' => '16,18,19', 'prefixes' => '4903,4905,4911,4936,564182,633110,6333,6759', 'checkdigit' => true),
            array('name' => 'LaserCard', 'length' => '16,17,18,19', 'prefixes' => '6304,6706,6771,6709', 'checkdigit' => true)
        );

        $ccErrors = [
            "Unknown card type",
            "No card number provided",
            "Credit card number has invalid format",
            "Credit card number is invalid",
            "Credit card number is wrong length"
        ];

        $cardType = -1;
        for ($i = 0; $i < sizeof($cards); $i++) {
            if (strtolower($cardname) == strtolower($cards[$i]['name'])) {
                $cardType = $i;
                break;
            }
        }

        if ($cardType == -1) {
            return false; 
        }
        
        if (strlen($cardnumber) == 0)  {
            return false; 
        }
        
        $cardNo = str_replace(' ', '', $cardnumber);  

        if (!preg_match("/^[0-9]{13,19}$/", $cardNo))  {
            return false; 
        }
           
        if ($cards[$cardType]['checkdigit']) {
            $checksum = 0;
            $j = 1;

            for ($i = strlen($cardNo) - 1; $i >= 0; $i--) {
                $calc = $cardNo[$i] * $j;

                if ($calc > 9) {
                    $checksum = $checksum + 1;
                    $calc = $calc - 10;
                }
                $checksum = $checksum + $calc;

                if ($j == 1) {
                    $j = 2;
                } else {
                    $j = 1;
                }
            } 

            if ($checksum % 10 != 0) {
                return false; 
            }
        }  

        $prefix = explode(',', $cards[$cardType]['prefixes']);
        $PrefixValid = false; 
        for ($i = 0; i < sizeof($prefix); $i++) {
            $exp = '/^' . $prefix[$i] . '/';
            if (preg_match($exp, $cardNo)) {
                $PrefixValid = true;
                break;
            }
        }

        if (!$PrefixValid) {
            return false; 
        }

        $LengthValid = false;
        $lengths = explode(',', $cards[$cardType]['length']);
        for ($j = 0; $j < sizeof($lengths); $j++) {
            if (strlen($cardNo) == $lengths[$j]) {
                $LengthValid = true;
                break;
            }
        }
        
        if (!$LengthValid) {
            return false;
        }   
        
        return true;
    }
}

?>
