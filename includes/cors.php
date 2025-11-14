<?php
// File: includes/cors.php
// Mengaktifkan CORS Dinamis berdasarkan pengaturan di database

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class UMH_CORS {

    public function __construct() {
        // Hook ini akan menambahkan header
    }

    public function add_cors_headers() {
        remove_filter('rest_pre_serve_request', 'rest_send_nocache_headers');
        add_filter('rest_pre_serve_request', array($this, 'send_cors_headers'));
    }

    /**
     * Mengirim header CORS secara dinamis.
     */
    public function send_cors_headers($value) {
        // Ambil pengaturan 'Allowed Origins' dari database
        $options = get_option('umh_settings');
        $allowed_origins_raw = $options['allowed_origins'] ?? '';
        
        // Ubah string textarea menjadi array
        $allowed_origins = array_filter(array_map('trim', explode("\n", $allowed_origins_raw)));

        // Tambahkan home_url() dan admin_url() sebagai default yang selalu diizinkan
        $allowed_origins[] = home_url();
        $allowed_origins[] = admin_url();
        
        // Ambil origin dari request yang masuk
        $origin = get_http_origin();
        
        // Jika origin yang masuk ada di dalam daftar, izinkan
        if ($origin && in_array($origin, $allowed_origins)) {
            // Kirim kembali origin yang spesifik, ini lebih aman daripada '*'
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        } else {
            // Jika tidak ada di daftar, jangan kirim header (blokir)
            // (Kita bisa log atau biarkan saja)
        }

        // Header lain yang diperlukan
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        
        // Jika ini adalah preflight request (OPTIONS), kirim response OK
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }

        return $value;
    }
}