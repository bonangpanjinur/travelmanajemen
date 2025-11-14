<?php
// File: includes/api/api-finance.php
// KERANGKA (TEMPLATE) AMAN UNTUK API KEUANGAN (CRUD LENGKAP)

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1';
    
    // Rute: /umh/v1/finance (GET)
    register_rest_route($namespace, '/finance', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_finance_entries',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/finance (POST)
    register_rest_route($namespace, '/finance', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_finance_entry',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // === TAMBAHAN: Rute Update (PUT) ===
    register_rest_route($namespace, '/finance/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_finance_entry',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));

    // === TAMBAHAN: Rute Delete (DELETE) ===
    register_rest_route($namespace, '/finance/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_finance_entry',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
        'args'                => array('id' => array('validate_callback' => 'is_numeric')),
    ));
});

/**
 * Callback untuk GET /finance
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_finance_entries(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_finance';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_finance_entries belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /finance
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_finance_entry(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_finance';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'jamaah_id'      => $params['jamaah_id'],
    //     'description'    => $params['description'],
    //     'amount'         => $params['amount'],
    //     'type'           => $params['type'], // 'income' or 'expense'
    //     'transaction_date' => $params['transaction_date'] ?? current_time('mysql'),
    // ];
    // $format = ['%d', '%s', '%f', '%s', '%s'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_entry = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_entry, 201); // 201 Created
    // } else {
    //     return new WP_Error('create_failed', 'Gagal membuat entri keuangan baru.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_create_finance_entry belum diimplementasi.'], 501);
}

// === TAMBAHAN: Fungsi callback untuk Update (PUT) ===
/**
 * Callback untuk PUT /finance/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_finance_entry(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_finance';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'jamaah_id'      => $params['jamaah_id'],
    //     'description'    => $params['description'],
    //     'amount'         => $params['amount'],
    //     'type'           => $params['type'],
    // ];
    // $where = ['id' => $id];
    // $format = ['%d', '%s', '%f', '%s'];
    // $where_format = ['%d'];
    // $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);
    //
    // if ($updated === false) {
    //     return new WP_Error('update_failed', 'Gagal memperbarui entri keuangan.', ['status' => 500]);
    // }
    // $updated_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_entry, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_finance_entry belum diimplementasi.'], 501);
}

// === TAMBAHAN: Fungsi callback untuk Delete (DELETE) ===
/**
 * Callback untuk DELETE /finance/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_finance_entry(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_finance';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // Contoh:
    // $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    // if ($deleted) {
    //     return new WP_REST_Response(['message' => 'Entri keuangan berhasil dihapus.'], 200);
    // } else {
    //     return new WP_Error('delete_failed', 'Gagal menghapus entri keuangan.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_finance_entry belum diimplementasi.'], 501);
}