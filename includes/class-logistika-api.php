<?php
/**
 * Logistika API Client
 *
 * Handles communication with Logistika REST API
 *
 * @package Logistika_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Logistika_Api {

    /**
     * API Base URL
     */
    private $api_url;

    /**
     * API Key
     */
    private $api_key;

    /**
     * Last error message
     */
    private $last_error = '';

    /**
     * Last response
     */
    private $last_response = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_url = 'https://logistika.jwebmodica.com/api';
        $this->api_key = get_option('logistika_api_key', '');
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->api_key);
    }

    /**
     * Send order to Logistika API
     *
     * @param WC_Order $order WooCommerce order
     * @return array|false Response data or false on failure
     */
    public function send_order($order) {
        if (!$this->is_configured()) {
            $this->last_error = 'API non configurata';
            return false;
        }

        $data = $this->format_order_data($order);

        $response = $this->request('POST', '/v1/orders', $data);

        if ($response && isset($response['success']) && $response['success']) {
            // Save API order ID to meta
            if (isset($response['data']['id'])) {
                $order->update_meta_data('_logistika_api_order_id', $response['data']['id']);
                $order->update_meta_data('_logistika_api_sent', current_time('mysql'));
                $order->add_order_note(
                    sprintf(
                        __('Ordine inviato a Logistika via API. ID: %d', 'logistika-woocommerce'),
                        $response['data']['id']
                    )
                );
                $order->save();
            }
            return $response['data'];
        }

        return false;
    }

    /**
     * Get order status from Logistika
     *
     * @param int $logistika_order_id Logistika order ID
     * @return array|false
     */
    public function get_order_status($logistika_order_id) {
        if (!$this->is_configured()) {
            $this->last_error = 'API non configurata';
            return false;
        }

        $response = $this->request('GET', "/v1/orders/{$logistika_order_id}");

        if ($response && isset($response['success']) && $response['success']) {
            return $response['data'];
        }

        return false;
    }

    /**
     * Get list of orders from Logistika
     *
     * @param array $params Query parameters
     * @return array|false
     */
    public function get_orders($params = []) {
        if (!$this->is_configured()) {
            $this->last_error = 'API non configurata';
            return false;
        }

        $query = http_build_query($params);
        $endpoint = '/v1/orders' . ($query ? "?{$query}" : '');

        $response = $this->request('GET', $endpoint);

        if ($response && isset($response['success']) && $response['success']) {
            return [
                'orders' => $response['data'],
                'pagination' => $response['meta']['pagination'] ?? null
            ];
        }

        return false;
    }

    /**
     * Update tracking information
     *
     * @param int $logistika_order_id
     * @param array $tracking_data
     * @return bool
     */
    public function update_tracking($logistika_order_id, $tracking_data) {
        if (!$this->is_configured()) {
            $this->last_error = 'API non configurata';
            return false;
        }

        $response = $this->request('PUT', "/v1/orders/{$logistika_order_id}/tracking", $tracking_data);

        return $response && isset($response['success']) && $response['success'];
    }

    /**
     * Test API connection
     *
     * @return bool
     */
    public function test_connection(): bool {
        if (!$this->is_configured()) {
            $this->last_error = 'API non configurata';
            return false;
        }

        // Try health endpoint first (no auth)
        $health = $this->request('GET', '/v1/health', null, false);

        if (!$health) {
            return false;
        }

        // Try authenticated endpoint
        $response = $this->request('GET', '/v1/orders?per_page=1');

        return $response && isset($response['success']) && $response['success'];
    }

    /**
     * Format WooCommerce order data for API
     *
     * @param WC_Order $order
     * @return array
     */
    private function format_order_data($order): array {
        // Get shipping or billing address
        $shipping_first = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
        $shipping_last = $order->get_shipping_last_name() ?: $order->get_billing_last_name();
        $shipping_company = $order->get_shipping_company() ?: $order->get_billing_company();

        $dest_name = trim($shipping_company ?: "{$shipping_first} {$shipping_last}");

        $data = [
            'riferimento_cliente' => $order->get_order_number(),
            'destinatario' => [
                'nome' => $dest_name,
                'indirizzo' => trim(
                    ($order->get_shipping_address_1() ?: $order->get_billing_address_1()) . ' ' .
                    ($order->get_shipping_address_2() ?: $order->get_billing_address_2())
                ),
                'citta' => $order->get_shipping_city() ?: $order->get_billing_city(),
                'cap' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
                'provincia' => $order->get_shipping_state() ?: $order->get_billing_state(),
                'nazione' => $order->get_shipping_country() ?: $order->get_billing_country() ?: 'IT',
                'telefono' => $order->get_billing_phone(),
                'email' => $order->get_billing_email()
            ],
            'note' => $order->get_customer_note(),
            'prodotti' => []
        ];

        // Add products
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();

            $data['prodotti'][] = [
                'sku' => $product ? $product->get_sku() : '',
                'nome' => $item->get_name(),
                'quantita' => $item->get_quantity(),
                'prezzo_unitario' => $order->get_item_total($item, false, true)
            ];
        }

        return $data;
    }

    /**
     * Make API request
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array|null $data Request body data
     * @param bool $auth Include authentication
     * @return array|false
     */
    private function request(string $method, string $endpoint, $data = null, bool $auth = true) {
        $url = $this->api_url . $endpoint;

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ];

        if ($auth && $this->api_key) {
            $args['headers']['X-API-Key'] = $this->api_key;
        }

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->last_response = [
            'code' => $code,
            'body' => $body
        ];

        $decoded = json_decode($body, true);

        if ($code >= 400) {
            $this->last_error = $decoded['error']['message'] ?? "HTTP Error {$code}";
            return false;
        }

        return $decoded;
    }

    /**
     * Get last error message
     *
     * @return string
     */
    public function get_last_error(): string {
        return $this->last_error;
    }

    /**
     * Get last response
     *
     * @return array|null
     */
    public function get_last_response(): ?array {
        return $this->last_response;
    }
}
