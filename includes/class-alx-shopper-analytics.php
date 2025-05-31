<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Alx_Shopper_Analytics {
    private $log_file;
    private $max_log_size = 1048576; // 1MB

    public function __construct() {
        $this->log_file = plugin_dir_path(__DIR__) . 'analytics.log';

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_alx_shopper_log_interaction', array($this, 'ajax_log_interaction'));
        add_action('wp_ajax_nopriv_alx_shopper_log_interaction', array($this, 'ajax_log_interaction'));
    }

    public function enqueue_scripts() {
        // Only enqueue if not already enqueued
        if (!wp_style_is('alx-shopper-style', 'enqueued')) {
            wp_enqueue_style(
                'alx-shopper-style',
                plugins_url('assets/css/shopper-style.css', dirname(__DIR__) . '/alx-shopper.php')
            );
        }
        if (!wp_script_is('alx-shopper-frontend', 'enqueued')) {
            wp_enqueue_script(
                'alx-shopper-frontend',
                plugins_url('assets/js/alx-shopper-frontend.js', dirname(__DIR__) . '/alx-shopper.php'),
                ['jquery'],
                ALX_SHOPPER_VERSION,
                true
            );
        }
        wp_localize_script('alx-shopper-frontend', 'alxShopperAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        ));
    }

    public function ajax_log_interaction() {
        $data = $this->sanitize_array($_POST);
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
        $timestamp = date('c'); // ISO 8601

        $log_entry = json_encode([
            'timestamp' => $timestamp,
            'ip' => $ip_address,
            'data' => $data,
        ]) . PHP_EOL;

        // Clear log if too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            file_put_contents($this->log_file, "");
        }

        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);

        wp_send_json_success(['logged' => true]);
    }

    private function sanitize_array($array) {
        $sanitized = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize_array($value);
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }

    public function get_analytics_data() {
        if (file_exists($this->log_file) && current_user_can('manage_options')) {
            return file_get_contents($this->log_file);
        }
        return '';
    }

    // Optional: Clear the log file (admin only)
    public function clear_log() {
        if (current_user_can('manage_options')) {
            file_put_contents($this->log_file, '');
            return true;
        }
        return false;
    }
}
?>