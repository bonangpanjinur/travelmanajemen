<?php
// File: includes/utils.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Fungsi utilitas umum
function umh_get_package_details($package_id) {
    global $wpdb;
    // PERBAIKAN: Menggunakan prefix tabel yang konsisten
    $package_table = $wpdb->prefix . 'umh_packages'; 
    $package = $wpdb->get_row($wpdb->prepare("SELECT * FROM $package_table WHERE id = %d", $package_id));
    return $package;
}

function umh_log_activity($user_id, $action, $details) {
    global $wpdb;
    // PERBAIKAN: Menggunakan prefix tabel yang konsisten
    $log_table = $wpdb->prefix . 'umh_logs';
    
    $wpdb->insert(
        $log_table,
        [
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'log_time' => current_time('mysql', 1)
        ],
        ['%d', '%s', '%s', '%s']
    );
}

/**
 * Pemeriksa Izin API Global (SANGAT PENTING)
 *
 * Fungsi ini menangani dua skenario login:
 * 1. Super Admin: Terautentikasi via Cookie + Nonce WordPress.
 * 2. Owner/Karyawan: Terautentikasi via Bearer Token dari tabel umh_users.
 */
function umh_check_api_permission(WP_REST_Request $request) {
    
    // Skenario 1: Cek jika ini adalah Admin WP yang diautentikasi
    // 'manage_options' adalah kapabilitas Super Admin
    if (current_user_can('manage_options')) {
        // Verifikasi nonce untuk keamanan tambahan terhadap CSRF
        $nonce = $request->get_header('X-WP-Nonce');
        if (wp_verify_nonce($nonce, 'wp_rest')) {
            // Ini adalah Super Admin yang sah dari dalam dashboard WP
            return true;
        }
        // Jika nonce tidak ada, mungkin ini panggilan dari luar (seperti app mobile)
        // Lanjutkan ke pemeriksaan token di bawah.
    }

    // Skenario 2: Cek jika ada Token Bearer (untuk Karyawan/Owner)
    $auth_header = $request->get_header('Authorization');
    
    if (empty($auth_header)) {
        return new WP_Error('rest_unauthorized', 'Header otorisasi tidak ditemukan.', ['status' => 401]);
    }

    // Harapkan format "Bearer <token>"
    if (strpos($auth_header, ' ') === false) {
        return new WP_Error('rest_unauthorized', 'Format header otorisasi tidak valid.', ['status' => 401]);
    }
    
    list($type, $token) = explode(' ', $auth_header, 2);
    
    if (strcasecmp($type, 'Bearer') !== 0 || empty($token)) {
        return new WP_Error('rest_unauthorized', 'Skema otorisasi tidak valid atau token kosong.', ['status' => 401]);
    }

    // 3. Verifikasi token di database kustom kita
    global $wpdb;
    // PERBAIKAN: Merujuk ke tabel umh_users yang baru
    $table_name = $wpdb->prefix . 'umh_users';
    
    // Cari user berdasarkan auth_token
    $user = $wpdb->get_row($wpdb->prepare("SELECT user_id, role FROM $table_name WHERE auth_token = %s", $token));

    if (empty($user)) {
        return new WP_Error('rest_invalid_token', 'Token tidak valid atau kedaluwarsa.', ['status' => 401]);
    }

    // Jika token valid, izinkan akses
    return true;
}

?>