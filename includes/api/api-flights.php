<?php
// File: includes/api/api-flights.php
// KERANGKA (TEMPLATE) AMAN UNTUK API PESAWAT (CRUD LENGKAP)

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

    // Rute: /umh/v1/flights (GET)
    register_rest_route($namespace, '/flights', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_flights',
        'permission_callback' => $read_permissions, // <-- PERBAIKAN
    ));

    // Rute: /umh/v1/flights (POST)
    register_rest_route($namespace, '/flights', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_flight',
        'permission_callback' => $write_permissions, // <-- PERBAIKAN
    ));

    // === TAMBAHAN: Rute Update (PUT) ===
    register_rest_route($namespace, '/flights/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_flight',
        'permission_callback' => $write_permissions, // <-- PERBAIKAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));

    // === TAMBAHAN: Rute Delete (DELETE) ===
    register_rest_route($namespace, '/flights/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_flight',
        'permission_callback' => $delete_permissions, // <-- PERBAIKAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));
});

/**
 * Callback untuk GET /flights
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_flights(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_flights';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_flights belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /flights
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_flight(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_flights';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'airline_name' => $params['airline_name'],
    //     'flight_number'  => $params['flight_number'],
    //     // ... data lain
    // ];
    // $format = ['%s', '%s'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_flight = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_flight, 201); // 201 Created
    // } else {
    //     return new WP_Error('create_failed', 'Gagal membuat data penerbangan baru.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_create_flight belum diimplementasi.'], 501);
}

// === TAMBAHAN: Fungsi callback untuk Update (PUT) ===
/**
 * Callback untuk PUT /flights/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_flight(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_flights';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'airline_name' => $params['airline_name'],
    //     'flight_number'  => $params['flight_number'],
    // ];
    // $where = ['id' => $id];
    // $format = ['%s', '%s'];
    // $where_format = ['%d'];
    // $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);
    //
    // if ($updated === false) {
    //     return new WP_Error('update_failed', 'Gagal memperbarui penerbangan.', ['status' => 500]);
    // }
    // $updated_flight = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_flight, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_flight belum diimplementasi.'], 501);
}

// === TAMBAHAN: Fungsi callback untuk Delete (DELETE) ===
/**
 * Callback untuk DELETE /flights/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_flight(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_flights';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // Contoh:
    // $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    // if ($deleted) {
    //     return new WP_REST_Response(['message' => 'Penerbangan berhasil dihapus.'], 200);
    // } else {
    //     return new WP_Error('delete_failed', 'Gagal menghapus penerbangan.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_flight belum diimplementasi.'], 501);
}