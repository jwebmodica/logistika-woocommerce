<?php
/**
 * Logistika Email Handler
 *
 * @package Logistika_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Logistika_Email {

    /**
     * Send CSV via email
     *
     * @param string $csv_content CSV content
     * @param int|array $order_ids Order ID(s) for reference
     * @return bool
     */
    public function send($csv_content, $order_ids) {
        $to = get_option('logistika_email_destinatario', get_option('admin_email'));

        if (empty($to)) {
            return false;
        }

        // Prepare subject
        $subject = get_option('logistika_email_oggetto', 'Nuovo ordine Logistika - {order_id}');

        if (is_array($order_ids)) {
            $order_ref = count($order_ids) > 1
                ? count($order_ids) . ' ordini'
                : $order_ids[0];
        } else {
            $order_ref = $order_ids;
        }

        $subject = str_replace('{order_id}', $order_ref, $subject);
        $subject = str_replace('{date}', date('d/m/Y H:i'), $subject);

        // Prepare body
        $body = $this->get_email_body($order_ids);

        // Save CSV to temp file
        $csv_generator = new Logistika_CSV();
        $filename = 'logistika-' . (is_array($order_ids) ? 'batch' : $order_ids) . '-' . date('Ymd-His') . '.csv';
        $filepath = $csv_generator->save_to_file($csv_content, $filename);

        if (!$filepath) {
            return false;
        }

        // Headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email with attachment
        $sent = wp_mail($to, $subject, $body, $headers, array($filepath));

        // Log the export
        $this->log_export($order_ids, $to, $sent);

        return $sent;
    }

    /**
     * Get email body HTML
     *
     * @param int|array $order_ids
     * @return string
     */
    private function get_email_body($order_ids) {
        if (!is_array($order_ids)) {
            $order_ids = array($order_ids);
        }

        $template_path = LOGISTIKA_PLUGIN_DIR . 'templates/email-template.php';

        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }

        // Default template
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: #fff; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 20px; background: #f9f9f9; }
        .order-list { background: #fff; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .order-item { padding: 8px 0; border-bottom: 1px solid #eee; }
        .order-item:last-child { border-bottom: none; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Logistika - Export Ordini</h1>
        </div>
        <div class="content">
            <p>In allegato trovi il file CSV con i dati degli ordini da processare.</p>

            <div class="order-list">
                <strong>Ordini inclusi:</strong>';

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $html .= '<div class="order-item">';
                $html .= '<strong>#' . $order->get_order_number() . '</strong> - ';
                $html .= esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                $html .= ' - ' . wc_price($order->get_total());
                $html .= '</div>';
            }
        }

        $html .= '
            </div>

            <p><strong>Data export:</strong> ' . date('d/m/Y H:i:s') . '</p>
            <p><strong>Totale ordini:</strong> ' . count($order_ids) . '</p>
        </div>
        <div class="footer">
            <p>Email generata automaticamente da Logistika WooCommerce Plugin</p>
            <p>' . get_bloginfo('name') . ' - ' . home_url() . '</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Log export to database/meta
     *
     * @param int|array $order_ids
     * @param string $email
     * @param bool $success
     */
    private function log_export($order_ids, $email, $success) {
        if (!is_array($order_ids)) {
            $order_ids = array($order_ids);
        }

        $log_entry = array(
            'date' => current_time('mysql'),
            'email' => $email,
            'success' => $success,
            'orders' => $order_ids
        );

        // Get existing logs
        $logs = get_option('logistika_export_logs', array());

        // Add new entry
        array_unshift($logs, $log_entry);

        // Keep only last 100 entries
        $logs = array_slice($logs, 0, 100);

        update_option('logistika_export_logs', $logs);

        // Also add meta to each order
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_logistika_exported', current_time('mysql'));
                $order->update_meta_data('_logistika_export_email', $email);
                $order->add_order_note(
                    sprintf(
                        __('Ordine esportato in CSV e inviato a %s', 'logistika-woocommerce'),
                        $email
                    )
                );
                $order->save();
            }
        }
    }
}
