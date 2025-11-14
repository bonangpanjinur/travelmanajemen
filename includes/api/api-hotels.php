<?php
// File: includes/api/api-hotels.php
// KERANGKA (TEMPLATE) AMAN UNTUK API HOTEL (CRUD LENGKAP)

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1';
    
    // Rute: /umh/v1/hotels (GET)
    register_rest_route($namespace, '/hotels', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_hotels',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/hotels (POST)
    register_rest_route($namespace, '/hotels', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_hotel',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // === TAMBAHAN: Rute Update (PUT) ===
    register_rest_route($namespace, '/hotels/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_hotel',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));

    // === TAMBAHAN: Rute Delete (DELETE) ===
    register_rest_route($namespace, '/hotels/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_hotel',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));
});

/**
 * Callback untuk GET /hotels
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_hotels(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_hotels';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_hotels belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /hotels
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_hotel(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_hotels';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'hotel_name' => $params['hotel_name'],
    //     'address'  => $params['address'] ?? '',
    //     'stars'    => $params['stars'] ?? 5,
    // ];
    // $format = ['%s', '%s', '%d'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_hotel = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_hotel, 201); // 201 Created
    // } else {
    //     return new WP_Error('create_failed', 'Gagal membuat hotel baru.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_create_hotel belum diimplementasi.'], 501);
}

// === TAMBAHAN: Fungsi callback untuk Update (PUT) ===
/**
 * Callback untuk PUT /hotels/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_hotel(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_hotels';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'hotel_name' => $params['hotel_name'],
    //     'address'  => $params['address'] ?? '',
    //     'stars'    => $params['stars'] ?? 5,
    // ];
    // $where = ['id' => $id];
    // $format = ['%s', '%s', '%d'];
    // $where_format = ['%d'];
    // $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);
    //
    // if ($updated === false) {
    //     return new WP_Error('update_failed', 'Gagal memperbarui hotel.', ['status' => 500]);
    // }
    // $updated_hotel = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_hotel, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_hotel belum diimplementasi.'], 501);
}

// === TAMBAHAN: Fungsi callback untuk Delete (DELETE) ===
/**
 * Callback untuk DELETE /hotels/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_hotel(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_hotels';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // Contoh:
    // $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    // if ($deleted) {
    //     return new WP_REST_Response(['message' => 'Hotel berhasil dihapus.'], 200);
    // } else {
    //     return new WP_Error('delete_failed', 'Gagal menghapus hotel.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_hotel belum diimplementasi.'], 501);
}