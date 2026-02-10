<?php
/**
 * Logistika CSV Generator
 *
 * @package Logistika_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Logistika_CSV {

    /**
     * CSV Headers (matching Logistika import format)
     */
    private $headers = array(
        'CodVet',
        'NUMDOCEST',
        'DATADOCEST',
        'DESTZCLI',
        'INDDESTZCLI',
        'CAPDESTZCLI',
        'CITDESTZCLI',
        'PROVDESTZCLI',
        'CONTRASSEGNO',
        'CodPor',
        'CFDESTCLI',
        'PIDESTCLI',
        'NOMESPEC',
        'TELDESTZCLI',
        'MAILDESTZCLI',
        'ImpLordoOrdine',
        'NOTE INTERNE',
        'NOTE SPEDIZIONE',
        'INTERNOCODART',
        'CODART',
        'DESCRIZIONE ARTICOLO',
        'QTA RICHIESTA'
    );

    /**
     * CSV Separator
     */
    private $separator;

    /**
     * Constructor
     */
    public function __construct() {
        $this->separator = get_option('logistika_csv_separator', ';');
    }

    /**
     * Generate CSV for single order
     *
     * @param int|WC_Order $order Order ID or object
     * @return string CSV content
     */
    public function generate_for_order($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order) {
            return false;
        }

        $rows = $this->get_order_rows($order);
        return $this->build_csv($rows);
    }

    /**
     * Generate CSV for multiple orders
     *
     * @param array $order_ids Array of order IDs
     * @return string CSV content
     */
    public function generate_for_orders($order_ids) {
        $all_rows = array();

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $rows = $this->get_order_rows($order);
                $all_rows = array_merge($all_rows, $rows);
            }
        }

        return $this->build_csv($all_rows);
    }

    /**
     * Get CSV rows for an order (one row per product)
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_rows($order) {
        $rows = array();
        $items = $order->get_items();

        // Common order data
        $order_data = $this->get_order_data($order);

        foreach ($items as $item) {
            $product = $item->get_product();
            $row = $order_data;

            // Product specific fields
            $row['INTERNOCODART'] = $product ? $product->get_id() : '';
            $row['CODART'] = $product ? $product->get_sku() : '';
            $row['DESCRIZIONE ARTICOLO'] = $item->get_name();
            $row['QTA RICHIESTA'] = $item->get_quantity();

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Get common order data
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_data($order) {
        $codvet = get_option('logistika_codvet', '40005');
        $date_format = get_option('logistika_date_format', 'd/m/y');

        // Determine if COD (contrassegno)
        $is_cod = $order->get_payment_method() === 'cod';
        $cod_amount = $is_cod ? $this->format_price($order->get_total()) : '';
        $cod_code = $is_cod ? '04' : '';

        // Get billing/shipping data
        $shipping_first_name = $order->get_shipping_first_name();
        $shipping_last_name = $order->get_shipping_last_name();
        $shipping_company = $order->get_shipping_company();

        // Use billing if shipping is empty
        if (empty($shipping_first_name) && empty($shipping_last_name)) {
            $shipping_first_name = $order->get_billing_first_name();
            $shipping_last_name = $order->get_billing_last_name();
        }
        if (empty($shipping_company)) {
            $shipping_company = $order->get_billing_company();
        }

        // Build destination name
        $dest_name = trim($shipping_company);
        if (empty($dest_name)) {
            $dest_name = trim($shipping_first_name . ' ' . $shipping_last_name);
        }

        // Get address
        $address_1 = $order->get_shipping_address_1();
        $address_2 = $order->get_shipping_address_2();
        if (empty($address_1)) {
            $address_1 = $order->get_billing_address_1();
            $address_2 = $order->get_billing_address_2();
        }
        $full_address = trim($address_1 . ' ' . $address_2);

        // Get city, postcode, state
        $city = $order->get_shipping_city() ?: $order->get_billing_city();
        $postcode = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
        $state = $order->get_shipping_state() ?: $order->get_billing_state();

        // Get phone and email
        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();

        // Get order notes
        $customer_note = $order->get_customer_note();

        // Get custom meta for internal notes if exists
        $internal_note = $order->get_meta('_logistika_note_interne');
        $shipping_note = $order->get_meta('_logistika_note_spedizione');

        // Try to get CF and P.IVA from order meta or billing fields
        $codice_fiscale = $order->get_meta('_billing_cf') ?: $order->get_meta('billing_cf') ?: $order->get_meta('_billing_codice_fiscale') ?: '';
        $partita_iva = $order->get_meta('_billing_piva') ?: $order->get_meta('billing_piva') ?: $order->get_meta('_billing_partita_iva') ?: $order->get_meta('_vat_number') ?: '';

        // NOMESPEC - specific person (use first+last name if company is set)
        $nome_spec = '';
        if (!empty($shipping_company)) {
            $nome_spec = trim($shipping_first_name . ' ' . $shipping_last_name);
        }

        return array(
            'CodVet' => $codvet,
            'NUMDOCEST' => $order->get_order_number(),
            'DATADOCEST' => $order->get_date_created()->date($date_format),
            'DESTZCLI' => $dest_name,
            'INDDESTZCLI' => $full_address,
            'CAPDESTZCLI' => $postcode,
            'CITDESTZCLI' => $city,
            'PROVDESTZCLI' => $state,
            'CONTRASSEGNO' => $cod_amount,
            'CodPor' => $cod_code,
            'CFDESTCLI' => $codice_fiscale,
            'PIDESTCLI' => $partita_iva,
            'NOMESPEC' => $nome_spec,
            'TELDESTZCLI' => $phone,
            'MAILDESTZCLI' => $email,
            'ImpLordoOrdine' => $this->format_price($order->get_total()),
            'NOTE INTERNE' => $internal_note ?: $customer_note,
            'NOTE SPEDIZIONE' => $shipping_note,
            'INTERNOCODART' => '',
            'CODART' => '',
            'DESCRIZIONE ARTICOLO' => '',
            'QTA RICHIESTA' => ''
        );
    }

    /**
     * Build CSV content from rows
     *
     * Uses raw implode (no quoting) to match Logistika expected format.
     *
     * @param array $rows
     * @return string
     */
    private function build_csv($rows) {
        $lines = array();

        // Add header line
        $lines[] = implode($this->separator, $this->headers);

        // Add data rows
        foreach ($rows as $row) {
            $csv_row = array();
            foreach ($this->headers as $header) {
                $value = isset($row[$header]) ? $row[$header] : '';
                // Strip separator and newlines from values to prevent corruption
                $value = str_replace(array($this->separator, "\r", "\n"), '', $value);
                $csv_row[] = $value;
            }
            $lines[] = implode($this->separator, $csv_row);
        }

        // Join with newlines, add BOM for Excel compatibility
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Format price for CSV (Italian format with € prefix)
     *
     * @param float $price
     * @return string
     */
    private function format_price($price) {
        return '€ ' . number_format((float)$price, 2, ',', '.');
    }

    /**
     * Save CSV to file
     *
     * @param string $csv_content
     * @param string $filename
     * @return string|false File path or false on failure
     */
    public function save_to_file($csv_content, $filename = null) {
        $upload_dir = wp_upload_dir();
        $logistika_dir = $upload_dir['basedir'] . '/logistika-exports';

        if (!file_exists($logistika_dir)) {
            wp_mkdir_p($logistika_dir);
        }

        if (!$filename) {
            $filename = 'logistika-export-' . date('Y-m-d-His') . '.csv';
        }

        $filepath = $logistika_dir . '/' . $filename;

        if (file_put_contents($filepath, $csv_content)) {
            return $filepath;
        }

        return false;
    }

    /**
     * Get headers
     *
     * @return array
     */
    public function get_headers() {
        return $this->headers;
    }
}
