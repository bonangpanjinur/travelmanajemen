<?php
// File: includes/api/api-packages.php
// KERANGKA (TEMPLATE) AMAN UNTUK API PAKET

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1'; // Namespace API Anda
    
    // Rute: /umh/v1/packages (GET) - Mendapatkan semua paket
    register_rest_route($namespace, '/packages', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_packages',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/packages (POST) - Membuat paket baru
    register_rest_route($namespace, '/packages', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_package',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array(
            'package_name' => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            'price'        => array('required' => true, 'sanitize_callback' => 'sanitize_text_field'),
            // Tambahkan args lain di sini
        ),
    ));
    
    // Rute: /umh/v1/packages/<id> (GET) - Mendapatkan satu paket
    register_rest_route($namespace, '/packages/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_package',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));

    // Rute: /umh/v1/packages/<id> (PUT/POST) - Update satu paket
    register_rest_route($namespace, '/packages/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_package',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));
    
    // Rute: /umh/v1/packages/<id> (DELETE) - Hapus satu paket
    register_rest_route($namespace, '/packages/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_package',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));
});

/**
 * Callback untuk GET /packages
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_packages(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    // if (empty($results)) {
    //     return new WP_REST_Response([], 200);
    // }
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_packages belum diimplementasi. Buka includes/api/api-packages.php dan isi logikanya.'], 501);
}

/**
 * Callback untuk POST /packages
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_package(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'package_name' => $params['package_name'],
    //     'description'  => $params['description'] ?? '',
    //     'price'        => $params['price'],
    //     // ... data lain
    // ];
    // $format = ['%s', '%s', '%f'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_package = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_package, 201); // 201 Created
    // } else {
    //     return new WP_Error('create_failed', 'Gagal membuat paket baru.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_create_package belum diimplementasi.'], 501);
}

/**
 * Callback untuk GET /packages/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_package(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';
    $id = $request['id'];

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $package = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // if (!$package) {
    //     return new WP_Error('not_found', 'Paket tidak ditemukan.', ['status' => 404]);
    // }
    // return new WP_REST_Response($package, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_package belum diimplementasi.'], 501);
}

/**
 * Callback untuk PUT /packages/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_package(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'package_name' => $params['package_name'],
    //     'price'        => $params['price'],
    //     // ... data lain
    // ];
    // $where = ['id' => $id];
    // $format = ['%s', '%f'];
    // $where_format = ['%d'];
    // $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);
    //
    // if ($updated === false) {
    //     return new WP_Error('update_failed', 'Gagal memperbarui paket.', ['status' => 500]);
    // }
    // $updated_package = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_package, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_package belum diimplementasi.'], 501);
}

/**
 * Callback untuk DELETE /packages/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_package(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // Contoh:
    // $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    // if ($deleted) {
    //     return new WP_REST_Response(['message' => 'Paket berhasil dihapus.'], 200);
    // } else {
    //     return new WP_Error('delete_failed', 'Gagal menghapus paket.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_package belum diimplementasi.'], 501);
}