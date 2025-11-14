<?php
// File: includes/api/api-export.php
// Mengelola endpoint untuk ekspor data (misal: CSV).

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_export_routes');

function umh_register_export_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten

    // PERBAIKAN: Tentukan izin (baca-saja)
    $read_permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff']);

    // Endpoint untuk ekspor data jemaah
    register_rest_route($namespace, '/export/jamaah', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_export_jamaah_csv',
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
    ]);
}

// Callback: Export Jamaah as CSV
function umh_export_jamaah_csv(WP_REST_Request $request) {
    global $wpdb;
    
    // Menggunakan tabel UMH yang baru
    $jamaah_table = $wpdb->prefix . 'umh_jamaah';
    $packages_table = $wpdb->prefix . 'umh_packages';
    
    $package_id = $request->get_param('package_id');

    $query = "
        SELECT j.*, p.package_name 
        FROM $jamaah_table j 
        LEFT JOIN $packages_table p ON j.package_id = p.id
        WHERE 1=1
    ";

    if (!empty($package_id)) {
        $query .= $wpdb->prepare(" AND j.package_id = %d", $package_id);
    }
    
    $data = $wpdb->get_results($query, ARRAY_A);

    if ($data === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    if (empty($data)) {
        return new WP_Error('not_found', __('No data to export.', 'umh'), ['status' => 404]);
    }

    // Generate CSV
    $filename = 'export_jamaah_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, array_keys($data[0]));
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}