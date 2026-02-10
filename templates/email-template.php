<?php
/**
 * Logistika Email Template
 *
 * @package Logistika_WooCommerce
 *
 * Available variables:
 * $order_ids - Array of order IDs
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('Logistika - Export Ordini', 'logistika-woocommerce'); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: #fff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .intro {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .order-list {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .order-list h3 {
            margin: 0 0 15px;
            font-size: 16px;
            color: #2c3e50;
        }
        .order-item {
            padding: 12px;
            background: #fff;
            border-radius: 4px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
        }
        .order-item:last-child {
            margin-bottom: 0;
        }
        .order-number {
            font-weight: 600;
            color: #2c3e50;
        }
        .order-customer {
            color: #666;
            font-size: 14px;
        }
        .order-total {
            float: right;
            font-weight: 600;
            color: #27ae60;
        }
        .summary {
            background: #e8f4fc;
            border-radius: 6px;
            padding: 15px 20px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-label {
            color: #666;
        }
        .summary-value {
            font-weight: 600;
            color: #2c3e50;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            font-size: 12px;
            color: #666;
        }
        .footer a {
            color: #3498db;
            text-decoration: none;
        }
        .attachment-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }
        .attachment-note strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1><?php _e('Logistika', 'logistika-woocommerce'); ?></h1>
                <p><?php _e('Export Ordini WooCommerce', 'logistika-woocommerce'); ?></p>
            </div>

            <div class="content">
                <p class="intro">
                    <?php _e('In allegato trovi il file CSV con i dati degli ordini da processare.', 'logistika-woocommerce'); ?>
                </p>

                <div class="order-list">
                    <h3><?php _e('Ordini inclusi', 'logistika-woocommerce'); ?></h3>

                    <?php
                    $total_amount = 0;
                    foreach ($order_ids as $order_id):
                        $order = wc_get_order($order_id);
                        if (!$order) continue;
                        $total_amount += $order->get_total();
                    ?>
                        <div class="order-item">
                            <span class="order-total"><?php echo $order->get_formatted_order_total(); ?></span>
                            <span class="order-number">#<?php echo $order->get_order_number(); ?></span>
                            <br>
                            <span class="order-customer">
                                <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>
                                <?php if ($order->get_shipping_city()): ?>
                                    - <?php echo esc_html($order->get_shipping_city()); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary">
                    <div class="summary-row">
                        <span class="summary-label"><?php _e('Data Export', 'logistika-woocommerce'); ?></span>
                        <span class="summary-value"><?php echo date('d/m/Y H:i:s'); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label"><?php _e('Totale Ordini', 'logistika-woocommerce'); ?></span>
                        <span class="summary-value"><?php echo count($order_ids); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label"><?php _e('Valore Totale', 'logistika-woocommerce'); ?></span>
                        <span class="summary-value"><?php echo wc_price($total_amount); ?></span>
                    </div>
                </div>

                <div class="attachment-note">
                    <strong><?php _e('Il file CSV e\' in allegato a questa email', 'logistika-woocommerce'); ?></strong>
                </div>
            </div>

            <div class="footer">
                <p>
                    <?php _e('Email generata automaticamente da', 'logistika-woocommerce'); ?>
                    <strong>Logistika WooCommerce Plugin</strong>
                </p>
                <p>
                    <?php echo get_bloginfo('name'); ?> -
                    <a href="<?php echo home_url(); ?>"><?php echo home_url(); ?></a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
