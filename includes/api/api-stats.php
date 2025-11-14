<?php
// File: includes/api/api-stats.php
// Mengelola endpoint untuk statistik dashboard.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_stats_routes');

function umh_register_stats_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten

    // PERBAIKAN: Tentukan izin (baca-saja)
    $read_permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff']);

    // Endpoint untuk statistik total
    register_rest_route($namespace, '/stats/totals', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_get_total_stats',
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
    ]);

    // Endpoint untuk statistik per paket
    register_rest_route($namespace, '/stats/packages', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_get_package_stats',
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
    ]);

    // Endpoint untuk statistik keuangan (grafik)
    register_rest_route($namespace, '/stats/finance-chart', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_get_finance_chart_stats',
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
    ]);
}

// Callback: Get Total Stats
function umh_get_total_stats(WP_REST_Request $request) {
    global $wpdb;
    
    // Menggunakan tabel UMH yang baru
    $jamaah_table = $wpdb->prefix . 'umh_jamaah';
    $packages_table = $wpdb->prefix . 'umh_packages';
    $finance_table = $wpdb->prefix . 'umh_finance';

    // Query menggunakan tabel yang benar
    $total_jamaah = $wpdb->get_var("SELECT COUNT(*) FROM $jamaah_table");
    $total_packages = $wpdb->get_var("SELECT COUNT(*) FROM $packages_table");
    $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM $finance_table WHERE transaction_type = 'income'");
    $total_expense = $wpdb->get_var("SELECT SUM(amount) FROM $finance_table WHERE transaction_type = 'expense'");

    $stats = [
        'total_jamaah' => (int) $total_jamaah,
        'total_packages' => (int) $total_packages,
        'total_revenue' => (float) $total_revenue,
        'total_expense' => (float) $total_expense,
        'net_profit' => (float) ($total_revenue - $total_expense),
    ];

    return new WP_REST_Response($stats, 200);
}

// Callback: Get Package Stats
function umh_get_package_stats(WP_REST_Request $request) {
    global $wpdb;
    
    // Menggunakan tabel UMH yang baru
    $jamaah_table = $wpdb->prefix . 'umh_jamaah';
    $packages_table = $wpdb->prefix . 'umh_packages';
    
    // Query menggunakan tabel yang benar
    $query = "
        SELECT p.package_name, COUNT(j.id) as jamaah_count
        FROM $packages_table p
        LEFT JOIN $jamaah_table j ON p.id = j.package_id
        GROUP BY p.id
    ";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    if ($results === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    return new WP_REST_Response($results, 200);
}

// Callback: Get Finance Chart Stats
function umh_get_finance_chart_stats(WP_REST_Request $request) {
    global $wpdb;
    $finance_table = $wpdb->prefix . 'umh_finance';
    
    // Query untuk mengambil data per bulan (contoh)
    $query = "
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN transaction_type = 'expense' THEN amount ELSE 0 END) as expense
        FROM $finance_table
        GROUP BY month
        ORDER BY month ASC
        LIMIT 12
    ";
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    if ($results === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    return new WP_REST_Response($results, 200);
}