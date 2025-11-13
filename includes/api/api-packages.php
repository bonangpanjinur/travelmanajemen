<?php
// Lokasi: includes/api/api-packages.php
if (!defined('ABSPATH')) exit;

/**
 * [FUNGSI BARU]
 * GET /umroh/v1/packages
 * Mengambil daftar paket dari plugin uhp_packages
 */
function umroh_get_packages($request) {
    global $wpdb;
    $packages_table = $wpdb->prefix . 'uhp_packages'; // Tabel dari plugin paket Anda
    
    // Cek jika tabel ada
    if($wpdb->get_var("SHOW TABLES LIKE '$packages_table'") != $packages_table) {
        return new WP_Error('no_table', 'Tabel uhp_packages tidak ditemukan. Plugin paket belum aktif?', ['status' => 500]);
    }
    
    $results = $wpdb->get_results("
        SELECT id, title, price_details 
        FROM {$packages_table} 
        ORDER BY title ASC
    ");

    if ($results) {
        foreach ($results as $key => $result) {
            // Pastikan price_details adalah JSON, lalu decode
            if (is_string($result->price_details)) {
                $results[$key]->price_details = json_decode($result->price_details, true); // true for assoc array
            }
        }
    }
    
    return new WP_REST_Response($results, 200);
}


/**
 * POST /umroh/v1/packages
 * Input Paket Baru ke tabel wp_uhp_packages
 */
function umroh_create_package($request) {
    global $wpdb;
    
    // Cek permission: Hanya Admin/Owner yang boleh buat paket
    if (!umroh_check_permission_admin()) {
        return new WP_Error('forbidden', 'Hanya admin bisa tambah paket', ['status' => 403]);
    }

    $p = $request->get_json_params();
    $table = $wpdb->prefix . 'uhp_packages';
    $table_dep = $wpdb->prefix . 'uhp_package_departures';

    // 1. Validasi Dasar
    if (empty($p['title']) || empty($p['departure_date'])) {
        return new WP_Error('missing_fields', 'Nama Paket dan Tanggal Wajib Diisi', ['status' => 400]);
    }

    // 2. Format Harga ke JSON (Sesuai struktur plugin UHP asli)
    $price_details = json_encode([
        'Quad' => isset($p['price_quad']) ? floatval($p['price_quad']) : 0,
        'Triple' => isset($p['price_triple']) ? floatval($p['price_triple']) : 0,
        'Double' => isset($p['price_double']) ? floatval($p['price_double']) : 0,
    ]);

    // 3. Generate Slug Otomatis
    $slug = sanitize_title($p['title']) . '-' . rand(100, 999);

    // 4. Insert ke Database
    $inserted = $wpdb->insert($table, [
        'title' => sanitize_text_field($p['title']),
        'slug' => $slug,
        'departure_city' => sanitize_text_field($p['departure_city']),
        'duration' => intval($p['duration']),
        'price_details' => $price_details, // JSON string
        'itinerary' => sanitize_textarea_field($p['itinerary']), 
        'last_updated' => current_time('mysql') // Menggunakan kolom 'last_updated'
    ]);

    if ($inserted === false) {
        return new WP_Error('db_error', 'Gagal simpan ke database: ' . $wpdb->last_error, ['status' => 500]);
    }
    
    $package_id = $wpdb->insert_id;

    // 5. Update Tanggal Keberangkatan (Tabel Relasi: wp_uhp_package_departures)
    $wpdb->insert($table_dep, [
        'package_id' => $package_id,
        'departure_date' => $p['departure_date']
    ]);

    // Catat Log
    umroh_log_activity('CREATE_PACKAGE', $package_id, "Membuat paket baru: " . $p['title']);

    return new WP_REST_Response(['success' => true, 'id' => $package_id, 'message' => 'Paket berhasil dibuat'], 201);
}