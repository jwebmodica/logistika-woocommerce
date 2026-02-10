<?php
/**
 * Logistika Admin Settings and Actions
 *
 * @package Logistika_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Logistika_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Add bulk action to orders list
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_action'));
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'add_bulk_action'));

        // Handle bulk action
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_action'), 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handle_bulk_action'), 10, 3);

        // Admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Add order action button
        add_action('woocommerce_order_actions', array($this, 'add_order_action'));
        add_action('woocommerce_order_action_logistika_export', array($this, 'process_order_action'));

        // Add meta box to order
        add_action('add_meta_boxes', array($this, 'add_meta_box'));

        // AJAX handlers
        add_action('wp_ajax_logistika_export_order', array($this, 'ajax_export_order'));
        add_action('wp_ajax_logistika_download_csv', array($this, 'ajax_download_csv'));
        add_action('wp_ajax_logistika_preview_csv', array($this, 'ajax_preview_csv'));
        add_action('wp_ajax_logistika_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_logistika_send_via_api', array($this, 'ajax_send_via_api'));
        add_action('wp_ajax_logistika_test_email', array($this, 'ajax_test_email'));

        // Auto export on order status change (unified handler)
        add_action('woocommerce_order_status_changed', array($this, 'maybe_auto_export'), 10, 4);

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . LOGISTIKA_PLUGIN_BASENAME, array($this, 'add_settings_link'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Logistika', 'logistika-woocommerce'),
            __('Logistika', 'logistika-woocommerce'),
            'manage_woocommerce',
            'logistika',
            array($this, 'render_main_page'),
            'dashicons-truck',
            56
        );

        add_submenu_page(
            'logistika',
            __('Dashboard', 'logistika-woocommerce'),
            __('Dashboard', 'logistika-woocommerce'),
            'manage_woocommerce',
            'logistika',
            array($this, 'render_main_page')
        );

        add_submenu_page(
            'logistika',
            __('Esporta Ordini', 'logistika-woocommerce'),
            __('Esporta Ordini', 'logistika-woocommerce'),
            'manage_woocommerce',
            'logistika-export',
            array($this, 'render_export_page')
        );

        add_submenu_page(
            'logistika',
            __('Impostazioni', 'logistika-woocommerce'),
            __('Impostazioni', 'logistika-woocommerce'),
            'manage_woocommerce',
            'logistika-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'logistika',
            __('Log Export', 'logistika-woocommerce'),
            __('Log Export', 'logistika-woocommerce'),
            'manage_woocommerce',
            'logistika-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('logistika_settings', 'logistika_codvet', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '40005'
        ));

        register_setting('logistika_settings', 'logistika_email_destinatario', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'default' => get_option('admin_email')
        ));

        register_setting('logistika_settings', 'logistika_email_oggetto', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Nuovo ordine Logistika - {order_id}'
        ));

        register_setting('logistika_settings', 'logistika_auto_export', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no'
        ));

        register_setting('logistika_settings', 'logistika_export_status', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'processing'
        ));

        register_setting('logistika_settings', 'logistika_date_format', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'd/m/y'
        ));

        register_setting('logistika_settings', 'logistika_csv_separator', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ';'
        ));

        // API settings
        register_setting('logistika_settings', 'logistika_send_method', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'email'
        ));

        register_setting('logistika_settings', 'logistika_api_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));

        register_setting('logistika_settings', 'logistika_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'logistika') !== false || $hook === 'edit.php' || $hook === 'post.php' || strpos($hook, 'wc-orders') !== false) {
            wp_enqueue_style(
                'logistika-admin',
                LOGISTIKA_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                LOGISTIKA_VERSION
            );

            wp_enqueue_script(
                'logistika-admin',
                LOGISTIKA_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                LOGISTIKA_VERSION,
                true
            );

            wp_localize_script('logistika-admin', 'logistikaAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('logistika_nonce'),
                'sendMethod' => get_option('logistika_send_method', 'email'),
                'strings' => array(
                    'confirm_export' => __('Sei sicuro di voler esportare questo ordine?', 'logistika-woocommerce'),
                    'confirm_export_bulk' => __('Sei sicuro di voler esportare gli ordini selezionati?', 'logistika-woocommerce'),
                    'exporting' => __('Esportazione in corso...', 'logistika-woocommerce'),
                    'success' => __('Esportazione completata con successo!', 'logistika-woocommerce'),
                    'error' => __('Errore durante l\'esportazione', 'logistika-woocommerce'),
                    'confirm_api' => __('Sei sicuro di voler inviare questo ordine via API?', 'logistika-woocommerce'),
                    'select_order' => __('Seleziona almeno un ordine.', 'logistika-woocommerce'),
                    'select_action' => __('Seleziona un\'azione.', 'logistika-woocommerce'),
                )
            ));
        }
    }

    /**
     * Render main dashboard page
     */
    public function render_main_page() {
        // Get statistics
        $today_exports = $this->get_exports_count('today');
        $week_exports = $this->get_exports_count('week');
        $pending_orders = $this->get_pending_orders_count();
        $send_method = get_option('logistika_send_method', 'email');

        ?>
        <div class="wrap logistika-wrap">
            <h1><span class="dashicons dashicons-truck"></span> <?php _e('Logistika Dashboard', 'logistika-woocommerce'); ?></h1>

            <div class="logistika-dashboard">
                <div class="logistika-stats">
                    <div class="stat-card">
                        <div class="stat-icon"><span class="dashicons dashicons-download"></span></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $today_exports; ?></span>
                            <span class="stat-label"><?php _e('Export Oggi', 'logistika-woocommerce'); ?></span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $week_exports; ?></span>
                            <span class="stat-label"><?php _e('Export Settimana', 'logistika-woocommerce'); ?></span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon"><span class="dashicons dashicons-cart"></span></div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $pending_orders; ?></span>
                            <span class="stat-label"><?php _e('Ordini da Esportare', 'logistika-woocommerce'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="logistika-quick-actions">
                    <h2><?php _e('Azioni Rapide', 'logistika-woocommerce'); ?></h2>
                    <a href="<?php echo admin_url('admin.php?page=logistika-export'); ?>" class="button button-primary button-hero">
                        <span class="dashicons dashicons-download"></span> <?php _e('Esporta Ordini', 'logistika-woocommerce'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=logistika-settings'); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-admin-generic"></span> <?php _e('Impostazioni', 'logistika-woocommerce'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-list-view"></span> <?php _e('Tutti gli Ordini', 'logistika-woocommerce'); ?>
                    </a>
                </div>

                <div class="logistika-info">
                    <h2><?php _e('Informazioni Plugin', 'logistika-woocommerce'); ?></h2>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Versione', 'logistika-woocommerce'); ?></th>
                            <td><?php echo LOGISTIKA_VERSION; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Metodo Invio', 'logistika-woocommerce'); ?></th>
                            <td>
                                <?php
                                $methods = array('email' => 'Email CSV', 'api' => 'API REST', 'both' => 'Email + API');
                                echo esc_html($methods[$send_method] ?? $send_method);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('CodVet', 'logistika-woocommerce'); ?></th>
                            <td><?php echo esc_html(get_option('logistika_codvet', '40005')); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email Destinatario', 'logistika-woocommerce'); ?></th>
                            <td><?php echo esc_html(get_option('logistika_email_destinatario', get_option('admin_email'))); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Auto Export', 'logistika-woocommerce'); ?></th>
                            <td><?php echo get_option('logistika_auto_export', 'no') === 'yes' ? __('Attivo', 'logistika-woocommerce') : __('Disattivo', 'logistika-woocommerce'); ?></td>
                        </tr>
                        <?php if (in_array($send_method, array('api', 'both'))): ?>
                        <tr>
                            <th><?php _e('API', 'logistika-woocommerce'); ?></th>
                            <td>
                                <?php
                                $api = new Logistika_Api();
                                if ($api->is_configured()) {
                                    echo '<span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ' . __('Configurata', 'logistika-woocommerce');
                                } else {
                                    echo '<span class="dashicons dashicons-warning" style="color:#dc3232;"></span> ' . __('Non configurata', 'logistika-woocommerce');
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render export page
     */
    public function render_export_page() {
        $orders = $this->get_exportable_orders();
        $send_method = get_option('logistika_send_method', 'email');
        ?>
        <div class="wrap logistika-wrap">
            <h1><span class="dashicons dashicons-download"></span> <?php _e('Esporta Ordini', 'logistika-woocommerce'); ?></h1>

            <div class="logistika-export-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="logistika-export">

                    <label for="status"><?php _e('Stato:', 'logistika-woocommerce'); ?></label>
                    <select name="status" id="status">
                        <option value=""><?php _e('Tutti', 'logistika-woocommerce'); ?></option>
                        <?php foreach (wc_get_order_statuses() as $status => $label): ?>
                            <option value="<?php echo esc_attr($status); ?>" <?php selected(isset($_GET['status']) ? $_GET['status'] : '', $status); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="date_from"><?php _e('Da:', 'logistika-woocommerce'); ?></label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">

                    <label for="date_to"><?php _e('A:', 'logistika-woocommerce'); ?></label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">

                    <label>
                        <input type="checkbox" name="not_exported" value="1" <?php checked(isset($_GET['not_exported']) ? $_GET['not_exported'] : '', '1'); ?>>
                        <?php _e('Solo non esportati', 'logistika-woocommerce'); ?>
                    </label>

                    <button type="submit" class="button"><?php _e('Filtra', 'logistika-woocommerce'); ?></button>
                </form>
            </div>

            <form method="post" id="logistika-export-form">
                <?php wp_nonce_field('logistika_export_orders', 'logistika_nonce'); ?>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action" id="bulk-action-selector">
                            <option value=""><?php _e('Azioni di massa', 'logistika-woocommerce'); ?></option>
                            <?php if (in_array($send_method, array('email', 'both'))): ?>
                                <option value="export_email"><?php _e('Esporta e Invia Email', 'logistika-woocommerce'); ?></option>
                            <?php endif; ?>
                            <?php if (in_array($send_method, array('api', 'both'))): ?>
                                <option value="export_api"><?php _e('Invia via API', 'logistika-woocommerce'); ?></option>
                            <?php endif; ?>
                            <option value="export_download"><?php _e('Esporta e Scarica CSV', 'logistika-woocommerce'); ?></option>
                            <option value="preview"><?php _e('Anteprima CSV', 'logistika-woocommerce'); ?></option>
                        </select>
                        <button type="submit" class="button action"><?php _e('Applica', 'logistika-woocommerce'); ?></button>
                    </div>
                    <div class="alignright">
                        <span class="displaying-num"><?php printf(_n('%s ordine', '%s ordini', count($orders), 'logistika-woocommerce'), count($orders)); ?></span>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th><?php _e('Ordine', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Data', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Cliente', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Destinatario', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Totale', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Stato', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Esportato', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Azioni', 'logistika-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9"><?php _e('Nessun ordine trovato.', 'logistika-woocommerce'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $exported = $order->get_meta('_logistika_exported');
                                $api_sent = $order->get_meta('_logistika_api_sent');
                                $shipping_name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
                                if (empty(trim($shipping_name))) {
                                    $shipping_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                                }
                                ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="order_ids[]" value="<?php echo $order->get_id(); ?>">
                                    </th>
                                    <td>
                                        <strong><a href="<?php echo $order->get_edit_order_url(); ?>">#<?php echo $order->get_order_number(); ?></a></strong>
                                    </td>
                                    <td><?php echo $order->get_date_created()->date('d/m/Y H:i'); ?></td>
                                    <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></td>
                                    <td>
                                        <?php echo esc_html($shipping_name); ?><br>
                                        <small><?php echo esc_html($order->get_shipping_city() ?: $order->get_billing_city()); ?></small>
                                    </td>
                                    <td><?php echo $order->get_formatted_order_total(); ?></td>
                                    <td>
                                        <mark class="order-status status-<?php echo $order->get_status(); ?>">
                                            <span><?php echo wc_get_order_status_name($order->get_status()); ?></span>
                                        </mark>
                                    </td>
                                    <td>
                                        <?php if ($exported): ?>
                                            <span class="dashicons dashicons-email-alt" style="color: #46b450;" title="CSV: <?php echo esc_attr($exported); ?>"></span>
                                        <?php endif; ?>
                                        <?php if ($api_sent): ?>
                                            <span class="dashicons dashicons-rest-api" style="color: #46b450;" title="API: <?php echo esc_attr($api_sent); ?>"></span>
                                        <?php endif; ?>
                                        <?php if (!$exported && !$api_sent): ?>
                                            <span class="dashicons dashicons-minus" style="color: #ccc;"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small logistika-single-export" data-order-id="<?php echo $order->get_id(); ?>" title="<?php _e('Esporta', 'logistika-woocommerce'); ?>">
                                            <span class="dashicons dashicons-download"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <!-- Preview Modal -->
            <div id="logistika-preview-modal" class="logistika-modal" style="display:none;">
                <div class="logistika-modal-content">
                    <span class="logistika-modal-close">&times;</span>
                    <h2><?php _e('Anteprima CSV', 'logistika-woocommerce'); ?></h2>
                    <div id="logistika-preview-content"></div>
                </div>
            </div>
        </div>
        <?php

        // Handle form submission
        if (isset($_POST['logistika_nonce']) && wp_verify_nonce($_POST['logistika_nonce'], 'logistika_export_orders')) {
            $this->handle_export_form();
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $send_method = get_option('logistika_send_method', 'email');
        ?>
        <div class="wrap logistika-wrap">
            <h1><span class="dashicons dashicons-admin-generic"></span> <?php _e('Impostazioni Logistika', 'logistika-woocommerce'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('logistika_settings'); ?>

                <h2 class="title"><?php _e('Impostazioni Generali', 'logistika-woocommerce'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="logistika_codvet"><?php _e('CodVet (Codice Vettore)', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="logistika_codvet" id="logistika_codvet"
                                   value="<?php echo esc_attr(get_option('logistika_codvet', '40005')); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Codice identificativo del vettore (default: 40005)', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="logistika_send_method"><?php _e('Metodo di Invio', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select name="logistika_send_method" id="logistika_send_method">
                                <option value="email" <?php selected($send_method, 'email'); ?>><?php _e('Email con CSV', 'logistika-woocommerce'); ?></option>
                                <option value="api" <?php selected($send_method, 'api'); ?>><?php _e('API REST', 'logistika-woocommerce'); ?></option>
                                <option value="both" <?php selected($send_method, 'both'); ?>><?php _e('Entrambi (Email + API)', 'logistika-woocommerce'); ?></option>
                            </select>
                            <p class="description"><?php _e('Scegli come inviare gli ordini a Logistika', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="logistika_auto_export"><?php _e('Export Automatico', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select name="logistika_auto_export" id="logistika_auto_export">
                                <option value="no" <?php selected(get_option('logistika_auto_export', 'no'), 'no'); ?>><?php _e('Disattivo', 'logistika-woocommerce'); ?></option>
                                <option value="yes" <?php selected(get_option('logistika_auto_export', 'no'), 'yes'); ?>><?php _e('Attivo', 'logistika-woocommerce'); ?></option>
                            </select>
                            <p class="description"><?php _e('Esporta automaticamente quando un ordine raggiunge lo stato selezionato (usa il metodo di invio scelto sopra)', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="logistika_export_status"><?php _e('Stato per Export Automatico', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select name="logistika_export_status" id="logistika_export_status">
                                <?php foreach (wc_get_order_statuses() as $status => $label): ?>
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected(get_option('logistika_export_status', 'wc-processing'), $status); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="logistika_date_format"><?php _e('Formato Data CSV', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select name="logistika_date_format" id="logistika_date_format">
                                <option value="d/m/y" <?php selected(get_option('logistika_date_format', 'd/m/y'), 'd/m/y'); ?>>dd/mm/yy (<?php echo date('d/m/y'); ?>)</option>
                                <option value="d/m/Y" <?php selected(get_option('logistika_date_format', 'd/m/y'), 'd/m/Y'); ?>>dd/mm/yyyy (<?php echo date('d/m/Y'); ?>)</option>
                                <option value="Y-m-d" <?php selected(get_option('logistika_date_format', 'd/m/y'), 'Y-m-d'); ?>>yyyy-mm-dd (<?php echo date('Y-m-d'); ?>)</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="logistika_csv_separator"><?php _e('Separatore CSV', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select name="logistika_csv_separator" id="logistika_csv_separator">
                                <option value=";" <?php selected(get_option('logistika_csv_separator', ';'), ';'); ?>>Punto e virgola (;)</option>
                                <option value="," <?php selected(get_option('logistika_csv_separator', ';'), ','); ?>>Virgola (,)</option>
                                <option value="\t" <?php selected(get_option('logistika_csv_separator', ';'), "\t"); ?>>Tab</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2 class="title logistika-email-section"><?php _e('Configurazione Email', 'logistika-woocommerce'); ?></h2>

                <table class="form-table logistika-email-field">
                    <tr>
                        <th scope="row">
                            <label for="logistika_email_destinatario"><?php _e('Email Destinatario', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="logistika_email_destinatario" id="logistika_email_destinatario"
                                   value="<?php echo esc_attr(get_option('logistika_email_destinatario', get_option('admin_email'))); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Indirizzo email a cui inviare i file CSV', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="logistika_email_oggetto"><?php _e('Oggetto Email', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="logistika_email_oggetto" id="logistika_email_oggetto"
                                   value="<?php echo esc_attr(get_option('logistika_email_oggetto', 'Nuovo ordine Logistika - {order_id}')); ?>"
                                   class="regular-text">
                            <p class="description"><?php _e('Usa {order_id} per il numero ordine, {date} per la data', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2 class="title logistika-api-section"><?php _e('Configurazione API', 'logistika-woocommerce'); ?></h2>
                <p class="description logistika-api-field"><?php _e('Configura l\'integrazione API per inviare ordini direttamente a Logistika.', 'logistika-woocommerce'); ?></p>

                <table class="form-table">
                    <tr class="logistika-api-field">
                        <th scope="row">
                            <label for="logistika_api_url"><?php _e('URL API Logistika', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="logistika_api_url" id="logistika_api_url"
                                   value="<?php echo esc_attr(get_option('logistika_api_url', '')); ?>"
                                   class="regular-text" placeholder="https://tuodominio.com/logistika/app/api">
                            <p class="description"><?php _e('URL base dell\'API Logistika (es: https://tuodominio.com/logistika/app/api)', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>

                    <tr class="logistika-api-field">
                        <th scope="row">
                            <label for="logistika_api_key"><?php _e('API Key', 'logistika-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="logistika_api_key" id="logistika_api_key"
                                   value="<?php echo esc_attr(get_option('logistika_api_key', '')); ?>"
                                   class="regular-text code" placeholder="La tua API Key">
                            <p class="description"><?php _e('API Key fornita da Logistika per l\'autenticazione', 'logistika-woocommerce'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Salva Impostazioni', 'logistika-woocommerce')); ?>
            </form>

            <hr>

            <h2><?php _e('Test Connessione', 'logistika-woocommerce'); ?></h2>

            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
                <button type="button" id="logistika-test-email" class="button button-secondary">
                    <span class="dashicons dashicons-email-alt"></span> <?php _e('Test Email', 'logistika-woocommerce'); ?>
                </button>
                <button type="button" id="logistika-test-api" class="button button-secondary">
                    <span class="dashicons dashicons-rest-api"></span> <?php _e('Test API', 'logistika-woocommerce'); ?>
                </button>
                <span id="logistika-test-result"></span>
            </div>

            <?php
            // Show API status
            $api = new Logistika_Api();
            if ($api->is_configured()):
            ?>
            <div class="logistika-api-status">
                <h3><?php _e('Stato API', 'logistika-woocommerce'); ?></h3>
                <table class="widefat" style="max-width: 400px;">
                    <tr>
                        <th><?php _e('Configurata', 'logistika-woocommerce'); ?></th>
                        <td><span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span></td>
                    </tr>
                    <tr>
                        <th><?php _e('URL', 'logistika-woocommerce'); ?></th>
                        <td><code><?php echo esc_html(get_option('logistika_api_url')); ?></code></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            function toggleSendMethodFields() {
                var method = $('#logistika_send_method').val();
                if (method === 'email') {
                    $('.logistika-email-section, .logistika-email-field').show();
                    $('.logistika-api-section, .logistika-api-field').hide();
                } else if (method === 'api') {
                    $('.logistika-email-section, .logistika-email-field').hide();
                    $('.logistika-api-section, .logistika-api-field').show();
                } else {
                    // both
                    $('.logistika-email-section, .logistika-email-field').show();
                    $('.logistika-api-section, .logistika-api-field').show();
                }
            }
            toggleSendMethodFields();
            $('#logistika_send_method').on('change', toggleSendMethodFields);

            // Test API button
            $('#logistika-test-api').on('click', function() {
                var $btn = $(this);
                var $result = $('#logistika-test-result');

                $btn.prop('disabled', true);
                $result.html('<span class="spinner is-active" style="float:none;"></span>');

                $.ajax({
                    url: logistikaAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'logistika_test_api',
                        nonce: logistikaAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color:#46b450;">' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color:#dc3232;">' + (response.data || 'Errore') + '</span>');
                        }
                    },
                    error: function() {
                        $result.html('<span style="color:#dc3232;">Errore di connessione</span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render logs page
     */
    public function render_logs_page() {
        $logs = get_option('logistika_export_logs', array());
        ?>
        <div class="wrap logistika-wrap">
            <h1><span class="dashicons dashicons-list-view"></span> <?php _e('Log Export', 'logistika-woocommerce'); ?></h1>

            <?php if (empty($logs)): ?>
                <p><?php _e('Nessun export registrato.', 'logistika-woocommerce'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Data', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Metodo', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Destinazione', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Ordini', 'logistika-woocommerce'); ?></th>
                            <th><?php _e('Stato', 'logistika-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['date']); ?></td>
                                <td><?php echo esc_html($log['method'] ?? 'email'); ?></td>
                                <td><?php echo esc_html($log['email'] ?? ($log['destination'] ?? '—')); ?></td>
                                <td>
                                    <?php
                                    if (is_array($log['orders'])) {
                                        $order_links = array();
                                        foreach ($log['orders'] as $order_id) {
                                            $order = wc_get_order($order_id);
                                            if ($order) {
                                                $order_links[] = '<a href="' . $order->get_edit_order_url() . '">#' . $order->get_order_number() . '</a>';
                                            } else {
                                                $order_links[] = '#' . $order_id;
                                            }
                                        }
                                        echo implode(', ', $order_links);
                                    } else {
                                        echo '#' . $log['orders'];
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($log['success']): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> <?php _e('Inviato', 'logistika-woocommerce'); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: #dc3232;"></span> <?php _e('Errore', 'logistika-woocommerce'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add bulk action to orders list
     */
    public function add_bulk_action($actions) {
        $send_method = get_option('logistika_send_method', 'email');

        if (in_array($send_method, array('email', 'both'))) {
            $actions['logistika_export_email'] = __('Logistika - Esporta e Invia Email', 'logistika-woocommerce');
        }
        if (in_array($send_method, array('api', 'both'))) {
            $actions['logistika_export_api'] = __('Logistika - Invia via API', 'logistika-woocommerce');
        }
        $actions['logistika_export'] = __('Logistika - Scarica CSV', 'logistika-woocommerce');

        return $actions;
    }

    /**
     * Handle bulk action
     */
    public function handle_bulk_action($redirect_to, $action, $post_ids) {
        if ($action === 'logistika_export_email') {
            $csv_generator = new Logistika_CSV();
            $csv_content = $csv_generator->generate_for_orders($post_ids);

            $email_handler = new Logistika_Email();
            $sent = $email_handler->send($csv_content, $post_ids);

            $redirect_to = add_query_arg(array(
                'logistika_exported' => count($post_ids),
                'logistika_email_sent' => $sent ? 1 : 0
            ), $redirect_to);

        } elseif ($action === 'logistika_export_api') {
            $api = new Logistika_Api();
            $success_count = 0;

            foreach ($post_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order && $api->send_order($order)) {
                    $success_count++;
                }
            }

            $this->log_export($post_ids, 'api', 'API', $success_count === count($post_ids));

            $redirect_to = add_query_arg(array(
                'logistika_exported' => $success_count,
                'logistika_api_sent' => 1
            ), $redirect_to);

        } elseif ($action === 'logistika_export') {
            $csv_generator = new Logistika_CSV();
            $csv_content = $csv_generator->generate_for_orders($post_ids);

            $filepath = $csv_generator->save_to_file($csv_content);
            $redirect_to = add_query_arg(array(
                'logistika_exported' => count($post_ids),
                'logistika_download' => urlencode($filepath)
            ), $redirect_to);

        } else {
            return $redirect_to;
        }

        return $redirect_to;
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if (isset($_GET['logistika_exported'])) {
            $count = intval($_GET['logistika_exported']);
            $email_sent = isset($_GET['logistika_email_sent']) ? intval($_GET['logistika_email_sent']) : 0;
            $api_sent = isset($_GET['logistika_api_sent']) ? intval($_GET['logistika_api_sent']) : 0;

            if ($api_sent) {
                $message = sprintf(
                    _n('%d ordine inviato via API.', '%d ordini inviati via API.', $count, 'logistika-woocommerce'),
                    $count
                );
            } elseif ($email_sent) {
                $message = sprintf(
                    _n('%d ordine esportato e inviato via email.', '%d ordini esportati e inviati via email.', $count, 'logistika-woocommerce'),
                    $count
                );
            } else {
                $message = sprintf(
                    _n('%d ordine esportato.', '%d ordini esportati.', $count, 'logistika-woocommerce'),
                    $count
                );
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    /**
     * Add order action
     */
    public function add_order_action($actions) {
        $actions['logistika_export'] = __('Esporta per Logistika', 'logistika-woocommerce');
        return $actions;
    }

    /**
     * Process order action (from WC order actions dropdown)
     */
    public function process_order_action($order) {
        $this->send_order_by_method($order);
    }

    /**
     * Add meta box to order
     */
    public function add_meta_box() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'logistika_export_box',
            __('Logistika Export', 'logistika-woocommerce'),
            array($this, 'render_meta_box'),
            $screen,
            'side',
            'default'
        );
    }

    /**
     * Render meta box
     */
    public function render_meta_box($post_or_order) {
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;

        if (!$order) {
            return;
        }

        $exported = $order->get_meta('_logistika_exported');
        $export_email = $order->get_meta('_logistika_export_email');
        $api_sent = $order->get_meta('_logistika_api_sent');
        $api_order_id = $order->get_meta('_logistika_api_order_id');
        $send_method = get_option('logistika_send_method', 'email');
        ?>
        <div class="logistika-meta-box">
            <?php if ($exported): ?>
                <p>
                    <span class="dashicons dashicons-email-alt" style="color: #46b450;"></span>
                    <strong><?php _e('CSV Esportato:', 'logistika-woocommerce'); ?></strong><br>
                    <?php echo esc_html(date('d/m/Y H:i', strtotime($exported))); ?><br>
                    <small><?php echo esc_html($export_email); ?></small>
                </p>
            <?php endif; ?>

            <?php if ($api_sent): ?>
                <p>
                    <span class="dashicons dashicons-rest-api" style="color: #46b450;"></span>
                    <strong><?php _e('Inviato via API:', 'logistika-woocommerce'); ?></strong><br>
                    <?php echo esc_html(date('d/m/Y H:i', strtotime($api_sent))); ?><br>
                    <?php if ($api_order_id): ?>
                        <small>ID Logistika: #<?php echo esc_html($api_order_id); ?></small>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (!$exported && !$api_sent): ?>
                <p>
                    <span class="dashicons dashicons-minus"></span>
                    <?php _e('Non ancora esportato', 'logistika-woocommerce'); ?>
                </p>
            <?php endif; ?>

            <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                <?php if (in_array($send_method, array('email', 'both'))): ?>
                    <button type="button" class="button button-primary logistika-export-btn" data-order-id="<?php echo $order->get_id(); ?>">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php _e('Invia Email', 'logistika-woocommerce'); ?>
                    </button>
                <?php endif; ?>

                <?php if (in_array($send_method, array('api', 'both'))): ?>
                    <button type="button" class="button button-primary logistika-api-send-btn" data-order-id="<?php echo $order->get_id(); ?>">
                        <span class="dashicons dashicons-rest-api"></span>
                        <?php _e('Invia API', 'logistika-woocommerce'); ?>
                    </button>
                <?php endif; ?>

                <button type="button" class="button logistika-preview-btn" data-order-id="<?php echo $order->get_id(); ?>">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Anteprima', 'logistika-woocommerce'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX export order (via email)
     */
    public function ajax_export_order() {
        check_ajax_referer('logistika_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permessi insufficienti', 'logistika-woocommerce'));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(__('ID ordine non valido', 'logistika-woocommerce'));
        }

        $csv_generator = new Logistika_CSV();
        $csv_content = $csv_generator->generate_for_order($order_id);

        if (!$csv_content) {
            wp_send_json_error(__('Errore generazione CSV', 'logistika-woocommerce'));
        }

        $email_handler = new Logistika_Email();
        $sent = $email_handler->send($csv_content, $order_id);

        if ($sent) {
            wp_send_json_success(array(
                'message' => __('Ordine esportato e inviato via email', 'logistika-woocommerce'),
                'exported_date' => current_time('d/m/Y H:i')
            ));
        } else {
            wp_send_json_error(__('Errore invio email', 'logistika-woocommerce'));
        }
    }

    /**
     * AJAX download CSV
     */
    public function ajax_download_csv() {
        check_ajax_referer('logistika_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permessi insufficienti', 'logistika-woocommerce'));
        }

        $order_ids = isset($_GET['order_ids']) ? array_map('intval', explode(',', $_GET['order_ids'])) : array();

        if (empty($order_ids)) {
            wp_die(__('Nessun ordine selezionato', 'logistika-woocommerce'));
        }

        $csv_generator = new Logistika_CSV();
        $csv_content = $csv_generator->generate_for_orders($order_ids);

        $filename = 'logistika-export-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv_content;
        exit;
    }

    /**
     * AJAX preview CSV
     */
    public function ajax_preview_csv() {
        check_ajax_referer('logistika_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permessi insufficienti', 'logistika-woocommerce'));
        }

        $order_ids = isset($_POST['order_ids']) ? array_map('intval', (array)$_POST['order_ids']) : array();

        if (empty($order_ids)) {
            wp_send_json_error(__('Nessun ordine selezionato', 'logistika-woocommerce'));
        }

        $csv_generator = new Logistika_CSV();
        $csv_content = $csv_generator->generate_for_orders($order_ids);

        // Remove BOM for parsing
        $csv_content = ltrim($csv_content, "\xEF\xBB\xBF");

        // Convert to HTML table for preview
        $separator = get_option('logistika_csv_separator', ';');
        $lines = explode("\n", trim($csv_content));
        $html = '<div class="logistika-csv-preview"><table class="widefat">';

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $cells = explode($separator, $line);
            $tag = $i === 0 ? 'th' : 'td';

            $html .= '<tr>';
            foreach ($cells as $cell) {
                $html .= '<' . $tag . '>' . esc_html($cell) . '</' . $tag . '>';
            }
            $html .= '</tr>';
        }

        $html .= '</table></div>';

        wp_send_json_success(array('html' => $html));
    }

    /**
     * AJAX test API connection
     */
    public function ajax_test_api() {
        check_ajax_referer('logistika_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permessi insufficienti', 'logistika-woocommerce'));
        }

        $api = new Logistika_Api();

        if (!$api->is_configured()) {
            wp_send_json_error(__('API non configurata. Inserisci URL e API Key.', 'logistika-woocommerce'));
        }

        if ($api->test_connection()) {
            wp_send_json_success(array(
                'message' => __('Connessione API riuscita!', 'logistika-woocommerce')
            ));
        } else {
            wp_send_json_error(__('Errore connessione API: ', 'logistika-woocommerce') . $api->get_last_error());
        }
    }

    /**
     * AJAX send order via API
     */
    public function ajax_send_via_api() {
        check_ajax_referer('logistika_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permessi insufficienti', 'logistika-woocommerce'));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(__('ID ordine non valido', 'logistika-woocommerce'));
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(__('Ordine non trovato', 'logistika-woocommerce'));
        }

        $api = new Logistika_Api();

        if (!$api->is_configured()) {
            wp_send_json_error(__('API non configurata', 'logistika-woocommerce'));
        }

        $result = $api->send_order($order);

        if ($result) {
            $this->log_export(array($order_id), 'api', 'API', true);

            wp_send_json_success(array(
                'message' => __('Ordine inviato via API', 'logistika-woocommerce'),
                'logistika_id' => $result['id'] ?? null
            ));
        } else {
            wp_send_json_error(__('Errore invio API: ', 'logistika-woocommerce') . $api->get_last_error());
        }
    }

    /**
     * AJAX test email
     */
    public function ajax_test_email() {
        check_ajax_referer('logistika_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permessi insufficienti', 'logistika-woocommerce'));
        }

        // Get the most recent order for testing
        $orders = wc_get_orders(array(
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids'
        ));

        if (empty($orders)) {
            wp_send_json_error(__('Nessun ordine disponibile per il test', 'logistika-woocommerce'));
        }

        $order_id = $orders[0];
        $csv_generator = new Logistika_CSV();
        $csv_content = $csv_generator->generate_for_order($order_id);

        if (!$csv_content) {
            wp_send_json_error(__('Errore generazione CSV', 'logistika-woocommerce'));
        }

        $email_handler = new Logistika_Email();
        $sent = $email_handler->send($csv_content, $order_id);

        if ($sent) {
            $to = get_option('logistika_email_destinatario', get_option('admin_email'));
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Email di test inviata a %s (ordine #%s)', 'logistika-woocommerce'),
                    $to,
                    $order_id
                )
            ));
        } else {
            wp_send_json_error(__('Errore invio email di test', 'logistika-woocommerce'));
        }
    }

    /**
     * Auto export on status change (unified handler)
     *
     * Respects the send_method setting: email, api, or both.
     */
    public function maybe_auto_export($order_id, $old_status, $new_status, $order) {
        if (get_option('logistika_auto_export', 'no') !== 'yes') {
            return;
        }

        $target_status = get_option('logistika_export_status', 'wc-processing');
        $target_status = str_replace('wc-', '', $target_status);

        if ($new_status !== $target_status) {
            return;
        }

        $send_method = get_option('logistika_send_method', 'email');

        // Send via email (CSV)
        if (in_array($send_method, array('email', 'both'))) {
            if (!$order->get_meta('_logistika_exported')) {
                $csv_generator = new Logistika_CSV();
                $csv_content = $csv_generator->generate_for_order($order);

                $email_handler = new Logistika_Email();
                $email_handler->send($csv_content, $order_id);
            }
        }

        // Send via API
        if (in_array($send_method, array('api', 'both'))) {
            if (!$order->get_meta('_logistika_api_sent')) {
                $api = new Logistika_Api();
                if ($api->is_configured()) {
                    $result = $api->send_order($order);
                    if ($result) {
                        $this->log_export(array($order_id), 'api', 'API', true);
                    }
                }
            }
        }
    }

    /**
     * Send order using configured method
     *
     * @param WC_Order $order
     */
    private function send_order_by_method($order) {
        $send_method = get_option('logistika_send_method', 'email');

        if (in_array($send_method, array('email', 'both'))) {
            $csv_generator = new Logistika_CSV();
            $csv_content = $csv_generator->generate_for_order($order);
            $email_handler = new Logistika_Email();
            $email_handler->send($csv_content, $order->get_id());
        }

        if (in_array($send_method, array('api', 'both'))) {
            $api = new Logistika_Api();
            if ($api->is_configured()) {
                $result = $api->send_order($order);
                if ($result) {
                    $this->log_export(array($order->get_id()), 'api', 'API', true);
                }
            }
        }
    }

    /**
     * Add settings link
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=logistika-settings') . '">' . __('Impostazioni', 'logistika-woocommerce') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Get exportable orders
     */
    private function get_exportable_orders() {
        $args = array(
            'limit' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        );

        // Apply filters
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $args['status'] = sanitize_text_field($_GET['status']);
        }

        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
            $args['date_created'] = '>=' . sanitize_text_field($_GET['date_from']);
        }

        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
            if (isset($args['date_created'])) {
                $args['date_created'] .= '...' . sanitize_text_field($_GET['date_to']);
            } else {
                $args['date_created'] = '<=' . sanitize_text_field($_GET['date_to']);
            }
        }

        $orders = wc_get_orders($args);

        // Filter by not exported if needed
        if (isset($_GET['not_exported']) && $_GET['not_exported'] === '1') {
            $orders = array_filter($orders, function($order) {
                return empty($order->get_meta('_logistika_exported')) && empty($order->get_meta('_logistika_api_sent'));
            });
        }

        return $orders;
    }

    /**
     * Get exports count
     */
    private function get_exports_count($period = 'today') {
        $logs = get_option('logistika_export_logs', array());
        $count = 0;

        $today = date('Y-m-d');
        $week_ago = date('Y-m-d', strtotime('-7 days'));

        foreach ($logs as $log) {
            $log_date = date('Y-m-d', strtotime($log['date']));

            if ($period === 'today' && $log_date === $today) {
                $count++;
            } elseif ($period === 'week' && $log_date >= $week_ago) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get pending orders count
     */
    private function get_pending_orders_count() {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold'),
            'return' => 'ids',
            'limit' => -1,
            'meta_query' => array(
                array(
                    'key' => '_logistika_exported',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        return count($orders);
    }

    /**
     * Handle export form
     */
    private function handle_export_form() {
        $order_ids = isset($_POST['order_ids']) ? array_map('intval', $_POST['order_ids']) : array();
        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';

        if (empty($order_ids)) {
            return;
        }

        if ($action === 'export_email') {
            $csv_generator = new Logistika_CSV();
            $csv_content = $csv_generator->generate_for_orders($order_ids);
            $email_handler = new Logistika_Email();
            $email_handler->send($csv_content, $order_ids);
            echo '<div class="notice notice-success"><p>' . __('Export inviato via email con successo!', 'logistika-woocommerce') . '</p></div>';

        } elseif ($action === 'export_api') {
            $api = new Logistika_Api();
            $success_count = 0;

            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order && $api->send_order($order)) {
                    $success_count++;
                }
            }

            $this->log_export($order_ids, 'api', 'API', $success_count === count($order_ids));

            echo '<div class="notice notice-success"><p>' . sprintf(
                __('%d ordini inviati via API con successo!', 'logistika-woocommerce'),
                $success_count
            ) . '</p></div>';

        } elseif ($action === 'export_download') {
            $csv_generator = new Logistika_CSV();
            $csv_content = $csv_generator->generate_for_orders($order_ids);
            $filepath = $csv_generator->save_to_file($csv_content);
            $upload_dir = wp_upload_dir();
            $download_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $filepath);
            echo '<div class="notice notice-success"><p>' . __('Export completato!', 'logistika-woocommerce') . ' <a href="' . esc_url($download_url) . '" download>' . __('Scarica CSV', 'logistika-woocommerce') . '</a></p></div>';
        }
    }

    /**
     * Log an export
     *
     * @param array $order_ids
     * @param string $method email|api
     * @param string $destination Email address or 'API'
     * @param bool $success
     */
    private function log_export($order_ids, $method, $destination, $success) {
        if (!is_array($order_ids)) {
            $order_ids = array($order_ids);
        }

        $log_entry = array(
            'date' => current_time('mysql'),
            'method' => $method,
            'email' => $destination,
            'success' => $success,
            'orders' => $order_ids
        );

        $logs = get_option('logistika_export_logs', array());
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 100);
        update_option('logistika_export_logs', $logs);
    }
}
