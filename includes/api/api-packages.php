<?php
/**
 * File: includes/api/api-packages.php
 *
 * MODIFIKASI TOTAL:
 * - Menghapus UMH_CRUD_Controller.
 * - Membuat endpoint kustom untuk CRUD Paket.
 * - Endpoint GET sekarang me-load data relasi (harga, pesawat, hotel).
 * - Endpoint POST/PUT sekarang menyimpan data relasi ke tabel terkait.
 *
 * PERBAIKAN 15/11/2025:
 * - Mengubah umh_get_packages agar mendukung Paginasi dan Pencarian.
 * - Mengubah respon agar sesuai standar: { data: [...], total_items: X, ... }
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_custom_packages_routes');

function umh_register_custom_packages_routes() {
    $namespace = 'umh/v1';
    $base = 'packages';

    // Izin
    $read_permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff']);
    $write_permissions = umh_check_api_permission(['owner', 'admin_staff']);
    $delete_permissions = umh_check_api_permission(['owner']);

    // Rute untuk koleksi (GET /packages, POST /packages)
    register_rest_route($namespace, '/' . $base, [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'umh_get_packages',
            'permission_callback' => $read_permissions,
        ],
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'umh_create_package',
            'permission_callback' => $write_permissions,
            'args'                => umh_get_package_schema(),
        ],
    ]);

    // Rute untuk satu item (GET, PUT, DELETE /packages/123)
    register_rest_route($namespace, '/' . $base . '/(?P<id>\d+)', [
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'umh_get_package',
            'permission_callback' => $read_permissions,
        ],
        [
            'methods'             => WP_REST_Server::EDITABLE, // PUT/PATCH
            'callback'            => 'umh_update_package',
            'permission_callback' => $write_permissions,
            'args'                => umh_get_package_schema(true),
        ],
        [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'umh_delete_package',
            'permission_callback' => $delete_permissions,
        ],
    ]);
}

/**
 * Skema data paket untuk validasi
 */
function umh_get_package_schema($is_update = false) {
    $schema = [
        'name'            => ['type' => 'string', 'required' => !$is_update],
        'category_id'     => ['type' => 'integer', 'required' => !$is_update],
        'description'     => ['type' => 'string', 'required' => false],
        'departure_date'  => ['type' => 'string', 'format' => 'date', 'required' => false],
        'duration_days'   => ['type' => 'integer', 'required' => false],
        'status'          => ['type' => 'string', 'default' => 'draft', 'enum' => ['draft', 'published', 'archived']],
        
        // Data relasional baru
        'prices'          => ['type' => 'array', 'required' => false, 'items' => ['type' => 'object']],
        'flight_ids'      => ['type' => 'array', 'required' => false, 'items' => ['type' => 'integer']],
        'hotel_bookings'  => ['type' => 'array', 'required' => false, 'items' => ['type' => 'object']],
    ];

    if ($is_update) {
        foreach ($schema as $key => &$field) {
            $field['required'] = false;
        }
    }
    return $schema;
}

/**
 * Helper: Mengambil data relasi untuk satu paket
 */
function umh_get_package_relations($package_id) {
    global $wpdb;
    
    // 1. Get Prices
    $prices_table = $wpdb->prefix . 'umh_package_prices';
    $prices = $wpdb->get_results($wpdb->prepare(
        "SELECT room_type, price FROM $prices_table WHERE package_id = %d", $package_id
    ), ARRAY_A);

    // 2. Get Flight IDs
    $flights_table = $wpdb->prefix . 'umh_flight_bookings';
    $flight_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT flight_id FROM $flights_table WHERE package_id = %d", $package_id
    ));
    // Ubah string jadi integer
    $flight_ids = array_map('intval', $flight_ids);

    // 3. Get Hotel Bookings
    $hotels_table = $wpdb->prefix . 'umh_hotel_bookings';
    $hotel_bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT hotel_id, check_in_date, check_out_date FROM $hotels_table WHERE package_id = %d", $package_id
    ), ARRAY_A);

    return [
        'prices'         => $prices,
        'flight_ids'     => $flight_ids,
        'hotel_bookings' => $hotel_bookings,
    ];
}

/**
 * Helper: Menyimpan data relasi
 */
function umh_save_package_relations($package_id, $params) {
    global $wpdb;

    // 1. Simpan Prices
    if (isset($params['prices']) && is_array($params['prices'])) {
        $prices_table = $wpdb->prefix . 'umh_package_prices';
        // Hapus harga lama
        $wpdb->delete($prices_table, ['package_id' => $package_id], ['%d']);
        // Tambah harga baru
        foreach ($params['prices'] as $price_item) {
            if (!empty($price_item['room_type']) && isset($price_item['price'])) {
                $wpdb->insert($prices_table, [
                    'package_id' => $package_id,
                    'room_type'  => sanitize_text_field($price_item['room_type']),
                    'price'      => (float) $price_item['price'],
                ]);
            }
        }
    }

    // 2. Simpan Flights
    if (isset($params['flight_ids']) && is_array($params['flight_ids'])) {
        $flights_table = $wpdb->prefix . 'umh_flight_bookings';
        $wpdb->delete($flights_table, ['package_id' => $package_id], ['%d']);
        foreach ($params['flight_ids'] as $flight_id) {
            $wpdb->insert($flights_table, [
                'package_id' => $package_id,
                'flight_id'  => (int) $flight_id,
                'status'     => 'confirmed', // Default
            ]);
        }
    }

    // 3. Simpan Hotels
    if (isset($params['hotel_bookings']) && is_array($params['hotel_bookings'])) {
        $hotels_table = $wpdb->prefix . 'umh_hotel_bookings';
        $wpdb->delete($hotels_table, ['package_id' => $package_id], ['%d']);
        foreach ($params['hotel_bookings'] as $booking) {
            if (!empty($booking['hotel_id'])) {
                $wpdb->insert($hotels_table, [
                    'package_id'     => $package_id,
                    'hotel_id'       => (int) $booking['hotel_id'],
                    'check_in_date'  => sanitize_text_field($booking['check_in_date']),
                    'check_out_date' => sanitize_text_field($booking['check_out_date']),
                    'status'         => 'confirmed', // Default
                ]);
            }
        }
    }
}


/**
 * Callback: GET /packages
 * PERBAIKAN: Ditambahkan logika Paginasi dan Pencarian
 */
function umh_get_packages(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';

    // Paginasi
    $page = (int) $request->get_param('page');
    $per_page = 20; // Tetapkan jumlah item per halaman
    if ($page < 1) {
        $page = 1;
    }
    $offset = ($page - 1) * $per_page;

    // Pencarian
    $search = $request->get_param('search');
    $where_clauses = [];
    $query_params = [];

    if (!empty($search)) {
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = "(name LIKE %s OR description LIKE %s)";
        $query_params[] = $search_like;
        $query_params[] = $search_like;
    }

    $where_sql = "";
    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(' AND ', $where_clauses);
    }

    // Ambil Total Item (untuk paginasi)
    $total_query = "SELECT COUNT(id) FROM {$table_name}{$where_sql}";
    $total_items = (int) $wpdb->get_var(
        empty($query_params) ? $total_query : $wpdb->prepare($total_query, $query_params)
    );
    $total_pages = ceil($total_items / $per_page);

    // Ambil Data Item (dengan limit dan offset)
    $data_query = "SELECT * FROM {$table_name}{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $packages = $wpdb->get_results(
        $wpdb->prepare($data_query, $query_params),
        ARRAY_A
    );
    
    if ($packages === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    // Loop dan tambahkan data relasi
    foreach ($packages as $key => $package) {
        $relations = umh_get_package_relations($package['id']);
        $packages[$key] = array_merge($package, $relations);
    }

    // Kembalikan dalam format objek baru
    $response = [
        'data'         => $packages,
        'total_items'  => $total_items,
        'total_pages'  => $total_pages,
        'current_page' => $page,
    ];

    return new WP_REST_Response($response, 200);
}

/**
 * Callback: GET /packages/{id}
 */
function umh_get_package(WP_REST_Request $request) {
    global $wpdb;
    $id = (int) $request['id'];
    $table_name = $wpdb->prefix . 'umh_packages';

    $package = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
    
    if (!$package) {
        return new WP_Error('not_found', __('Package not found.', 'umh'), ['status' => 404]);
    }

    // Ambil data relasi
    $relations = umh_get_package_relations($id);
    $package = array_merge($package, $relations);

    return new WP_REST_Response($package, 200);
}

/**
 * Callback: POST /packages
 */
function umh_create_package(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_packages';
    $params = $request->get_json_params();

    // 1. Siapkan data utama paket
    $main_data = [
        'name'           => sanitize_text_field($params['name']),
        'category_id'    => (int) $params['category_id'],
        'description'    => sanitize_textarea_field($params['description']),
        'departure_date' => sanitize_text_field($params['departure_date']),
        'duration_days'  => (int) $params['duration_days'],
        'status'         => sanitize_text_field($params['status']),
        'created_at'     => current_time('mysql'),
        'updated_at'     => current_time('mysql'),
    ];

    // 2. Insert data utama
    $result = $wpdb->insert($table_name, $main_data);
    
    if ($result === false) {
        return new WP_Error('db_error', __('Failed to create package.', 'umh'), ['status' => 500, 'db_error' => $wpdb->last_error]);
    }
    
    $new_id = $wpdb->insert_id;

    // 3. Simpan data relasi (harga, pesawat, hotel)
    umh_save_package_relations($new_id, $params);

    // 4. Ambil data lengkap yang baru dibuat
    $new_package_request = new WP_REST_Request('GET', "/umh/v1/packages/{$new_id}");
    $new_package_request->set_param('id', $new_id);
    
    return umh_get_package($new_package_request);
}

/**
 * Callback: PUT /packages/{id}
 */
function umh_update_package(WP_REST_Request $request) {
    global $wpdb;
    $id = (int) $request['id'];
    $table_name = $wpdb->prefix . 'umh_packages';
    $params = $request->get_json_params();

    // 1. Cek apakah paket ada
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
    if (!$existing) {
        return new WP_Error('not_found', __('Package not found.', 'umh'), ['status' => 404]);
    }

    // 2. Siapkan data utama
    $main_data = [
        'name'           => sanitize_text_field($params['name']),
        'category_id'    => (int) $params['category_id'],
        'description'    => sanitize_textarea_field($params['description']),
        'departure_date' => sanitize_text_field($params['departure_date']),
        'duration_days'  => (int) $params['duration_days'],
        'status'         => sanitize_text_field($params['status']),
        'updated_at'     => current_time('mysql'),
    ];

    // 3. Update data utama
    $wpdb->update($table_name, $main_data, ['id' => $id]);

    // 4. Simpan (Update) data relasi (ini akan menghapus yg lama dan menambah yg baru)
    umh_save_package_relations($id, $params);

    // 5. Ambil data lengkap yang baru diupdate
    $updated_package_request = new WP_REST_Request('GET', "/umh/v1/packages/{$id}");
    $updated_package_request->set_param('id', $id);
    
    return umh_get_package($updated_package_request);
}

/**
 * Callback: DELETE /packages/{id}
 */
function umh_delete_package(WP_REST_Request $request) {
    global $wpdb;
    $id = (int) $request['id'];

    // Hapus dari tabel relasi terlebih dahulu
    $wpdb->delete($wpdb->prefix . 'umh_package_prices', ['package_id' => $id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'umh_flight_bookings', ['package_id' => $id], ['%d']);
    $wpdb->delete($wpdb->prefix . 'umh_hotel_bookings', ['package_id' => $id], ['%d']);
    
    // Hapus dari tabel utama
    $result = $wpdb->delete($wpdb->prefix . 'umh_packages', ['id' => $id], ['%d']);

    if ($result === false) {
        return new WP_Error('db_error', __('Failed to delete item.', 'umh'), ['status' => 500]);
    }
    if ($result === 0) {
        return new WP_Error('not_found', __('Item not found to delete.', 'umh'), ['status' => 404]);
    }

    return new WP_REST_Response(['id' => $id, 'message' => 'Package and all related data deleted successfully.'], 200);
}