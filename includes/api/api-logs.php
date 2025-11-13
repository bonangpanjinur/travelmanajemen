<?php
// File: includes/api/api-logs.php
// API untuk melihat Log Aktivitas (CCTV Digital)

if (!defined('ABSPATH')) exit;

/**
 * Mengambil data log aktivitas.
 * Hanya bisa diakses oleh Admin/Owner.
 * Endpoint: GET /umroh/v1/logs
 */
function umroh_get_audit_logs(WP_REST_Request $request) {
    global $wpdb;
    
    $table_log = $wpdb->prefix . 'umroh_logs';
    $table_users = $wpdb->prefix . 'users';

    // Ambil 100 log terakhir, gabungkan dengan nama user
    $logs = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT l.id, l.timestamp, l.action, l.object_type, l.object_id, l.details, u.display_name as user_name
             FROM $table_log l
             LEFT JOIN $table_users u ON l.user_id = u.ID
             ORDER BY l.timestamp DESC
             LIMIT 100",
            null
        )
    );

    if ($logs === null) {
        return new WP_Error('db_error', 'Gagal mengambil data logs', ['status' => 500]);
    }

    return new WP_REST_Response($logs, 200);
}