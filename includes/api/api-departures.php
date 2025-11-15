<?php
/**
 * API endpoints for departures
 *
 * PERBAIKAN (15/11/2025):
 * - Direfaktor untuk menggunakan pola pewarisan (inheritance) yang benar.
 * - Memanggil parent::__construct() untuk mendaftarkan rute standar.
 * - Mengganti `add_action` di akhir file dengan `new UMH_Departures_API_Controller();`
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class UMH_Departures_API_Controller extends UMH_CRUD_Controller {
    
    public function __construct() {
        // 1. Definisikan Schema
        $schema = [
            'departure_date' => ['type' => 'string', 'format' => 'date', 'required' => true],
            'return_date'    => ['type' => 'string', 'format' => 'date', 'required' => true],
            'package_id'     => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            'flight_id'      => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
            'status'         => ['type' => 'string', 'required' => false, 'default' => 'scheduled', 'enum' => ['scheduled', 'confirmed', 'completed', 'cancelled']],
            'notes'          => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
            'total_seats'    => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            'available_seats'=> ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
        ];
        
        // 2. Definisikan Izin
        $permissions = [
            'get_items'    => ['owner', 'admin_staff', 'ops_staff'],
            'get_item'     => ['owner', 'admin_staff', 'ops_staff'],
            'create_item'  => ['owner', 'admin_staff', 'ops_staff'],
            'update_item'  => ['owner', 'admin_staff', 'ops_staff'],
            'delete_item'  => ['owner', 'admin_staff'],
        ];

        // 3. Definisikan Kolom Pencarian
        $searchable_fields = ['package_name', 'airline_name', 'status', 'notes'];

        // 4. Panggil Parent Constructor
        // Ini akan secara otomatis memanggil add_action('rest_api_init', [$this, 'register_routes']);
        parent::__construct(
            'departures',        // $resource_name
            'umh_departures',    // $table_slug
            $schema,             // $schema
            $permissions,        // $permissions
            $searchable_fields   // $searchable_fields
        );
    }

    // Override get_base_query untuk join
    protected function get_base_query() {
        global $wpdb;
        // 'd' adalah alias untuk tabel departures (this->table_name)
        return "SELECT d.*, p.name as package_name, f.airline as airline_name
                FROM {$this->table_name} d
                LEFT JOIN {$wpdb->prefix}umh_packages p ON d.package_id = p.id
                LEFT JOIN {$wpdb->prefix}umh_flights f ON d.flight_id = f.id";
    }

    // Override get_item_by_id untuk join
    protected function get_item_by_id($id) {
        global $wpdb;
        // 'd' adalah alias dari get_base_query
        $query = $this->get_base_query() . $wpdb->prepare(" WHERE d.id = %d", $id);
        return $wpdb->get_row($query);
    }
}

// Instansiasi controller untuk mendaftarkan hook
new UMH_Departures_API_Controller();