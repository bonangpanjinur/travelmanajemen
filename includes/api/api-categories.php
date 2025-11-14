<?php
// File: includes/api/api-categories.php
// Mengelola endpoint untuk kategori (misal: keuangan).

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_categories_routes');

function umh_register_categories_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten
    $table_name = 'umh_categories'; // Menggunakan tabel UMH yang baru

    // PERBAIKAN: Tentukan izin
    $permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff']);
    $read_permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff']);

    // Endpoint untuk CRUD Kategori
    register_rest_route($namespace, '/categories', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function(WP_REST_Request $request) use ($table_name) {
                return umh_get_items($request, $table_name);
            },
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => function(WP_REST_Request $request) use ($table_name) {
                return umh_create_item($request, $table_name, ['name', 'type']);
            },
            'permission_callback' => $permissions, // PERBAIKAN
        ],
    ]);

    // Endpoint untuk satu Kategori (by ID)
    register_rest_route($namespace, '/categories/(?P<id>\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => function(WP_REST_Request $request) use ($table_name) {
                return umh_get_item_by_id($request, $table_name);
            },
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => function(WP_REST_Request $request) use ($table_name) {
                return umh_update_item($request, $table_name, ['name', 'type']);
            },
            'permission_callback' => $permissions, // PERBAIKAN
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => function(WP_REST_Request $request) use ($table_name) {
                return umh_delete_item($request, $table_name);
            },
            'permission_callback' => $permissions, // PERBAIKAN
        ],
    ]);
}

// Catatan: Fungsi umh_get_items, umh_create_item, dll.
// adalah fungsi generik yang ada di file template Anda (misal: api-tasks.php).
// Pastikan fungsi-fungsi tersebut ada dan berfungsi, atau ganti
// dengan implementasi CRUD yang spesifik seperti di api-jamaah.php.

// Jika fungsi generik belum ada, berikut implementasi sederhananya:

if (!function_exists('umh_get_items')) {
    function umh_get_items(WP_REST_Request $request, $table_slug) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table_slug;
        $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
        return new WP_REST_Response($results, 200);
    }
}

if (!function_exists('umh_get_item_by_id')) {
    function umh_get_item_by_id(WP_REST_Request $request, $table_slug) {
        global $wpdb;
        $id = (int) $request['id'];
        $table_name = $wpdb->prefix . $table_slug;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
        if (!$item) {
            return new WP_Error('not_found', __('Item not found.', 'umh'), ['status' => 404]);
        }
        return new WP_REST_Response($item, 200);
    }
}

if (!function_exists('umh_create_item')) {
    function umh_create_item(WP_REST_Request $request, $table_slug, $allowed_keys) {
        global $wpdb;
        $table_name = $wpdb->prefix . $table_slug;
        $data = $request->get_json_params();
        $insert_data = [];
        foreach ($allowed_keys as $key) {
            if (isset($data[$key])) {
                $insert_data[$key] = $data[$key];
            }
        }
        if (empty($insert_data)) {
            return new WP_Error('bad_request', __('No valid data provided.', 'umh'), ['status' => 400]);
        }
        
        $insert_data['created_at'] = current_time('mysql');
        $insert_data['updated_at'] = current_time('mysql');
        
        $wpdb->insert($table_name, $insert_data);
        return new WP_REST_Response(['id' => $wpdb->insert_id, 'message' => 'Item created.'], 201);
    }
}

if (!function_exists('umh_update_item')) {
    function umh_update_item(WP_REST_Request $request, $table_slug, $allowed_keys) {
        global $wpdb;
        $id = (int) $request['id'];
        $table_name = $wpdb->prefix . $table_slug;
        $data = $request->get_json_params();
        $update_data = [];
        foreach ($allowed_keys as $key) {
            if (isset($data[$key])) {
                $update_data[$key] = $data[$key];
            }
        }
        if (empty($update_data)) {
            return new WP_Error('bad_request', __('No valid data provided.', 'umh'), ['status' => 400]);
        }

        $update_data['updated_at'] = current_time('mysql');
        
        $wpdb->update($table_name, $update_data, ['id' => $id]);
        return new WP_REST_Response(['id' => $id, 'message' => 'Item updated.'], 200);
    }
}

if (!function_exists('umh_delete_item')) {
    function umh_delete_item(WP_REST_Request $request, $table_slug) {
        global $wpdb;
        $id = (int) $request['id'];
        $table_name = $wpdb->prefix . $table_slug;
        $wpdb->delete($table_name, ['id' => $id]);
        return new WP_REST_Response(['id' => $id, 'message' => 'Item deleted.'], 200);
    }
}