<?php
// File: includes/api/api-departures.php
// KERANGKA (TEMPLATE) AMAN UNTUK API KEBERANGKATAN

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1';
    
    // PERBAIKAN: Tentukan izin
    $read_permissions = umh_check_api_permission(['owner', 'admin_staff', 'marketing_staff']);
    $write_permissions = umh_check_api_permission(['owner', 'admin_staff']);
    $delete_permissions = umh_check_api_permission(['owner']);

    // Rute: /umh/v1/departures (GET)
    register_rest_route($namespace, '/departures', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_departures',
        'permission_callback' => $read_permissions, // <-- PERBAIKAN
    ));

    // Rute: /umh/v1/departures (POST)
    register_rest_route($namespace, '/departures', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_departure',
        'permission_callback' => $write_permissions, // <-- PERBAIKAN
    ));
    
    // Rute: /umh/v1/departures/<id> (PUT)
    register_rest_route($namespace, '/departures/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_departure',
        'permission_callback' => $write_permissions, // <-- PERBAIKAN
    ));
    
    // Rute: /umh/v1/departures/<id> (DELETE)
    register_rest_route($namespace, '/departures/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_departure',
        'permission_callback' => $delete_permissions, // <-- PERBAIKAN
    ));
});

/**
 * Callback untuk GET /departures
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_departures(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_departures';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_departures belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /departures
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_departure(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_departures';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'package_id' => $params['package_id'],
    //     'departure_date' => $params['departure_date'],
    //     'status' => 'scheduled',
    // ];
    // $format = ['%d', '%s', '%s'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_departure = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_departure, 201);
    // }
    // return new WP_Error('create_failed', 'Gagal membuat keberangkatan.', ['status' => 500]);

    return new WP_REST_Response(['message' => 'Fungsi umh_create_departure belum diimplementasi.'], 501);
}

/**
 * Callback untuk PUT /departures/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_departure(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_departures';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'package_id' => $params['package_id'],
    //     'departure_date' => $params['departure_date'],
    //     'status' => $params['status'], // (scheduled, departed, completed, cancelled)
    // ];
    // $where = ['id' => $id];
    // $format = ['%d', '%s', '%s'];
    // $where_format = ['%d'];
    // $wpdb->update($table_name, $data, $where, $format, $where_format);
    // $updated_departure = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_departure, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_departure belum diimplementasi.'], 501);
}

/**
 * Callback untuk DELETE /departures/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_departure(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_departures';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // Contoh:
    // $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    // if ($deleted) {
    //     return new WP_REST_Response(['message' => 'Keberangkatan berhasil dihapus.'], 200);
    // }
    // return new WP_Error('delete_failed', 'Gagal menghapus keberangkatan.', ['status' => 500]);

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_departure belum diimplementasi.'], 501);
}