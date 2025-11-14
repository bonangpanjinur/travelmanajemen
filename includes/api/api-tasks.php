<?php
// File: includes/api/api-tasks.php
// KERANGKA (TEMPLATE) AMAN UNTUK API MANAJEMEN TUGAS

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1';
    
    // Rute: /umh/v1/tasks (GET)
    register_rest_route($namespace, '/tasks', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_tasks',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/tasks (POST)
    register_rest_route($namespace, '/tasks', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_task',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
    
    // Rute: /umh/v1/tasks/<id> (PUT)
    register_rest_route($namespace, '/tasks/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_task',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
    
    // Rute: /umh/v1/tasks/<id> (DELETE)
    register_rest_route($namespace, '/tasks/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_task',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
});

/**
 * Callback untuk GET /tasks
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_tasks(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_tasks';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_tasks belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /tasks
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_task(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_tasks';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'task_name' => $params['task_name'],
    //     'assigned_to_user_id' => $params['assigned_to_user_id'],
    //     'status' => 'pending',
    // ];
    // $format = ['%s', '%d', '%s'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_task = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_task, 201);
    // }
    // return new WP_Error('create_failed', 'Gagal membuat tugas.', ['status' => 500]);

    return new WP_REST_Response(['message' => 'Fungsi umh_create_task belum diimplementasi.'], 501);
}

/**
 * Callback untuk PUT /tasks/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_task(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_tasks';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'task_name' => $params['task_name'],
    //     'assigned_to_user_id' => $params['assigned_to_user_id'],
    //     'status' => $params['status'], // (pending, in_progress, completed)
    // ];
    // $where = ['id' => $id];
    // $format = ['%s', '%d', '%s'];
    // $where_format = ['%d'];
    // $wpdb->update($table_name, $data, $where, $format, $where_format);
    // $updated_task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_task, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_task belum diimplementasi.'], 501);
}

/**
 * Callback untuk DELETE /tasks/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_task(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_tasks';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // Contoh:
    // $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    // if ($deleted) {
    //     return new WP_REST_Response(['message' => 'Tugas berhasil dihapus.'], 200);
    // }
    // return new WP_Error('delete_failed', 'Gagal menghapus tugas.', ['status' => 500]);

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_task belum diimplementasi.'], 501);
}