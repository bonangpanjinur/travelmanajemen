<?php
// File: includes/api/api-stats.php
// API untuk data Dashboard/Overview

if (!defined('ABSPATH')) exit;

/**
 * Mengambil data statistik untuk dashboard.
 * Endpoint: GET /umroh/v1/dashboard/stats
 */
function umroh_get_dashboard_stats(WP_REST_Request $request) {
    global $wpdb;
    
    // Tentukan prefix tabel
    $prefix = $wpdb->prefix . 'umroh_';
    
    // 1. Omset Bulan Ini (dari tabel finance)
    $omset_month = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount) FROM {$prefix}finance 
             WHERE type = 'Pemasukan' AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())",
            null
        )
    );

    // 2. Jemaah Baru Bulan Ini (dari tabel manifest)
    $new_pilgrims_month = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(id) FROM {$prefix}manifest 
             WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
            null
        )
    );

    // 3. Total Kasbon Aktif (dari tabel finance)
    $total_kasbon = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount) FROM {$prefix}finance 
             WHERE type = 'Kasbon' AND status = 'Pending'",
            null
        )
    );

    // 4. Tugas Belum Selesai (dari tabel tasks)
    $pending_tasks = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(id) FROM {$prefix}tasks WHERE status = 'Pending'",
            null
        )
    );
    
    // 5. Leads (dari tabel leads)
    $leads_hot = $wpdb->get_var("SELECT COUNT(id) FROM {$prefix}leads WHERE status = 'Hot'");
    $leads_warm = $wpdb->get_var("SELECT COUNT(id) FROM {$prefix}leads WHERE status = 'Warm'");
    $leads_cold = $wpdb->get_var("SELECT COUNT(id) FROM {$prefix}leads WHERE status = 'Cold'");

    // 6. Omset 12 Bulan (dari tabel finance)
    $omset_12_months = $wpdb->get_results(
        "SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as omset
         FROM {$prefix}finance
         WHERE type = 'Pemasukan' AND date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
         GROUP BY DATE_FORMAT(date, '%Y-%m')
         ORDER BY month ASC",
        ARRAY_A
    );
    
    // Format ulang data bulan (misal: "2023-11" -> "Nov")
    $omset_formatted = [];
    foreach ($omset_12_months as $month_data) {
        $date_obj = DateTime::createFromFormat('!Y-m', $month_data['month']);
        $omset_formatted[] = [
            'month' => $date_obj->format('M'), // 'Nov', 'Dec', 'Jan'
            'omset' => (int)$month_data['omset']
        ];
    }

    // Siapkan data untuk dikirim
    $stats = [
        'omset_month'         => (int)$omset_month,
        'new_pilgrims_month'  => (int)$new_pilgrims_month,
        'total_kasbon'        => (int)$total_kasbon,
        'pending_tasks'       => (int)$pending_tasks,
        'leads_hot'           => (int)$leads_hot,
        'leads_warm'          => (int)$leads_warm,
        'leads_cold'          => (int)$leads_cold,
        'omset_12_months'     => $omset_formatted
    ];

    return new WP_REST_Response($stats, 200);
}