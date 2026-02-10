<?php
/**
 * Plugin Name: Logistika WooCommerce
 * Plugin URI: https://github.com/logistika-dev/logistika-woocommerce
 * Description: Esporta ordini WooCommerce in formato CSV per la logistica e li invia via email o API REST a Logistika.
 * Version: 1.0.0
 * Author: Logistika
 * Author URI: https://logistika.it
 * Text Domain: logistika-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * GitHub Plugin URI: https://github.com/jwebmodica/logistika-woocommerce
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LOGISTIKA_VERSION', '1.0.0');
define('LOGISTIKA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOGISTIKA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LOGISTIKA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function logistika_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'logistika_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function logistika_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('Logistika WooCommerce richiede WooCommerce per funzionare. Installa e attiva WooCommerce.', 'logistika-woocommerce'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function logistika_init() {
    if (!logistika_check_woocommerce()) {
        return;
    }

    // Load text domain
    load_plugin_textdomain('logistika-woocommerce', false, dirname(LOGISTIKA_PLUGIN_BASENAME) . '/languages');

    // Include required files
    require_once LOGISTIKA_PLUGIN_DIR . 'includes/class-logistika-csv.php';
    require_once LOGISTIKA_PLUGIN_DIR . 'includes/class-logistika-email.php';
    require_once LOGISTIKA_PLUGIN_DIR . 'includes/class-logistika-api.php';
    require_once LOGISTIKA_PLUGIN_DIR . 'includes/class-logistika-admin.php';
    require_once LOGISTIKA_PLUGIN_DIR . 'includes/class-logistika-updater.php';

    // Initialize classes
    new Logistika_Admin();

    // Initialize updater
    new Logistika_Updater();
}
add_action('plugins_loaded', 'logistika_init');

/**
 * Activation hook
 */
function logistika_activate() {
    // Set default options
    $defaults = array(
        'codvet' => '40005',
        'email_destinatario' => get_option('admin_email'),
        'email_oggetto' => 'Nuovo ordine Logistika - {order_id}',
        'auto_export' => 'no',
        'export_status' => 'processing',
        'date_format' => 'd/m/y',
        'csv_separator' => ';',
        'send_method' => 'email',
        'api_url' => '',
        'api_key' => '',
    );

    foreach ($defaults as $key => $value) {
        if (get_option('logistika_' . $key) === false) {
            add_option('logistika_' . $key, $value);
        }
    }

    // Create export directory
    $upload_dir = wp_upload_dir();
    $logistika_dir = $upload_dir['basedir'] . '/logistika-exports';
    if (!file_exists($logistika_dir)) {
        wp_mkdir_p($logistika_dir);
        // Add .htaccess to protect files
        file_put_contents($logistika_dir . '/.htaccess', 'deny from all');
    }
}
register_activation_hook(__FILE__, 'logistika_activate');

/**
 * Deactivation hook
 */
function logistika_deactivate() {
    // Cleanup transients
    delete_transient('logistika_github_release');
}
register_deactivation_hook(__FILE__, 'logistika_deactivate');

/**
 * HPOS compatibility declaration
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
