<?php
// File: includes/api/api-logs.php
// Mengelola endpoint untuk log aktivitas.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_logs_routes');

function umh_register_logs_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten

    // PERBAIKAN: Hanya 'owner' yang boleh melihat log
    $permissions = umh_check_api_permission(['owner']);

    // Endpoint untuk mengambil log
    register_rest_route($namespace, '/logs', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_get_logs',
            'permission_callback' => $permissions, // PERBAIKAN
        ],
    ]);
}

// Callback: Get Logs
function umh_get_logs(WP_REST_Request $request) {
    global $wpdb;
    
    // Menggunakan tabel UMH yang baru
    $logs_table = $wpdb->prefix . 'umh_logs';
    $users_table = $wpdb->prefix . 'umh_users'; // Menggunakan tabel umh_users
    
    $limit = $request->get_param('limit') ? (int) $request->get_param('limit') : 20;
    $page = $request->get_param('page') ? (int) $request->get_param('page') : 1;
    $offset = ($page - 1) * $limit;

    $query = $wpdb->prepare("
        SELECT l.*, u.full_name as user_name 
        FROM $logs_table l
        LEFT JOIN $users_table u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT %d OFFSET %d
    ", $limit, $offset);
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    $total_query = "SELECT COUNT(*) FROM $logs_table";
    $total_items = $wpdb->get_var($total_query);

    if ($results === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    $response = new WP_REST_Response($results, 200);
    
    // Menambahkan header pagination
    $response->header('X-WP-Total', (int) $total_items);
    $response->header('X-WP-TotalPages', ceil($total_items / $limit));

    return $response;
}

/**
 * Fungsi helper untuk membuat log (panggil ini dari fungsi lain)
 *
 * @param int $user_id ID pengguna dari tabel umh_users
 * @param string $action Aksi (e.g., 'create', 'update', 'delete', 'login')
 * @param string $object_type Tipe objek (e.g., 'jamaah', 'package', 'user')
 * @param int $object_id ID objek yang terpengaruh
 * @param string $details Detail log (opsional)
 */
function umh_create_log_entry($user_id, $action, $object_type, $object_id, $details = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_logs';

    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'action' => $action,
        'object_type' => $object_type,
        'object_id' => $object_id,
        'details' => $details,
        'ip_address' => umh_get_client_ip(), // Asumsi ada fungsi umh_get_client_ip di utils.php
        'created_at' => current_time('mysql'),
    ]);
}

// Tambahkan fungsi ini ke utils.php jika belum ada
if (!function_exists('umh_get_client_ip')) {
    function umh_get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}