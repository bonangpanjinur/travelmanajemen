<?php
// Lokasi: wp-content/plugins/umroh-manager-headless/includes/api/api-finance.php

if (!defined('ABSPATH')) exit;

/**
 * [FUNGSI TRIGGER / BUKU BESAR OTOMATIS]
 * Dipanggil oleh modul lain (Manifest) untuk mencatat Pemasukan/Pengeluaran
 * ke buku besar keuangan kantor.
 */
function umroh_trigger_finance_entry($type, $amount, $description, $manifest_id = null, $user_id = null) {
    global $wpdb;

    // Validasi tipe
    $allowed_types = ['Pemasukan', 'Gaji', 'Kasbon', 'Operasional', 'Refund'];
    if (!in_array($type, $allowed_types)) {
        return false;
    }
    
    $result = $wpdb->insert($wpdb->prefix . 'umroh_finance', [
        'type' => $type,
        'amount' => floatval($amount),
        'description' => sanitize_text_field($description),
        'manifest_id' => $manifest_id ? intval($manifest_id) : null,
        'user_id' => $user_id ? intval($user_id) : null,
        'date' => current_time('Y-m-d'),
        'created_at' => current_time('mysql')
    ]);

    // Catat log
    if ($result) {
        $new_id = $wpdb->insert_id;
        umroh_log_activity('TRIGGER_FINANCE', $new_id, "Buku Besar: " . $type . " - " . $amount);
        return true;
    }
    return false;
}

/**
 * GET /umroh/v1/finance
 * Mengambil data keuangan (Gaji, Kasbon, Operasional) - Hanya Admin
 */
function umroh_get_finance_data($request) {
    global $wpdb;
    
    // Hanya Admin
    if (!umroh_check_permission_admin()) {
        return new WP_Error('forbidden', 'Hanya admin yang bisa melihat data keuangan', ['status' => 403]);
    }

    $data = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}umroh_finance ORDER BY date DESC");
    return new WP_REST_Response($data, 200);
}

/**
 * POST /umroh/v1/finance
 * Mencatat entri keuangan manual (Gaji, Kasbon, Operasional) - Hanya Admin
 */
function umroh_create_finance_entry($request) {
    global $wpdb;
    $p = $request->get_json_params();

    // Hanya Admin
    if (!umroh_check_permission_admin()) {
        return new WP_Error('forbidden', 'Hanya admin yang bisa input data keuangan', ['status' => 403]);
    }

    // Validasi tipe manual (tidak boleh Pemasukan/Refund yg otomatis)
    $allowed_types = ['Gaji', 'Kasbon', 'Operasional'];
    if (!isset($p['type']) || !in_array($p['type'], $allowed_types)) {
        return new WP_Error('bad_type', 'Tipe manual hanya boleh Gaji, Kasbon, Operasional', ['status' => 400]);
    }
    
    if (empty($p['amount']) || !is_numeric($p['amount'])) {
         return new WP_Error('bad_amount', 'Nominal tidak valid', ['status' => 400]);
    }
    
    $result = $wpdb->insert($wpdb->prefix . 'umroh_finance', [
        'type' => sanitize_text_field($p['type']), 
        'amount' => floatval($p['amount']),
        'description' => sanitize_text_field($p['description']),
        'user_id' => isset($p['user_id']) ? intval($p['user_id']) : null, // Karyawan yg kasbon/gaji
        'date' => $p['date'],
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        $new_id = $wpdb->insert_id;
        umroh_log_activity('CREATE_FINANCE', $new_id, "Catat: " . $p['type'] . " - " . $p['amount']);
        return new WP_REST_Response(['success' => true, 'id' => $new_id], 201);
    } else {
        return new WP_Error('db_error', 'Gagal simpan data keuangan', ['status' => 500]);
    }
}
?>