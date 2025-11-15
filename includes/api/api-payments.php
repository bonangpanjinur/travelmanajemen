<?php
/**
 * API endpoints for payments
 *
 * PERBAIKAN (15/11/2025):
 * - Direfaktor untuk menggunakan pola pewarisan (inheritance) yang benar.
 * - Memindahkan logika transaksi (create, update, delete) ke dalam metode kelas.
 * - Meng-override register_routes() untuk menunjuk ke metode transaksional.
 * - Mengganti `add_action` di akhir file dengan `new UMH_Payments_API_Controller();`
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class UMH_Payments_API_Controller extends UMH_CRUD_Controller {
    
    public function __construct() {
        // 1. Definisikan Schema
        $schema = [
            'jamaah_id'      => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
            'amount'         => ['type' => 'number', 'required' => true],
            'payment_date'   => ['type' => 'string', 'format' => 'date', 'required' => true],
            'payment_method' => ['type' => 'string', 'required' => false, 'default' => 'cash', 'sanitize_callback' => 'sanitize_text_field'],
            'status'         => ['type' => 'string', 'required' => false, 'default' => 'pending', 'enum' => ['pending', 'confirmed', 'cancelled']],
            'notes'          => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
            'created_at'     => ['type' => 'datetime', 'readonly' => true],
            'updated_at'     => ['type' => 'datetime', 'readonly' => true],
            'created_by'     => ['type' => 'integer', 'readonly' => true],
        ];

        // 2. Definisikan Izin
        $permissions = [
            'get_items'    => ['owner', 'finance_staff', 'admin_staff'],
            'get_item'     => ['owner', 'finance_staff', 'admin_staff'],
            'create_item'  => ['owner', 'finance_staff', 'admin_staff'],
            'update_item'  => ['owner', 'finance_staff', 'admin_staff'],
            'delete_item'  => ['owner', 'finance_staff'],
        ];

        // 3. Definisikan Kolom Pencarian
        $searchable_fields = ['jamaah_name', 'payment_method', 'status', 'notes'];

        // 4. Panggil Parent Constructor
        parent::__construct(
            'payments',          // $resource_name
            'umh_payments',      // $table_slug
            $schema,             // $schema
            $permissions,        // $permissions
            $searchable_fields   // $searchable_fields
        );
    }

    /**
     * Override register_routes untuk menggunakan metode transaksional
     */
    public function register_routes() {
        $namespace = 'umh/v1';
        $base = $this->resource_name; // 'payments'

        $get_items_perm   = $this->permissions['get_items']   ?? ['owner'];
        $get_item_perm    = $this->permissions['get_item']    ?? ['owner'];
        $create_item_perm = $this->permissions['create_item'] ?? ['owner'];
        $update_item_perm = $this->permissions['update_item'] ?? ['owner'];
        $delete_item_perm = $this->permissions['delete_item'] ?? ['owner'];

        // Rute GET menggunakan metode parent
        register_rest_route($namespace, '/' . $base, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_items'],
                'permission_callback' => umh_check_api_permission($get_items_perm),
            ],
            // Rute POST menggunakan metode transaksional kustom
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_item_transaksional'], 
                'permission_callback' => umh_check_api_permission($create_item_perm),
            ],
        ]);

        register_rest_route($namespace, '/' . $base . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_item'],
                'permission_callback' => umh_check_api_permission($get_item_perm),
            ],
            // Rute PUT/PATCH menggunakan metode transaksional kustom
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_item_transaksional'],
                'permission_callback' => umh_check_api_permission($update_item_perm),
            ],
            // Rute DELETE menggunakan metode transaksional kustom
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_item_transaksional'],
                'permission_callback' => umh_check_api_permission($delete_item_perm),
            ],
        ]);
    }

    // Override get_base_query untuk join
    protected function get_base_query() {
        global $wpdb;
        $payments_table = $this->table_name;
        $jamaah_table = $wpdb->prefix . 'umh_jamaah';
        
        // 'p' adalah alias untuk tabel payments (this->table_name)
        return "SELECT p.*, j.full_name as jamaah_name 
                FROM {$payments_table} p
                LEFT JOIN {$jamaah_table} j ON p.jamaah_id = j.id";
    }

    // Override get_item_by_id untuk join
    protected function get_item_by_id($id) {
        global $wpdb;
        $query = $this->get_base_query() . $wpdb->prepare(" WHERE p.id = %d", $id);
        return $wpdb->get_row($query);
    }
    
    // Override prepare_item_for_db untuk add created_by
    public function prepare_item_for_db($request, $is_update = false) {
        $data = parent::prepare_item_for_db($request, $is_update);
        if (is_wp_error($data)) {
            return $data;
        }
        
        if (!$is_update) {
            // Dapatkan ID pengguna dari konteks (token atau cookie)
            $context = umh_get_current_user_context($request);
            if (!is_wp_error($context)) {
                 $data['created_by'] = $context['user_id'];
            }
        }
        return $data;
    }

    /**
     * Custom CREATE payment function to include transaction
     */
    public function create_item_transaksional($request) {
        global $wpdb;
        $data = $this->prepare_item_for_db($request);

        if (is_wp_error($data)) {
            return $data;
        }

        $wpdb->query('START TRANSACTION');

        $result = $wpdb->insert($this->table_name, $data);
        $new_id = $wpdb->insert_id;

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Gagal menyimpan payment.', ['status' => 500]);
        }

        $balance_updated = $this->update_jamaah_balance($data['jamaah_id']);

        if ($balance_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Gagal mengupdate saldo jemaah.', ['status' => 500]);
        }

        $wpdb->query('COMMIT');
        
        $this->clear_resource_cache(); // Hapus cache
        $new_payment = $this->get_item_by_id($new_id);
        return new WP_REST_Response($new_payment, 201);
    }

    /**
     * Custom UPDATE payment function to include transaction
     */
    public function update_item_transaksional($request) {
        global $wpdb;
        $id = (int) $request['id'];
        
        $old_payment = $wpdb->get_row($wpdb->prepare("SELECT jamaah_id FROM {$this->table_name} WHERE id = %d", $id));
        if (!$old_payment) {
            return new WP_Error('not_found', 'Payment not found.', ['status' => 404]);
        }
        $old_jamaah_id = $old_payment->jamaah_id;

        $data = $this->prepare_item_for_db($request, true);
        if (is_wp_error($data)) {
            return $data;
        }
        
        if (empty($data)) {
             return new WP_Error('no_data', 'No data provided to update', ['status' => 400]);
        }

        $wpdb->query('START TRANSACTION');
        
        $result = $wpdb->update($this->table_name, $data, ['id' => $id]);

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Gagal mengupdate payment.', ['status' => 500]);
        }

        $balance_updated = $this->update_jamaah_balance($old_jamaah_id);
        if ($balance_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Gagal mengupdate saldo jemaah lama.', ['status' => 500]);
        }

        $new_jamaah_id = isset($data['jamaah_id']) ? $data['jamaah_id'] : $old_jamaah_id;
        if ($new_jamaah_id != $old_jamaah_id) {
            $new_balance_updated = $this->update_jamaah_balance($new_jamaah_id);
            if ($new_balance_updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', 'Gagal mengupdate saldo jemaah baru.', ['status' => 500]);
            }
        }

        $wpdb->query('COMMIT');

        $this->clear_resource_cache(); // Hapus cache
        $updated_payment = $this->get_item_by_id($id);
        return new WP_REST_Response($updated_payment, 200);
    }

    /**
     * Custom DELETE payment function to include transaction
     */
    public function delete_item_transaksional($request) {
        global $wpdb;
        $id = (int) $request['id'];

        $payment = $wpdb->get_row($wpdb->prepare("SELECT jamaah_id FROM {$this->table_name} WHERE id = %d", $id));
        if (!$payment) {
            return new WP_Error('not_found', 'Payment not found.', ['status' => 404]);
        }
        $jamaah_id = $payment->jamaah_id;

        $wpdb->query('START TRANSACTION');

        $result = $wpdb->delete($this->table_name, ['id' => $id]);

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Gagal menghapus payment.', ['status' => 500]);
        }
        
        if ($result === 0) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('not_found', 'Payment not found.', ['status' => 404]);
        }

        $balance_updated = $this->update_jamaah_balance($jamaah_id);
        if ($balance_updated === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Gagal mengupdate saldo jemaah.', ['status' => 500]);
        }

        $wpdb->query('COMMIT');
        
        $this->clear_resource_cache(); // Hapus cache
        return new WP_REST_Response(true, 204); // No Content
    }


    /**
     * Recalculate and update jamaah balance
     * @return bool True on success, false on failure.
     */
    protected function update_jamaah_balance($jamaah_id) {
        global $wpdb;
        
        if (empty($jamaah_id)) {
            return false;
        }

        $jamaah_table = $wpdb->prefix . 'umh_jamaah';
        $packages_table = $wpdb->prefix . 'umh_packages';
        $prices_table = $wpdb->prefix . 'umh_package_prices';
        $payments_table = $this->table_name; // umh_payments

        // Hitung total tagihan (harga paket + harga kamar)
        // PERBAIKAN: Mengambil total_price dari tabel jamaah, asumsi sudah di-set saat jamaah dibuat/diedit
        $jamaah_data = $wpdb->get_row($wpdb->prepare("SELECT total_price FROM {$jamaah_table} WHERE id = %d", $jamaah_id));
        $total_price = (float) ($jamaah_data->total_price ?? 0);

        // Hitung total bayar (hanya yang 'confirmed')
        $total_paid = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) 
             FROM {$payments_table} 
             WHERE jamaah_id = %d AND status = 'confirmed'", 
            $jamaah_id
        ));
        
        $remaining_balance = $total_price - $total_paid;
        
        $payment_status = 'belum_lunas';
        if ($total_price <= 0) {
            $payment_status = 'pending'; 
        } elseif ($remaining_balance <= 0) {
            $payment_status = 'lunas';
        }

        $result = $wpdb->update(
            $jamaah_table,
            [
                // 'total_price' tidak diupdate di sini, hanya 'total_paid'
                'amount_paid' => $total_paid, // Ganti nama kolom 'total_paid' menjadi 'amount_paid'
                'payment_status' => $payment_status,
            ],
            ['id' => $jamaah_id],
            [
                '%f', // amount_paid
                '%s', // payment_status
            ],
            ['%d'] // where id
        );

        return ($result !== false);
    }
}

// Instansiasi controller untuk mendaftarkan hook
new UMH_Payments_API_Controller();