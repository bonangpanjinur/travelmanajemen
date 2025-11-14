<?php
/**
 * File: includes/api/api-payments.php
 *
 * File BARU untuk mengelola pembayaran jemaah secara dinamis.
 * File ini MENGGUNAKAN ENDPOINT KUSTOM (bukan UMH_CRUD_Controller)
 * karena perlu logika bisnis tambahan (update total di tabel jamaah).
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_payment_api_routes');

function umh_register_payment_api_routes() {
    $namespace = 'umh/v1';
    $base = 'payments';

    // GET /payments ATAU /payments?jamaah_id=...
    register_rest_route($namespace, '/' . $base, [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'umh_get_payments',
            'permission_callback' => 'umh_check_api_permission_finance_staff',
        ],
        // POST /payments
        [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'umh_create_payment',
            'permission_callback' => 'umh_check_api_permission_finance_staff',
        ],
    ]);

    // GET, PUT, DELETE /payments/{id}
    register_rest_route($namespace, '/' . $base . '/(?P<id>[\d]+)', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'umh_get_payment',
            'permission_callback' => 'umh_check_api_permission_finance_staff',
        ],
        [
            'methods'  => WP_REST_Server::EDITABLE,
            'callback' => 'umh_update_payment',
            'permission_callback' => 'umh_check_api_permission_finance_staff',
        ],
        [
            'methods'  => WP_REST_Server::DELETABLE,
            'callback' => 'umh_delete_payment',
            'permission_callback' => 'umh_check_api_permission_finance_staff',
        ],
    ]);

    // POST /payments/{id}/upload_proof
    register_rest_route($namespace, '/' . $base . '/(?P<id>[\d]+)/upload_proof', [
        [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'umh_handle_payment_proof_upload',
            'permission_callback' => 'umh_check_api_permission_finance_staff',
        ],
    ]);
}

// Izin custom (atau bisa gunakan `umh_check_api_permission` dengan role 'finance_staff')
function umh_check_api_permission_finance_staff() {
    return umh_check_api_permission(['owner', 'admin_staff', 'finance_staff']);
}

// Helper Function: Menghitung ulang total pembayaran jemaah
function umh_update_jamaah_balance($jamaah_id) {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'umh_payments';
    $jamaah_table = $wpdb->prefix . 'umh_jamaah';

    // Hitung total pembayaran yang 'verified'
    $total_paid = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $payments_table WHERE jamaah_id = %d AND status = 'verified'",
        $jamaah_id
    ));

    if (is_null($total_paid)) {
        $total_paid = 0;
    }

    // Update tabel umh_jamaah
    $wpdb->update(
        $jamaah_table,
        ['amount_paid' => $total_paid],
        ['id' => $jamaah_id],
        ['%f'],
        ['%d']
    );

    // Update payment_status
    $jamaah = $wpdb->get_row($wpdb->prepare("SELECT total_price FROM $jamaah_table WHERE id = %d", $jamaah_id));
    $total_price = (float) $jamaah->total_price;
    
    $payment_status = 'Belum Lunas';
    if ($total_paid >= $total_price) {
        $payment_status = 'Lunas';
    } elseif ($total_paid > 0) {
        $payment_status = 'Cicil';
    }

    $wpdb->update(
        $jamaah_table,
        ['payment_status' => $payment_status],
        ['id' => $jamaah_id],
        ['%s'],
        ['%d']
    );

    return $total_paid;
}

// GET /payments?jamaah_id=...
function umh_get_payments($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_payments';
    $jamaah_id = $request->get_param('jamaah_id');

    if (!empty($jamaah_id)) {
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE jamaah_id = %d ORDER BY payment_date DESC", $jamaah_id));
    } else {
        $items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY payment_date DESC");
    }

    return new WP_REST_Response($items, 200);
}

// GET /payments/{id}
function umh_get_payment($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_payments';
    $id = (int) $request['id'];
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));

    if (!$item) {
        return new WP_Error('not_found', 'Payment not found', ['status' => 404]);
    }

    return new WP_REST_Response($item, 200);
}

// POST /payments
function umh_create_payment($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_payments';
    $params = $request->get_json_params();

    $jamaah_id = (int) $params['jamaah_id'];
    if (empty($jamaah_id)) {
        return new WP_Error('bad_request', 'Jamaah ID is required', ['status' => 400]);
    }

    $data = [
        'jamaah_id'     => $jamaah_id,
        'payment_date'  => sanitize_text_field($params['payment_date']),
        'amount'        => (float) $params['amount'],
        'payment_stage' => sanitize_text_field($params['payment_stage']),
        'status'        => sanitize_text_field($params['status']) ?: 'pending',
        'notes'         => sanitize_textarea_field($params['notes']),
        'proof_url'     => esc_url_raw($params['proof_url']),
        'created_at'    => current_time('mysql'),
        'updated_at'    => current_time('mysql'),
    ];

    $wpdb->insert($table_name, $data);
    $new_id = $wpdb->insert_id;

    // Hitung ulang total
    umh_update_jamaah_balance($data['jamaah_id']);

    $new_payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $new_id));
    return new WP_REST_Response($new_payment, 201);
}

// PUT /payments/{id}
function umh_update_payment($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_payments';
    $id = (int) $request['id'];
    $params = $request->get_json_params();

    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    if (!$item) {
        return new WP_Error('not_found', 'Payment not found', ['status' => 404]);
    }

    $data = [
        'payment_date'  => sanitize_text_field($params['payment_date']),
        'amount'        => (float) $params['amount'],
        'payment_stage' => sanitize_text_field($params['payment_stage']),
        'status'        => sanitize_text_field($params['status']),
        'notes'         => sanitize_textarea_field($params['notes']),
        'proof_url'     => esc_url_raw($params['proof_url']),
        'updated_at'    => current_time('mysql'),
    ];

    $wpdb->update($table_name, $data, ['id' => $id]);

    // Hitung ulang total
    umh_update_jamaah_balance($item->jamaah_id);

    $updated_payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    return new WP_REST_Response($updated_payment, 200);
}

// DELETE /payments/{id}
function umh_delete_payment($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_payments';
    $id = (int) $request['id'];

    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    if (!$item) {
        return new WP_Error('not_found', 'Payment not found', ['status' => 404]);
    }

    $wpdb->delete($table_name, ['id' => $id]);

    // Hitung ulang total
    umh_update_jamaah_balance($item->jamaah_id);

    return new WP_REST_Response(['deleted' => true, 'id' => $id], 200);
}

// POST /payments/{id}/upload_proof
function umh_handle_payment_proof_upload($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_payments';
    $id = (int) $request['id'];

    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    if (!$item) {
        return new WP_Error('not_found', 'Payment not found', ['status' => 404]);
    }

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploaded_file = $_FILES['file'];
    $upload_overrides = ['test_form' => false];
    $move_file = wp_handle_upload($uploaded_file, $upload_overrides);

    if ($move_file && !isset($move_file['error'])) {
        $file_url = $move_file['url'];

        // Update URL di database
        $wpdb->update(
            $table_name,
            ['proof_url' => $file_url, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        $updated_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        return new WP_REST_Response($updated_item, 200);
    } else {
        return new WP_Error('upload_error', $move_file['error'], ['status' => 500]);
    }
}