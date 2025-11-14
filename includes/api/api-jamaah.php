<?php
// File: includes/api/api-jamaah.php
// Mengelola semua data jemaah, manifest, dan pembayaran terkait.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_jamaah_routes');

function umh_register_jamaah_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten

    // Tentukan role yang diizinkan
    $read_permissions = ['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff'];
    $write_permissions = ['owner', 'admin_staff'];
    $delete_permissions = ['owner'];
    $payment_permissions = ['owner', 'admin_staff', 'finance_staff'];

    // Endpoint untuk CRUD Jamaah
    register_rest_route($namespace, '/jamaah', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_get_all_jamaah',
            'permission_callback' => umh_check_api_permission($read_permissions), // PERBAIKAN
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'umh_create_jamaah',
            'permission_callback' => umh_check_api_permission($write_permissions), // PERBAIKAN
            'args' => umh_get_jamaah_schema(),
        ],
    ]);

    // Endpoint untuk satu Jamaah (by ID)
    register_rest_route($namespace, '/jamaah/(?P<id>\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_get_jamaah_by_id',
            'permission_callback' => umh_check_api_permission($read_permissions), // PERBAIKAN
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'umh_update_jamaah',
            'permission_callback' => umh_check_api_permission($write_permissions), // PERBAIKAN
            'args' => umh_get_jamaah_schema(true), // true for update
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'umh_delete_jamaah',
            'permission_callback' => umh_check_api_permission($delete_permissions), // PERBAIKAN
        ],
    ]);

    // Endpoint untuk pembayaran (dari api-manifest.php)
    register_rest_route($namespace, '/jamaah/(?P<id>\d+)/payment', [
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'umh_update_jamaah_payment',
            'permission_callback' => umh_check_api_permission($payment_permissions), // PERBAIKAN
            'args' => [
                'payment_status' => [
                    'type' => 'string',
                    'required' => true,
                    'enum' => ['pending', 'paid', 'refunded'],
                ],
                'amount_paid' => [
                    'type' => 'number',
                    'required' => false,
                ],
            ],
        ],
    ]);
}

// Skema data Jamaah untuk validasi
function umh_get_jamaah_schema($is_update = false) {
    $schema = [
        'package_id' => ['type' => 'integer', 'required' => !$is_update],
        'user_id' => ['type' => 'integer', 'required' => false],
        'full_name' => ['type' => 'string', 'required' => !$is_update],
        'id_number' => ['type' => 'string', 'required' => !$is_update],
        'passport_number' => ['type' => 'string', 'required' => false],
        'phone' => ['type' => 'string', 'required' => false],
        'email' => ['type' => 'string', 'format' => 'email', 'required' => false],
        'address' => ['type' => 'string', 'required' => false],
        'gender' => ['type' => 'string', 'enum' => ['male', 'female'], 'required' => false],
        'birth_date' => ['type' => 'string', 'format' => 'date', 'required' => false],
        'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'waitlist'], 'default' => 'pending'],
        'payment_status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'refunded'], 'default' => 'pending'],
        'total_price' => ['type' => 'number', 'required' => false],
        'amount_paid' => ['type' => 'number', 'default' => 0],
        'notes' => ['type' => 'string', 'required' => false],
    ];

    if ($is_update) {
        foreach ($schema as $key => &$field) {
            $field['required'] = false;
        }
    }

    return $schema;
}


// Callback: Get All Jamaah
function umh_get_all_jamaah(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_jamaah';
    $package_table = $wpdb->prefix . 'umh_packages';

    // Ambil parameter query
    $package_id = $request->get_param('package_id');
    $status = $request->get_param('status');
    $payment_status = $request->get_param('payment_status');

    $query = "SELECT j.*, p.package_name FROM $table_name j LEFT JOIN $package_table p ON j.package_id = p.id WHERE 1=1";

    if (!empty($package_id)) {
        $query .= $wpdb->prepare(" AND j.package_id = %d", $package_id);
    }
    if (!empty($status)) {
        $query .= $wpdb->prepare(" AND j.status = %s", $status);
    }
    if (!empty($payment_status)) {
        $query .= $wpdb->prepare(" AND j.payment_status = %s", $payment_status);
    }

    $results = $wpdb->get_results($query, ARRAY_A);
    
    if ($results === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    return new WP_REST_Response($results, 200);
}

// Callback: Create Jamaah
function umh_create_jamaah(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_jamaah';

    $data = $request->get_json_params();
    
    // Ambil harga paket jika total_price tidak diset
    if (!isset($data['total_price']) && isset($data['package_id'])) {
        $package_table = $wpdb->prefix . 'umh_packages';
        $price = $wpdb->get_var($wpdb->prepare("SELECT price FROM $package_table WHERE id = %d", $data['package_id']));
        $data['total_price'] = $price ? (float)$price : 0;
    }
    
    // Set default values jika tidak ada
    $data['amount_paid'] = $data['amount_paid'] ?? 0;
    $data['status'] = $data['status'] ?? 'pending';
    $data['payment_status'] = $data['payment_status'] ?? 'pending';
    $data['created_at'] = current_time('mysql');

    // Filter data sesuai skema
    $schema = umh_get_jamaah_schema();
    $insert_data = [];
    foreach ($schema as $key => $value) {
        if (isset($data[$key])) {
            $insert_data[$key] = $data[$key];
        }
    }
    // Tambahkan field non-skema
    $insert_data['created_at'] = current_time('mysql');
    $insert_data['updated_at'] = current_time('mysql');


    $result = $wpdb->insert($table_name, $insert_data);

    if ($result === false) {
        return new WP_Error('db_error', __('Failed to create jamaah.', 'umh'), ['status' => 500, 'db_error' => $wpdb->last_error]);
    }

    $new_id = $wpdb->insert_id;
    // umh_create_log_entry('create', 'jamaah', $new_id, $data); // Asumsi fungsi log ada

    return new WP_REST_Response(['id' => $new_id, 'message' => 'Jamaah created successfully.'], 201);
}

// Callback: Get Jamaah by ID
function umh_get_jamaah_by_id(WP_REST_Request $request) {
    global $wpdb;
    $id = (int)$request['id'];
    $table_name = $wpdb->prefix . 'umh_jamaah';
    $package_table = $wpdb->prefix . 'umh_packages';
    
    $query = $wpdb->prepare("SELECT j.*, p.package_name FROM $table_name j LEFT JOIN $package_table p ON j.package_id = p.id WHERE j.id = %d", $id);
    $jamaah = $wpdb->get_row($query, ARRAY_A);

    if (!$jamaah) {
        return new WP_Error('not_found', __('Jamaah not found.', 'umh'), ['status' => 404]);
    }

    return new WP_REST_Response($jamaah, 200);
}

// Callback: Update Jamaah
function umh_update_jamaah(WP_REST_Request $request) {
    global $wpdb;
    $id = (int)$request['id'];
    $table_name = $wpdb->prefix . 'umh_jamaah';

    $data = $request->get_json_params();
    $data['updated_at'] = current_time('mysql');
    
    // Filter data sesuai skema
    $schema = umh_get_jamaah_schema(true);
    $update_data = [];
     foreach ($schema as $key => $value) {
        if (isset($data[$key])) {
            $update_data[$key] = $data[$key];
        }
    }
    // Tambahkan field non-skema
    $update_data['updated_at'] = current_time('mysql');

    if (empty($update_data)) {
         return new WP_Error('bad_request', __('No data provided for update.', 'umh'), ['status' => 400]);
    }

    $result = $wpdb->update($table_name, $update_data, ['id' => $id]);

    if ($result === false) {
        return new WP_Error('db_error', __('Failed to update jamaah.', 'umh'), ['status' => 500, 'db_error' => $wpdb->last_error]);
    }
    
    if ($result === 0) {
        return new WP_REST_Response(['message' => 'No changes detected.'], 200);
    }

    // umh_create_log_entry('update', 'jamaah', $id, $data); // Asumsi fungsi log ada

    return new WP_REST_Response(['id' => $id, 'message' => 'Jamaah updated successfully.'], 200);
}

// Callback: Delete Jamaah
function umh_delete_jamaah(WP_REST_Request $request) {
    global $wpdb;
    $id = (int)$request['id'];
    $table_name = $wpdb->prefix . 'umh_jamaah';

    $result = $wpdb->delete($table_name, ['id' => $id]);

    if ($result === false) {
        return new WP_Error('db_error', __('Failed to delete jamaah.', 'umh'), ['status' => 500]);
    }
    
    if ($result === 0) {
        return new WP_Error('not_found', __('Jamaah not found to delete.', 'umh'), ['status' => 404]);
    }

    // umh_create_log_entry('delete', 'jamaah', $id); // Asumsi fungsi log ada

    return new WP_REST_Response(['id' => $id, 'message' => 'Jamaah deleted successfully.'], 200);
}


// --- FUNGSI DARI API-MANIFEST.PHP YANG DIGABUNG ---

// Callback: Update Payment Status (dari api-manifest.php)
function umh_update_jamaah_payment(WP_REST_Request $request) {
    global $wpdb;
    $id = (int) $request['id'];
    $table_name = $wpdb->prefix . 'umh_jamaah';

    $data = $request->get_json_params();
    $update_data = [
        'payment_status' => $data['payment_status'],
        'updated_at' => current_time('mysql'),
    ];

    if (isset($data['amount_paid'])) {
        $update_data['amount_paid'] = (float)$data['amount_paid'];
    }

    $result = $wpdb->update($table_name, $update_data, ['id' => $id]);

    if ($result === false) {
        return new WP_Error('db_error', __('Failed to update payment status.', 'umh'), ['status' => 500]);
    }

    // umh_create_log_entry('update_payment', 'jamaah', $id, $update_data);

    // TODO: Integrasi dengan api-finance untuk mencatat transaksi ini secara otomatis
    // if ($data['payment_status'] == 'paid') {
    //    umh_create_finance_entry(...);
    // }

    return new WP_REST_Response(['id' => $id, 'message' => 'Payment status updated.'], 200);
}