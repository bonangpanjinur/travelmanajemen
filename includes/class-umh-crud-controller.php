<?php
/**
 * File: includes/class-umh-crud-controller.php
 *
 * PENINGKATAN (Item 4):
 * - Konstruktor dimodifikasi untuk menerima `$searchable_fields`.
 * - Metode `get_items` di-upgrade total untuk menangani:
 * - Pencarian (Search) via query parameter `?search=...`
 * - Paginasi (Pagination) via query parameter `?page=...`
 * - Respon dari `get_items` diubah menjadi objek:
 * { data: [...], total_items: X, total_pages: Y, current_page: Z }
 *
 * [PERBAIKAN 15/11/2025]:
 * - Mengganti `do_action` dengan `apply_filters` untuk hook
 * before_create dan before_update agar bisa memodifikasi data.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class UMH_CRUD_Controller {
    protected $resource_name;
    protected $table_name;
    protected $schema;
    protected $permissions;
    protected $searchable_fields; // BARU: Kolom yang bisa dicari

    public function __construct($resource_name, $table_slug, $schema, $permissions = [], $searchable_fields = []) {
        global $wpdb;
        $this->resource_name = $resource_name;
        $this->table_name = $wpdb->prefix . $table_slug;
        $this->schema = $schema;
        $this->permissions = $permissions;
        $this->searchable_fields = $searchable_fields; // BARU

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $namespace = 'umh/v1';

        register_rest_route($namespace, '/' . $this->resource_name, [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => [$this, 'check_permission_get_items'],
            ],
            [
                'methods'  => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item'],
                'permission_callback' => [$this, 'check_permission_create_item'],
            ],
        ]);

        register_rest_route($namespace, '/' . $this->resource_name . '/(?P<id>[\d]+)', [
            [
                'methods'  => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => [$this, 'check_permission_get_item'],
            ],
            [
                'methods'  => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => [$this, 'check_permission_update_item'],
            ],
            [
                'methods'  => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => [$this, 'check_permission_delete_item'],
            ],
        ]);
    }

    // --- (PENINGKATAN 4) Logika Paginasi dan Pencarian ---
    public function get_items($request) {
        global $wpdb;

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

        if (!empty($search) && !empty($this->searchable_fields)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $search_parts = [];
            foreach ($this->searchable_fields as $field) {
                $search_parts[] = "{$field} LIKE %s";
                $query_params[] = $search_like;
            }
            $where_clauses[] = "(" . implode(' OR ', $search_parts) . ")";
        }

        $where_sql = "";
        if (!empty($where_clauses)) {
            $where_sql = " WHERE " . implode(' AND ', $where_clauses);
        }

        // Ambil Total Item (untuk paginasi)
        $total_query = "SELECT COUNT(id) FROM {$this->table_name}{$where_sql}";
        $total_items = (int) $wpdb->get_var(
            empty($query_params) ? $total_query : $wpdb->prepare($total_query, $query_params)
        );
        $total_pages = ceil($total_items / $per_page);

        // Ambil Data Item (dengan limit dan offset)
        $data_query = "SELECT * FROM {$this->table_name}{$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $query_params[] = $per_page;
        $query_params[] = $offset;

        $items = $wpdb->get_results(
            $wpdb->prepare($data_query, $query_params)
        );

        // Kembalikan dalam format objek baru
        $response = [
            'data'         => $items,
            'total_items'  => $total_items,
            'total_pages'  => $total_pages,
            'current_page' => $page,
        ];

        return new WP_REST_Response($response, 200);
    }
    // --- Akhir Peningkatan 4 ---

    public function get_item($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id));

        if (!$item) {
            return new WP_Error('not_found', 'Item not found', ['status' => 404]);
        }
        return new WP_REST_Response($item, 200);
    }

    public function create_item($request) {
        global $wpdb;
        $params = $request->get_json_params();
        $prepared_data = $this->validate_and_sanitize($params);

        if (is_wp_error($prepared_data)) {
            return $prepared_data;
        }

        // Tambahkan timestamp
        $prepared_data['created_at'] = current_time('mysql');
        $prepared_data['updated_at'] = current_time('mysql');
        
        // PERBAIKAN: Gunakan apply_filters untuk mengizinkan modifikasi data
        $prepared_data = apply_filters("umh_crud_{$this->resource_name}_before_create", $prepared_data, $request);

        $result = $wpdb->insert($this->table_name, $prepared_data);

        if ($result === false) {
            return new WP_Error('db_error', 'Could not create item', ['status' => 500, 'db_error' => $wpdb->last_error]);
        }

        $new_id = $wpdb->insert_id;
        $new_item = $this->get_item(['id' => $new_id])->get_data();

        // Hook setelah create
        do_action("umh_crud_{$this->resource_name}_after_create", $new_item, $request);

        return new WP_REST_Response($new_item, 201);
    }

    public function update_item($request) {
        global $wpdb;
        $id = (int) $request['id'];
        $params = $request->get_json_params();

        // Cek item ada
        $existing_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id));
        if (!$existing_item) {
            return new WP_Error('not_found', 'Item not found', ['status' => 404]);
        }

        $prepared_data = $this->validate_and_sanitize($params, true); // true = is update

        if (is_wp_error($prepared_data)) {
            return $prepared_data;
        }
        
        // Jangan update created_at
        unset($prepared_data['created_at']);
        // Update updated_at
        $prepared_data['updated_at'] = current_time('mysql');
        
        // PERBAIKAN: Gunakan apply_filters untuk mengizinkan modifikasi data
        $prepared_data = apply_filters("umh_crud_{$this->resource_name}_before_update", $prepared_data, $request, $id, $existing_item);

        $result = $wpdb->update($this->table_name, $prepared_data, ['id' => $id]);

        if ($result === false) {
            return new WP_Error('db_error', 'Could not update item', ['status' => 500, 'db_error' => $wpdb->last_error]);
        }

        $updated_item = $this->get_item(['id' => $id])->get_data();
        
        // Hook setelah update
        do_action("umh_crud_{$this->resource_name}_after_update", $updated_item, $request);

        return new WP_REST_Response($updated_item, 200);
    }

    public function delete_item($request) {
        global $wpdb;
        $id = (int) $request['id'];

        // Cek item ada
        $existing_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id));
        if (!$existing_item) {
            return new WP_Error('not_found', 'Item not found', ['status' => 404]);
        }
        
        // Hook sebelum delete
        do_action("umh_crud_{$this->resource_name}_before_delete", $id, $existing_item);

        $result = $wpdb->delete($this->table_name, ['id' => $id]);

        if ($result === false) {
            return new WP_Error('db_error', 'Could not delete item', ['status' => 500]);
        }
        
        // Hook setelah delete
        do_action("umh_crud_{$this->resource_name}_after_delete", $id, $existing_item);

        return new WP_REST_Response(['deleted' => true, 'id' => $id], 200);
    }

    protected function validate_and_sanitize($params, $is_update = false) {
        $prepared_data = [];
        
        foreach ($this->schema as $key => $rules) {
            $value_exists = isset($params[$key]);
            $value = $value_exists ? $params[$key] : null;

            // Cek 'required'
            if (!$is_update && !empty($rules['required']) && !$value_exists) {
                return new WP_Error('bad_request', "Field '$key' is required.", ['status' => 400]);
            }

            // Hanya proses jika value ada di params
            if ($value_exists) {
                // Sanitasi
                $sanitized_value = $value;
                if (!empty($rules['sanitize_callback']) && is_callable($rules['sanitize_callback'])) {
                    $sanitized_value = call_user_func($rules['sanitize_callback'], $sanitized_value);
                }

                // TODO: Validasi (type, format, dll)
                // ... (bisa ditambahkan validasi lebih ketat di sini)

                $prepared_data[$key] = $sanitized_value;
            }
        }
        
        return $prepared_data;
    }

    // --- Cek Izin ---
    protected function check_permission($action) {
        if (!isset($this->permissions[$action])) {
            return true; // Default boleh jika tidak diset
        }
        $required_roles = (array) $this->permissions[$action];
        return umh_check_api_permission($required_roles);
    }

    public function check_permission_get_items() {
        return $this->check_permission('get_items');
    }
    public function check_permission_get_item() {
        return $this->check_permission('get_item');
    }
    public function check_permission_create_item() {
        return $this->check_permission('create_item');
    }
    public function check_permission_update_item() {
        return $this->check_permission('update_item');
    }
    public function check_permission_delete_item() {
        return $this->check_permission('delete_item');
    }
}