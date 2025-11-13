<?php
// Lokasi: wp-content/plugins/umroh-manager-headless/includes/api/api-manifest.php

if (!defined('ABSPATH')) exit;

/**
 * GET /umroh/v1/manifest
 * Mengambil daftar semua jemaah (sudah di-JOIN dengan nama paket)
 */
function umroh_get_manifest($request) {
    global $wpdb;
    $manifest_table = $wpdb->prefix . 'umroh_manifest';
    $packages_table = $wpdb->prefix . 'uhp_packages';

    $sql = $wpdb->prepare("
        SELECT 
            m.*, 
            p.title as package_name, 
            p.price_details 
        FROM {$manifest_table} as m
        LEFT JOIN {$packages_table} as p ON m.package_id = p.id
        ORDER BY m.created_at DESC
    ");
    $results = $wpdb->get_results($sql);
    
    // Decode price_details JSON untuk setiap result
    if ($results) {
        foreach ($results as $key => $result) {
            if (is_string($result->price_details)) {
                $results[$key]->price_details = json_decode($result->price_details);
            }
        }
    }
    
    return new WP_REST_Response($results, 200);
}

/**
 * POST /umroh/v1/manifest
 * Membuat jemaah baru
 */
function umroh_create_manifest($request) {
    global $wpdb;
    $p = $request->get_json_params();

    // Validasi data
    if (empty($p['full_name']) || empty($p['package_id']) || empty($p['final_price'])) {
        return new WP_Error('missing_fields', 'Nama, Paket, dan Harga Wajib diisi', ['status' => 400]);
    }

    $result = $wpdb->insert(
        $wpdb->prefix . 'umroh_manifest',
        [
            'full_name'      => sanitize_text_field($p['full_name']),
            'passport_no'    => sanitize_text_field($p['passport_no']),
            'package_id'     => intval($p['package_id']),
            'final_price'    => floatval($p['final_price']),
            'payment_status' => sanitize_text_field($p['payment_status']),
            'visa_status'    => 'Belum Submit', // Default
            'equipment_taken'=> 0, // Default
            'status'         => 'Active', // Default
            'created_at'     => current_time('mysql')
        ]
    );
    
    if ($result) {
        $new_id = $wpdb->insert_id;
        // --- TAMBAHAN LOG ---
        umroh_log_activity('CREATE_JEMAAH', $new_id, "Menambahkan jemaah: " . $p['full_name']);
        // --------------------
        return new WP_REST_Response(['id' => $new_id, 'message' => 'Jemaah berhasil ditambahkan'], 201);
    } else {
        return new WP_Error('db_error', 'Gagal menyimpan manifest', ['status' => 500]);
    }
}

/**
 * PUT /umroh/v1/manifest/{id}
 * Update status jemaah (Visa, Koper, Status Umum)
 */
function umroh_update_manifest($request) {
    global $wpdb;
    $id = $request['id'];
    $p = $request->get_json_params();

    $data_to_update = [];
    $log_details = [];

    // Kumpulkan data yang ingin diupdate
    if (isset($p['visa_status'])) {
        $data_to_update['visa_status'] = sanitize_text_field($p['visa_status']);
        $log_details[] = "Visa: " . $p['visa_status'];
    }
    if (isset($p['equipment_taken'])) {
        $data_to_update['equipment_taken'] = intval($p['equipment_taken']);
        $log_details[] = "Koper: " . ($p['equipment_taken'] ? 'Diambil' : 'Belum');
    }
     if (isset($p['status'])) {
        $data_to_update['status'] = sanitize_text_field($p['status']);
        $log_details[] = "Status: " . $p['status'];
    }
    // TODO: Tambah update payment_status jika perlu diupdate manual

    if (empty($data_to_update)) {
        return new WP_Error('no_data', 'Tidak ada data untuk diupdate', ['status' => 400]);
    }

    $wpdb->update($wpdb->prefix . 'umroh_manifest', $data_to_update, ['id' => $id]);

    // --- TAMBAHAN LOG ---
    umroh_log_activity('UPDATE_JEMAAH', $id, "Update status: " . implode(', ', $log_details));
    // --------------------

    return new WP_REST_Response(['id' => $id, 'message' => 'Status jemaah diupdate'], 200);
}


/**
 * GET /umroh/v1/manifest/{id}/payments
 * Mengambil riwayat cicilan jemaah
 */
function umroh_get_jemaah_payments($request) {
    global $wpdb;
    $id = $request['id'];
    
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}umroh_payments WHERE manifest_id = %d ORDER BY payment_date DESC", $id
    ));
    
    return new WP_REST_Response($payments, 200);
}

/**
 * POST /umroh/v1/manifest/{id}/payment
 * Menambahkan cicilan pembayaran jemaah
 */
function umroh_add_jemaah_payment($request) {
    global $wpdb;
    $manifest_id = $request['id'];
    $p = $request->get_json_params();
    $admin_id = get_current_user_id();
    
    // 1. Validasi
    if (empty($p['amount']) || !is_numeric($p['amount']) || $p['amount'] <= 0) {
        return new WP_Error('bad_amount', 'Nominal tidak valid', ['status' => 400]);
    }
    
    // 2. Simpan ke tabel cicilan
    $wpdb->insert($wpdb->prefix . 'umroh_payments', [
        'manifest_id'   => $manifest_id,
        'amount'        => floatval($p['amount']),
        'payment_date'  => $p['payment_date'],
        'method'        => sanitize_text_field($p['method']),
        'notes'         => sanitize_textarea_field($p['notes']),
        'recorded_by'   => $admin_id,
        'created_at'    => current_time('mysql')
    ]);
    
    $payment_id = $wpdb->insert_id;
    
    // 3. Ambil data jemaah untuk update status & logging
    $jemaah = $wpdb->get_row($wpdb->prepare("SELECT full_name, final_price FROM {$wpdb->prefix}umroh_manifest WHERE id = %d", $manifest_id));
    
    // 4. Hitung total yang sudah dibayar
    $total_paid = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM {$wpdb->prefix}umroh_payments WHERE manifest_id = %d", $manifest_id
    ));
    
    // 5. Update status lunas di tabel manifest
    $new_payment_status = 'DP';
    if ($total_paid >= $jemaah->final_price) {
        $new_payment_status = 'Lunas';
    }
    $wpdb->update($wpdb->prefix . 'umroh_manifest', ['payment_status' => $new_payment_status], ['id' => $manifest_id]);
    
    // 6. [INTEGRASI] Panggil "Buku Besar Otomatis"
    umroh_trigger_finance_entry(
        'Pemasukan', 
        $p['amount'], 
        "Pembayaran Jemaah: " . $jemaah->full_name,
        $manifest_id
    );
    
    // 7. Catat Log Aktivitas
    umroh_log_activity('JEMAAH_PAYMENT', $manifest_id, "Input bayar Rp " . $p['amount'] . " (Total: $total_paid)");
    
    return new WP_REST_Response(['success' => true, 'id' => $payment_id, 'new_status' => $new_payment_status], 201);
}

/**
 * POST /umroh/v1/manifest/{id}/refund
 * Memproses jemaah batal dan refund
 */
function umroh_process_refund($request) {
    global $wpdb;
    $manifest_id = $request['id'];
    $p = $request->get_json_params();
    
    // 1. Validasi
    if (empty($p['amount']) || !is_numeric($p['amount']) || $p['amount'] <= 0) {
        return new WP_Error('bad_amount', 'Nominal refund tidak valid', ['status' => 400]);
    }
    
    // 2. Ambil data jemaah
    $jemaah = $wpdb->get_row($wpdb->prepare("SELECT full_name FROM {$wpdb->prefix}umroh_manifest WHERE id = %d", $manifest_id));
    
    // 3. Update status jemaah jadi Batal/Refund
    $wpdb->update($wpdb->prefix . 'umroh_manifest', 
        ['status' => 'Refund', 'payment_status' => 'Refund'], 
        ['id' => $manifest_id]
    );
    
    // 4. [INTEGRASI] Catat sebagai PENGELUARAN di Buku Besar
    umroh_trigger_finance_entry(
        'Refund', 
        $p['amount'], 
        "Refund Jemaah Batal: " . $jemaah->full_name,
        $manifest_id
    );
    
    // 5. Catat Log Aktivitas
    umroh_log_activity('JEMAAH_REFUND', $manifest_id, "Proses refund Rp " . $p['amount']);
    
    return new WP_REST_Response(['success' => true, 'message' => 'Refund berhasil diproses'], 200);
}
?>