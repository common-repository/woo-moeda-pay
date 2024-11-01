<?php

/*
 * Plugin Name: WooCommerce Moeda Pay
 * Plugin URI: https://wordpress.org/plugins/woo-moeda-pay/
 * Description: Moeda Pay payment gateway
 * Version: 2.0.1
 * Author: Victor Municelli Dario
 * Developers: Victor Municelli Dario
 * 
 * WooCommerce Moeda Pay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * WooCommerce Moeda Pay is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WooCommerce Moeda Pay. If not, see
 * <https://www.gnu.org/licenses/gpl-3.0.txt>.
 */

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

add_action('plugins_loaded', 'woocommerce_moedapay', 0);

include_once('utils/notification.php');

function woocommerce_moedapay()
{

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_MOEDAPAY extends WC_Payment_Gateway
    {

        public static $log_enabled = true;
        public static $log = false;

        public function __construct()
        {
            global $woocommerce;
            $plugin_dir = plugin_dir_url(__FILE__);
            $this->id = 'WC_MOEDAPAY';
            $this->icon = apply_filters('woocommerce_Paysecure_icon', '' . $plugin_dir . 'moedapay.png');
            $this->method_title = 'Moeda Pay';
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->test_env = 'yes' == $this->get_option('test');
            $this->description = $this->get_option('description');
            $this->auth_token = $this->get_option('auth_token');
            $this->return_url_success = $this->get_option('return_url_success');
            $this->callback_url = get_home_url(null, '', 'https') . '/wc-api/wc_moedapay_callback/';
            $this->return_url_fail = $this->get_option('return_url_fail');
            $this->currency = $this->get_option('currency');
            $this->endpoint = $this->test_env ? 'https://api-moedapay-dev.herokuapp.com' : 'https://moedapay.moedaseeds.com';

            add_action('woocommerce_product_options_advanced', array($this, 'show_product_custom_fields'));
            add_action('woocommerce_process_product_meta', array($this, 'save_product_custom_fields'));

            add_filter('allowed_http_origins', array($this, 'allowed_http_origins'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_moedapay_callback', array($this, 'callback_payment_handler'));
        }

        function show_product_custom_fields()
        {
            echo '<div class="product_custom_field">';
            // Custom Product Text Field
            woocommerce_wp_text_input(
                array(
                    'id' => '_moedapay_marketplace_product_id',
                    'placeholder' => '',
                    'label' => __('Moeda Pay product id', 'woocommerce'),
                    'desc_tip' => 'true'
                )
            );
            echo '</div>';
        }

        function save_product_custom_fields($post_id)
        {
            self::log('Saving custom fields ' . $post_id . json_encode($_POST));
            $moedapay_product_id_text_field = $_POST['_moedapay_marketplace_product_id'];
            if (!empty($moedapay_product_id_text_field)) {
                update_post_meta($post_id, '_moedapay_marketplace_product_id', esc_attr($moedapay_product_id_text_field));
            }
        }

        public static function log($message, $level = 'info')
        {
            if (self::$log_enabled) {
                if (empty(self::$log)) {
                    self::$log = wc_get_logger();
                }
                self::$log->log($level, $message, array('source' => 'moedapay'));
            }
        }

        public static function coalesce_string($str1, $str2)
        {
            if (isset($str1) and $str1 != '') {
                return $str1;
            }
            return $str2;
        }

        public function allowed_http_origins($allowed_origins)
        {
            $allowed_origins = array('https://moedapay.moedaseeds.com', 'https://api-moedapay-dev.herokuapp.com');
            return $allowed_origins;
        }

        public function init_form_fields()
        {

            include_once(ABSPATH . 'wp-admin/includes/plugin.php');

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable Moeda Pay Module.'),
                    'default' => 'no'
                ),
                'test' => array(
                    'title' => __('Test environment'),
                    'type' => 'checkbox',
                    'label' => __('Enable test environment.'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title:'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.'),
                    'default' => __('Moeda Pay')
                ),
                'description' => array(
                    'title' => __('Description:'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.'),
                    'default' => __('Moeda Pay is a secure payment gateway powered by blockchain technology.')
                ),
                'auth_token' => array(
                    'title' => __('API key:'),
                    'type' => 'text',
                    'description' => __('* This is required'),
                    'default' => __('')
                ),
                'currency' => array(
                    'title' => __('Currency:'),
                    'type' => 'text',
                    'description' => __('Used in product prices'),
                    'default' => __('BRL')
                ),
                'return_url_fail' => array(
                    'title' => __('Return URL fail:'),
                    'type' => 'text',
                    'description' => __(''),
                    'default' => get_home_url()
                ),
                'return_url_success' => array(
                    'title' => __('Return URL success:'),
                    'type' => 'text',
                    'description' => __(''),
                    'default' => get_home_url()
                )
            );
        }

        public function admin_options()
        {
            echo '<h3>' . __('Moeda Pay payment gateway') . '</h3>';
            echo '<p>' . __('<a target="_blank" href="">Moeda Pay</a> is secure payment gateway powered by blockchain technology.') . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = wc_get_order($order_id);

            require_once('utils/coupons.php');
            $coupons = get_coupons($order_id);

            $products = array();
            $items = $order->get_items();
            foreach ($items as $key => $orderItem) {
                $moedapay_product_id = get_post_meta($orderItem->get_product_id(), '_moedapay_marketplace_product_id', true);
                if (empty($moedapay_product_id)) {
                    wp_delete_post($order_id, true);
                    $product_name = $orderItem->get_name();
                    $product_id = $orderItem->get_product_id();
                    wc_add_notice(__('Moeda Pay configuration error, try again later'), 'error');
                    custom_notification_helper(__('Moeda Pay configuration error'), 'error');
                    custom_notification_helper(
                        __("Product '$product_name' (ID: $product_id) does not have Moeda Pay product id (Go to 'Edit product' and input the correct id in product data section)"),
                        'error'
                    );
                    self::log(__("Product '$product_name' (ID: $product_id) does not have Moeda Pay product id (Go to 'Edit product' and input the correct id in product data section)"), 'error');
                    return array('result' => 'error', 'redirect' => '');
                }
                $products[] = array(
                    'id' => $moedapay_product_id,
                    'name' => $orderItem->get_name(),
                    'value' => $orderItem->get_product()->get_price(),
                    'quantity' => intval($orderItem->get_quantity())
                );
            }

            require_once('utils/address.php');
            $address = self::coalesce_string($order->get_shipping_address_1(), $order->get_billing_address_1());
            $formatAddress = format_address($address);

            $deliveryInfo = array(
                'firstName' => self::coalesce_string($order->get_shipping_first_name(), $order->get_billing_first_name()),
                'lastName' => self::coalesce_string($order->get_shipping_last_name(), $order->get_billing_last_name()),
                'email' => $order->get_billing_email(),
                'city' => self::coalesce_string($order->get_shipping_city(), $order->get_billing_city()),
                'countryCode' => self::coalesce_string($order->get_shipping_country(), $order->get_billing_country()),
                'zip' => self::coalesce_string($order->get_shipping_postcode(), $order->get_billing_postcode()),
                'street' => $formatAddress['street'],
                'number' => $formatAddress['number'],
                'others' => self::coalesce_string($order->get_shipping_address_2(), $order->get_billing_address_2()),
                'state' => self::coalesce_string($order->get_shipping_state(), $order->get_billing_state())
            );

            $shipping_total = $order->get_shipping_total();
            $shipping_tax   = $order->get_shipping_tax();
            $delivery_fee = $shipping_total + $shipping_tax;
            $delivery = null;
            if ($delivery_fee > 0) {
                $delivery = array(
                    'fee' => array('value' => $delivery_fee)
                );
            }
            $total = $order->get_total(); // total (discounts and all applied)
            $subtotal = $order->get_subtotal(); // subtotal
            // process order
            $body = array(
                'products' => $products,
                'currency' => $this->currency,
                'deliveryInfo' => $deliveryInfo,
                'optionals' => array(
                    'wc' => array(
                        'order_id' => $order_id,
                        'callback_url' => $this->callback_url,
                        'return_url_success' => $this->return_url_success,
                        'return_url_fail' => $this->return_url_fail,
                        'total' => $total,
                        'subtotal' => $subtotal,
                        'coupons' => $coupons['coupons']
                    ),
                    'discounts' => array(
                        'general' => $coupons['general_discount'],
                        'products' => $coupons['product_discounts']
                    ),
                    'delivery' => $delivery
                )
            );

            $headers = array(
                'x-api-key' => $this->auth_token
            );

            $ret = wp_safe_remote_post($this->endpoint . '/api/orders', array(
                'body' => $body,
                'headers' => $headers,
                'timeout' => 60
            ));
            $response = json_decode(wp_remote_retrieve_body($ret), true);
            if (is_wp_error($ret) or $response['status'] >= 400) {
                $error_message = $response['message'];
                self::log(json_encode($ret), 'error');
                wc_add_notice('Moeda Pay error: ' . $error_message, 'error');
                wp_delete_post($order_id, true);
                return array('result' => 'error', 'redirect' => '');
            }

            wc_reduce_stock_levels($order);
            $woocommerce->cart->empty_cart();
            return array(
                'result' => 'success',
                'redirect' => $this->get_request_url($order, $response['data']['oid'])
            );
        }

        function get_request_url($order, $unique_id)
        {
            $form_params = array(
                'cancelReturnUrl' => $this->return_url_fail,
                'successReturnUrl' => $this->return_url_success,
                'oid' => $unique_id
            );
            $form_params_joins = '';
            foreach ($form_params as $key => $value) {
                $form_params_joins .= $key . '=' . $value . '&';
            }
            return $this->endpoint . '?' . $form_params_joins;
        }

        public function callback_payment_handler()
        {
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: POST, OPTIONS");
            header('Access-Control-Allow-Headers: Origin, Content-Type, x-api-key,authorization,XMLHttpRequest, user-agent, accept');
            if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
                status_header(200);
                exit();
            } elseif ('POST' == $_SERVER['REQUEST_METHOD']) {
                $api_key = $_SERVER['HTTP_X_API_KEY'];
                if ($this->auth_token != $api_key) {
                    wp_die(__('Not allowed'), 'Moeda Pay', array('response' => 400));
                }

                
                $raw_post = file_get_contents('php://input');
                $decoded  = json_decode($raw_post);
                $order_id = (int) $decoded->oid;
                $status = $decoded->status;
                $message = $decoded->message;
                self::log(json_encode($decoded));

                if (isset($order_id) and isset($status)) {
                    $order = new WC_Order($order_id);
                    if (isset($message)) {
                        $order->add_order_note(esc_html_e($message));
                    }

                    $prev_status = strtoupper($order->get_status());
                    self::log('Changing order ' . $order_id . ' status: ' . $prev_status . ' -> ' . $status);

                    if ($status == 'PAID' && $prev_status == 'ON-HOLD') {
                        try {
                            $order->update_status('processing',  __('Payment was successful ', 'woocommerce-moedapay-payment-gateway'));
                            $order->payment_complete();
                            wp_die(__('Success'), 'Moeda Pay', array('response' => 200));
                        } catch (\Throwable $th) {
                            wp_die(__('Payment failed'), 'Moeda Pay', array('response' => 404));
                        }
                    } elseif ($status == 'CANCELLED' && $prev_status == 'PENDING') {
                        try {
                            wc_increase_stock_levels( $order );
                            $order->update_status('cancelled',  __('Payment was cancelled ', 'woocommerce-moedapay-payment-gateway'));
                            wp_die(__('Success'), 'Moeda Pay', array('response' => 200));
                        } catch (\Throwable $th) {
                            wp_die(__('Payment failed'), 'Moeda Pay', array('response' => 404));
                        }
                    } elseif ($status == 'ON-HOLD' && $prev_status == 'PENDING') {
                        try {
                            $order->update_status('on-hold',  __('Payment awaiting to complete ', 'woocommerce-moedapay-payment-gateway'));
                            wp_die(__('Success'), 'Moeda Pay', array('response' => 200));
                        } catch (\Throwable $th) {
                            wp_die(__('Payment failed'), 'Moeda Pay', array('response' => 404));
                        }
                    } else {
                        self::log('Could not change order ' . $order_id . ' status: ' . $prev_status . ' -> ' . $status);
                        wp_die(__('Could not change order ' . $order_id . ' status: ' . $prev_status . ' -> ' . $status), 'Moeda Pay', array('response' => 400));
                    }
                }
                wp_die(__('Invalid order ' . $order_id), 'Moeda Pay', array('response' => 500));
            }
        }

        function payment_fields()
        {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }
    }

    $WC = new WC_MOEDAPAY();

    function woocommerce_add_moedapay_gateway($methods)
    {
        $methods[] = 'WC_MOEDAPAY';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_moedapay_gateway');
}
