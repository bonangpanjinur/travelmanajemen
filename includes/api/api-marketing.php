<?php
// File: includes/api/api-marketing.php
// KERANGKA (TEMPLATE) AMAN UNTUK API MARKETING

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1';
    
    // Rute: /umh/v1/marketing (GET)
    register_rest_route($namespace, '/marketing', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_marketing_data',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/marketing/leads (POST)
    register_rest_route($namespace, '/marketing/leads', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_lead',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
    
    // Rute: /umh/v1/marketing/leads/<id> (PUT)
    register_rest_route($namespace, '/marketing/leads/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_lead',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
});

/**
 * Callback untuk GET /marketing
 * (Contoh: mungkin mengambil data leads)
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_marketing_data(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_marketing_leads';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT * FROM $table_name");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_marketing_data belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /marketing/leads
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_lead(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_marketing_leads';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Contoh:
    // $data = [
    //     'lead_name' => $params['lead_name'],
    //     'source' => $params['source'],
    //     'status' => 'new',
    // ];
    // $format = ['%s', '%s', '%s'];
    // $wpdb->insert($table_name, $data, $format);
    // $new_id = $wpdb->insert_id;
    //
    // if ($new_id) {
    //     $new_lead = $wpdb->get_row("SELECT * FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_lead, 201);
    // }
    // return new WP_Error('create_failed', 'Gagal membuat lead.', ['status' => 500]);

    return new WP_REST_Response(['message' => 'Fungsi umh_create_lead belum diimplementasi.'], 501);
}

/**
 * Callback untuk PUT /marketing/leads/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_lead(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_marketing_leads';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'lead_name' => $params['lead_name'],
    //     'source' => $params['source'],
    //     'status' => $params['status'], // (new, contacted, qualified, lost)
    // ];
    // $where = ['id' => $id];
    // $format = ['%s', '%s', '%s'];
    // $where_format = ['%d'];
    // $wpdb->update($table_name, $data, $where, $format, $where_format);
    // $updated_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_lead, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_lead belum diimplementasi.'], 501);
}