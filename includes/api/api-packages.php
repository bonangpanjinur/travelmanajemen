<?php
/**
 * API endpoints for packages
 *
 * PERBAIKAN (15/11/2025):
 * - Direfaktor untuk menggunakan pola pewarisan (inheritance) yang benar.
 * - Memanggil parent::__construct() untuk mendaftarkan rute standar.
 * - Meng-override register_routes() untuk menambahkan rute kustom (/relations).
 * - Memindahkan fungsi global ke dalam metode kelas.
 * - Mengganti `add_action` di akhir file dengan `new UMH_Packages_API_Controller();`
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class UMH_Packages_API_Controller extends UMH_CRUD_Controller {
    
    public function __construct() {
        // 1. Definisikan Schema
        $schema = [
            'name'        => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            'description' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
            'category_id' => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            'base_price'  => ['type' => 'number', 'required' => true],
            'duration'    => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
            'status'      => ['type' => 'string', 'required' => false, 'default' => 'draft', 'enum' => ['draft', 'publish']],
            'capacity'    => ['type' => 'integer', 'required' => false, 'default' => 0, 'sanitize_callback' => 'absint'],
            'start_date'  => ['type' => 'string', 'format' => 'date', 'required' => false],
            'end_date'    => ['type' => 'string', 'format' => 'date', 'required' => false],
            'created_at'  => ['type' => 'datetime', 'readonly' => true],
            'updated_at'  => ['type' => 'datetime', 'readonly' => true],
        ];

        // 2. Definisikan Izin
        $permissions = [
            'get_items'    => ['owner', 'admin_staff', 'ops_staff', 'finance_staff', 'marketing_staff'],
            'get_item'     => ['owner', 'admin_staff', 'ops_staff', 'finance_staff', 'marketing_staff'],
            'create_item'  => ['owner', 'admin_staff'],
            'update_item'  => ['owner', 'admin_staff'],
            'delete_item'  => ['owner', 'admin_staff'],
        ];

        // 3. Definisikan Kolom Pencarian
        $searchable_fields = ['name', 'description'];

        // 4. Panggil Parent Constructor
        // Ini akan secara otomatis memanggil add_action('rest_api_init', [$this, 'register_routes']);
        parent::__construct(
            'packages',          // $resource_name
            'umh_packages',      // $table_slug
            $schema,             // $schema
            $permissions,        // $permissions
            $searchable_fields   // $searchable_fields
        );
    }

    /**
     * Override register_routes untuk menambahkan endpoint kustom.
     */
    public function register_routes() {
        // 1. Daftarkan rute CRUD standar (GET, POST, PUT, DELETE) dari parent
        parent::register_routes();

        $namespace = 'umh/v1';
        $base = $this->resource_name; // 'packages'

        // 2. Daftarkan rute kustom untuk /relations
        $relations_permissions = umh_check_api_permission($this->permissions['get_item']); // Izin sama
        $update_relations_permissions = umh_check_api_permission($this->permissions['update_item']); // Izin sama

        register_rest_route($namespace, '/' . $base . '/(?P<id>\d+)/relations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_package_relations'],
                'permission_callback' => $relations_permissions,
            ],
            [
                'methods' => WP_REST_Server::EDITABLE, // PUT/PATCH
                'callback' => [$this, 'save_package_relations'],
                'permission_callback' => $update_relations_permissions,
            ],
        ]);
        
        // 3. Override rute GET tunggal untuk memanggil 'get_item_with_relations'
        register_rest_route($namespace, '/' . $base . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item_with_relations'], // Override callback
                'permission_callback' => $relations_permissions,
            ],
            // Rute PUT dan DELETE dari parent::register_routes() masih berlaku
            // tapi kita perlu mendaftarkannya lagi di sini agar tidak tertimpa
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item'],
                'permission_callback' => $update_relations_permissions,
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item'],
                'permission_callback' => umh_check_api_permission($this->permissions['delete_item'] ?? ['owner']),
            ],
        ]);
    }
    
    // Override get_base_query untuk join
    protected function get_base_query() {
        global $wpdb;
        $packages_table = $this->table_name;
        $categories_table = $wpdb->prefix . 'umh_categories';
        
        // 'p' adalah alias untuk tabel paket (this->table_name)
        return "SELECT p.*, c.name as category_name 
                FROM {$packages_table} p
                LEFT JOIN {$categories_table} c ON p.category_id = c.id";
    }

    // Override get_item_by_id untuk join
    protected function get_item_by_id($id) {
        global $wpdb;
        // 'p' adalah alias dari get_base_query
        $query = $this->get_base_query() . $wpdb->prepare(" WHERE p.id = %d", $id);
        return $wpdb->get_row($query);
    }
    
    // Override get_searchable_columns
    protected function get_searchable_columns() {
        // 'category_name' adalah alias dari JOIN di get_base_query
        return ['name', 'description', 'category_name']; 
    }
    
    // Callback kustom: Get item WITH relations
    public function get_item_with_relations($request) {
        $id = (int) $request['id'];
        $item = $this->get_item_by_id($id);

        if (empty($item)) {
            return new WP_Error('not_found', $this->resource_name . ' not found', ['status' => 404]);
        }
        
        $relations = $this->get_package_relations_data($id);
        $item->package_prices = $relations['package_prices'];
        $item->package_flights = $relations['package_flights'];
        $item->package_hotels = $relations['package_hotels'];
        
        return new WP_REST_Response($item, 200);
    }

    /**
     * Get related data for a package
     */
    public function get_package_relations($request) {
        $id = (int) $request['id'];
        $data = $this->get_package_relations_data($id);
        return new WP_REST_Response($data, 200);
    }

    /**
     * Helper: Ambil data relasi
     */
    private function get_package_relations_data($package_id) {
        global $wpdb;
        
        $prices_table = $wpdb->prefix . 'umh_package_prices';
        $prices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prices_table} WHERE package_id = %d", 
            $package_id
        ));
        
        $flights_table = $wpdb->prefix . 'umh_package_flights';
        $flights_data_table = $wpdb->prefix . 'umh_flights';
        $flights = $wpdb->get_results($wpdb->prepare(
            "SELECT f.* FROM {$flights_data_table} f
             JOIN {$flights_table} pf ON f.id = pf.flight_id
             WHERE pf.package_id = %d",
            $package_id
        ));
        
        $hotels_table = $wpdb->prefix . 'umh_package_hotels';
        $hotels_data_table = $wpdb->prefix . 'umh_hotels';
        $hotels = $wpdb->get_results($wpdb->prepare(
            "SELECT h.* FROM {$hotels_data_table} h
             JOIN {$hotels_table} ph ON h.id = ph.hotel_id
             WHERE ph.package_id = %d",
            $package_id
        ));
        
        return [
            'package_prices' => $prices,
            'package_flights' => $flights,
            'package_hotels' => $hotels,
        ];
    }

    /**
     * Save related data for a package (Prices, Flights, Hotels)
     */
    public function save_package_relations($request) {
        global $wpdb;
        $id = (int) $request['id'];

        $package_exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d", $id));
        if (!$package_exists) {
            return new WP_Error('not_found', 'Package not found.', ['status' => 404]);
        }

        $table_prices = $wpdb->prefix . 'umh_package_prices';
        $table_flights = $wpdb->prefix . 'umh_package_flights';
        $table_hotels = $wpdb->prefix . 'umh_package_hotels';

        $package_prices = $request->get_param('package_prices') ?: [];
        $package_flights = $request->get_param('package_flights') ?: [];
        $package_hotels = $request->get_param('package_hotels') ?: [];
        
        $wpdb->query('START TRANSACTION');

        // 1. Proses Harga
        $wpdb->delete($table_prices, ['package_id' => $id], ['%d']);
        foreach ($package_prices as $price) {
            $result = $wpdb->insert($table_prices, [
                'package_id' => $id,
                'room_type' => sanitize_text_field($price['room_type']),
                'price' => floatval($price['price']),
            ]);
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Gagal menyimpan harga paket.', ['status' => 500]);
            }
        }

        // 2. Proses Penerbangan
        $wpdb->delete($table_flights, ['package_id' => $id], ['%d']);
        foreach ($package_flights as $flight_id) {
            $result = $wpdb->insert($table_flights, [
                'package_id' => $id,
                'flight_id' => intval($flight_id),
            ]);
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Gagal menyimpan penerbangan paket.', ['status' => 500]);
            }
        }

        // 3. Proses Hotel
        $wpdb->delete($table_hotels, ['package_id' => $id], ['%d']);
        foreach ($package_hotels as $hotel_id) {
            $result = $wpdb->insert($table_hotels, [
                'package_id' => $id,
                'hotel_id' => intval($hotel_id),
            ]);
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Gagal menyimpan hotel paket.', ['status' => 500]);
            }
        }

        $wpdb->query('COMMIT');

        $new_relations = $this->get_package_relations_data($id);
        return new WP_REST_Response($new_relations, 200);
    }
}

// Instansiasi controller untuk mendaftarkan hook
new UMH_Packages_API_Controller();