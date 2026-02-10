<?php
/**
 * Logistika GitHub Updater
 *
 * Enables automatic updates from GitHub repository
 *
 * @package Logistika_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Logistika_Updater {

    /**
     * GitHub repository
     */
    private $repo = 'jwebmodica/logistika-woocommerce';

    /**
     * Plugin slug
     */
    private $slug;

    /**
     * Plugin data
     */
    private $plugin_data;

    /**
     * GitHub API result
     */
    private $github_api_result;

    /**
     * Constructor
     */
    public function __construct() {
        $this->slug = plugin_basename(LOGISTIKA_PLUGIN_DIR . 'logistika-woocommerce.php');

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Add update check link
        add_filter('plugin_action_links_' . $this->slug, array($this, 'add_check_update_link'));

        // Handle manual update check from plugins page
        add_action('admin_init', array($this, 'handle_check_update'));
    }

    /**
     * Get plugin data
     */
    private function get_plugin_data() {
        if (!$this->plugin_data) {
            $this->plugin_data = get_plugin_data(LOGISTIKA_PLUGIN_DIR . 'logistika-woocommerce.php');
        }
        return $this->plugin_data;
    }

    /**
     * Get GitHub release info
     */
    private function get_github_release() {
        if (!empty($this->github_api_result)) {
            return $this->github_api_result;
        }

        $url = "https://api.github.com/repos/{$this->repo}/releases/latest";

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $this->github_api_result = json_decode($body);

        return $this->github_api_result;
    }

    /**
     * Check for updates
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();

        if (!$release) {
            return $transient;
        }

        $plugin_data = $this->get_plugin_data();
        $current_version = $plugin_data['Version'];

        // Remove 'v' prefix from tag name if present
        $github_version = ltrim($release->tag_name, 'v');

        // Compare versions
        if (version_compare($github_version, $current_version, '>')) {
            $package = $release->zipball_url;

            // Check for asset with specific name
            if (!empty($release->assets)) {
                foreach ($release->assets as $asset) {
                    if (strpos($asset->name, '.zip') !== false) {
                        $package = $asset->browser_download_url;
                        break;
                    }
                }
            }

            $transient->response[$this->slug] = (object) array(
                'slug' => dirname($this->slug),
                'plugin' => $this->slug,
                'new_version' => $github_version,
                'url' => $release->html_url,
                'package' => $package,
                'icons' => array(),
                'banners' => array(),
                'banners_rtl' => array(),
                'tested' => '',
                'requires_php' => '7.4',
                'compatibility' => new stdClass(),
            );
        }

        return $transient;
    }

    /**
     * Plugin info for update details
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }

        $release = $this->get_github_release();

        if (!$release) {
            return $result;
        }

        $plugin_data = $this->get_plugin_data();

        return (object) array(
            'name' => $plugin_data['Name'],
            'slug' => dirname($this->slug),
            'version' => ltrim($release->tag_name, 'v'),
            'author' => $plugin_data['Author'],
            'author_profile' => $plugin_data['AuthorURI'],
            'homepage' => $plugin_data['PluginURI'],
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'description' => $plugin_data['Description'],
                'changelog' => $this->parse_changelog($release->body),
            ),
            'download_link' => $release->zipball_url,
            'last_updated' => $release->published_at,
            'requires' => '5.8',
            'requires_php' => '7.4',
            'tested' => get_bloginfo('version'),
        );
    }

    /**
     * Parse changelog from release body
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return __('Nessuna nota di rilascio disponibile.', 'logistika-woocommerce');
        }

        // Convert markdown to HTML
        $changelog = nl2br(esc_html($body));
        $changelog = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $changelog);
        $changelog = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $changelog);
        $changelog = preg_replace('/^- (.*)$/m', '<li>$1</li>', $changelog);
        $changelog = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $changelog);

        return $changelog;
    }

    /**
     * After install - fix folder name
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Check if this is our plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->slug) {
            return $result;
        }

        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->slug);
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;

        // Reactivate plugin
        if (is_plugin_active($this->slug)) {
            activate_plugin($this->slug);
        }

        return $result;
    }

    /**
     * Add check update link
     */
    public function add_check_update_link($links) {
        $update_link = '<a href="' . wp_nonce_url(admin_url('plugins.php?logistika_check_update=1'), 'logistika_check_update') . '">' . __('Verifica Aggiornamenti', 'logistika-woocommerce') . '</a>';
        $links[] = $update_link;
        return $links;
    }

    /**
     * Handle manual update check - clear transient and redirect to plugins page
     */
    public function handle_check_update() {
        if (!isset($_GET['logistika_check_update'])) {
            return;
        }

        check_admin_referer('logistika_check_update');

        if (!current_user_can('update_plugins')) {
            return;
        }

        // Clear update transient to force re-check
        delete_site_transient('update_plugins');

        // Redirect back to plugins page
        wp_safe_redirect(admin_url('plugins.php?logistika_updated=1'));
        exit;
    }

}
