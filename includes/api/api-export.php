<?php
// File: includes/api/api-export.php
// API untuk export data ke CSV

if (!defined('ABSPATH')) exit;

/**
 * Export data manifest ke CSV.
 * Endpoint: GET /umroh/v1/export/manifest
 */
function umroh_export_manifest(WP_REST_Request $request) {
    global $wpdb;

    $table_manifest = $wpdb->prefix . 'umroh_manifest';
    $table_packages = $wpdb->prefix . 'uhp_packages';

    // Ambil data jemaah dengan nama paket
    $data = $wpdb->get_results(
        "SELECT 
            m.full_name, 
            m.passport_number, 
            m.passport_expiry, 
            m.payment_status, 
            m.visa_status, 
            m.equipment_status,
            m.status as jemaah_status,
            p.title as package_name,
            m.final_price
         FROM $table_manifest m
         LEFT JOIN $table_packages p ON m.package_id = p.id
         ORDER BY m.full_name ASC",
        ARRAY_A // Ambil sebagai associative array
    );

    if (empty($data)) {
        return new WP_Error('no_data', 'Tidak ada data manifest untuk diexport', ['status' => 404]);
    }

    // Buat header CSV
    $csv_output = "";
    $headers = [
        'Nama Lengkap', 
        'No Paspor', 
        'Expiry Paspor', 
        'Status Bayar', 
        'Status Visa', 
        'Status Koper',
        'Status Jemaah',
        'Nama Paket',
        'Harga Paket (Rp)'
    ];
    $csv_output .= implode(',', $headers) . "\n";

    // Buat baris data CSV
    foreach ($data as $row) {
        // Pastikan data bersih untuk CSV (kutip jika ada koma)
        foreach ($row as $key => $value) {
            $row[$key] = '"' . str_replace('"', '""', $value) . '"';
        }
        $csv_output .= implode(',', $row) . "\n";
    }
    
    // Kembalikan sebagai JSON yang berisi string CSV
    // Frontend akan menangani konversi ke file
    return new WP_REST_Response(['csv_data' => $csv_output], 200);
}