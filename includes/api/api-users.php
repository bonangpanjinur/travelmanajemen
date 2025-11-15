<?php
/**
 * File: includes/api/api-users.php
 *
 * Mengelola endpoint untuk CRUD pengguna (staff) dan otentikasi.
 *
 * [PERBAIKAN 15/11/2025]:
 * - Memperbaiki Fatal Error ArgumentCountError.
 * - Mengubah pemanggilan UMH_CRUD_Controller agar sesuai
 * dengan constructor baru (resource, table_slug, schema, permissions).
 * - Menghapus pemanggilan `$controller->register_routes()`
 * karena sudah ditangani di dalam constructor class.
 * - Menggunakan `add_filter` untuk hooks `before_create` / `before_update`.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_users_routes');

function umh_register_users_routes() {
    $namespace = 'umh/v1';
    $base = 'users';

    // === PERBAIKAN: Definisikan schema dan permissions di luar ===
    $users_schema = [
        'email'       => ['type' => 'string', 'required' => true, 'format' => 'email', 'sanitize_callback' => 'sanitize_email'],
        'full_name'   => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        'role'        => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
        'phone'       => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field'],
        'status'      => ['type' => 'string', 'default' => 'active', 'sanitize_callback' => 'sanitize_text_field'],
        'password'    => ['type' => 'string', 'required' => false], // Hanya untuk create/update, tidak disanitasi karena akan di-hash
    ];

    $users_permissions = [
        'get_items'    => ['owner', 'admin_staff', 'hr_staff'],
        'get_item'     => ['owner', 'admin_staff', 'hr_staff'],
        'create_item'  => ['owner', 'admin_staff'],
        'update_item'  => ['owner', 'admin_staff'],
        'delete_item'  => ['owner'],
    ];

    // Kolom yang dapat dicari
    $searchable_fields = ['full_name', 'email', 'phone'];

    // === PERBAIKAN: Gunakan add_filter untuk memodifikasi data ===
    // Nama hook-nya: "umh_crud_{$resource_name}_before_create"
    add_filter("umh_crud_{$base}_before_create", 'umh_hash_password_on_create', 10, 2);
    add_filter("umh_crud_{$base}_before_update", 'umh_hash_password_on_update', 10, 2);

    // === PERBAIKAN: Panggil constructor baru ===
    new UMH_CRUD_Controller(
        $base,               // 'users'
        'umh_users',         // $table_slug
        $users_schema,       // $schema
        $users_permissions,  // $permissions
        $searchable_fields   // $searchable_fields
    );
    
    // === DIHAPUS: $controller->register_routes($namespace, $base); ===
    // Pemanggilan register_routes() sudah otomatis di dalam constructor.

    // Rute Otentikasi (ini tetap)
    register_rest_route($namespace, '/auth/login', [
        'methods' => 'POST',
        'callback' => 'umh_auth_login',
        'permission_callback' => '__return_true', // Endpoint publik
    ]);

    register_rest_route($namespace, '/auth/wp-login', [
        'methods' => 'POST',
        'callback' => 'umh_auth_wp_admin_login',
        'permission_callback' => 'is_user_logged_in', // Hanya untuk WP Admin
    ]);

    // Rute /me (ini tetap)
    register_rest_route($namespace, '/' . $base . '/me', [
        'methods' => 'GET',
        'callback' => 'umh_get_current_user_by_token',
        'permission_callback' => umh_check_api_permission(), // Cek token valid
    ]);
}

/**
 * Hash password sebelum insert ke DB
 * [PERBAIKAN]: Fungsi ini adalah filter, harus me-return $data
 */
function umh_hash_password_on_create($data, $request) {
    if (isset($data['password']) && !empty($data['password'])) {
        $data['password_hash'] = wp_hash_password($data['password']);
    }
    unset($data['password']); // Hapus password plaintext
    return $data; // Kembalikan data yang sudah dimodifikasi
}

/**
 * Hash password jika diupdate
 * [PERBAIKAN]: Fungsi ini adalah filter, harus me-return $data
 */
function umh_hash_password_on_update($data, $request) {
    if (isset($data['password']) && !empty($data['password'])) {
        $data['password_hash'] = wp_hash_password($data['password']);
    }
    unset($data['password']); // Hapus password plaintext (kosong atau tidak)
    return $data; // Kembalikan data yang sudah dimodifikasi
}


/**
 * Callback untuk POST /auth/login (Headless Login)
 */
function umh_auth_login(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    
    $params = $request->get_json_params();
    $email = sanitize_email($params['email']);
    $password = $params['password'];

    if (empty($email) || empty($password)) {
        return new WP_Error('credentials_required', 'Email dan password dibutuhkan.', ['status' => 400]);
    }

    $user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE email = %s", $email));

    if (!$user) {
        return new WP_Error('invalid_email', 'Email tidak ditemukan.', ['status' => 403]);
    }
    
    // Pastikan kolom password_hash ada
    if (!isset($user->password_hash)) {
         return new WP_Error('user_misconfigured', 'Konfigurasi user salah (hash tidak ada).', ['status' => 500]);
    }

    if (!wp_check_password($password, $user->password_hash, $user->id)) {
        return new WP_Error('invalid_password', 'Password salah.', ['status' => 403]);
    }

    if ($user->status !== 'active') {
        return new WP_Error('user_inactive', 'Akun Anda tidak aktif.', ['status' => 403]);
    }

    // Buat token
    $token_data = umh_generate_auth_token($user->id, $user->role);

    return new WP_REST_Response([
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'role' => $user->role,
        ],
        'token' => $token_data['token'],
        'expires' => $token_data['expires'],
    ], 200);
}

/**
 * Callback untuk POST /auth/wp-login (Admin Login)
 * Ini dipanggil oleh React jika user adalah WP Admin
 */
function umh_auth_wp_admin_login(WP_REST_Request $request) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('not_admin', 'Hanya administrator yang bisa menggunakan endpoint ini.', ['status' => 403]);
    }

    // Panggil fungsi dari file utama (umroh-manager-hybrid.php)
    $user_data_for_react = umh_get_current_user_data_for_react(); 

    if (empty($user_data_for_react['token'])) {
         return new WP_Error('admin_sync_failed', 'Gagal sinkronisasi data admin.', ['status' => 500]);
    }

    return new WP_REST_Response([
        'user' => [
            'id' => $user_data_for_react['id'], // Ini akan jadi umh_user id
            'email' => $user_data_for_react['email'],
            'full_name' => $user_data_for_react['name'],
            'role' => $user_data_for_react['role'],
        ],
        'token' => $user_data_for_react['token'],
        'expires' => (new DateTime('+1 hour'))->format('Y-m-d H:i:s'), // Cocokkan dengan file utama
    ], 200);
}

/**
 * [PERBAIKAN] Callback untuk GET /me (verifikasi token)
 * Mengambil data user berdasarkan token yang valid.
 */
function umh_get_current_user_by_token(WP_REST_Request $request) {
    // Fungsi umh_check_api_permission sudah memvalidasi token.
    // Kita hanya perlu mengambil data user dari context yang disisipkan.
    $context = umh_get_current_user_context($request);

    if (is_wp_error($context)) {
        return $context; // Token invalid, expired, dll.
    }

    // === BLOK YANG DIPERBAIKI (DIHAPUS) ===
    // Blok 'super_admin' yang rusak sebelumnya dihapus.
    // Logika di bawah ini sudah benar untuk SEMUA user (headless ATAU super_admin),
    // karena $context['user_id'] berisi ID dari tabel umh_users
    // yang sudah divalidasi dari token.
    
    // Ambil data lengkap dari umh_users
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, email, full_name, role, phone, status FROM $table_name WHERE id = %d",
        $context['user_id']
    ), ARRAY_A);

    if (!$user) {
        return new WP_Error('user_not_found', 'Data user tidak ditemukan di tabel.', ['status' => 404]);
    }

    return new WP_REST_Response($user, 200);
}


/**
 * Helper: Membuat token JWT atau token sederhana
 */
function umh_generate_auth_token($user_id, $role) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';

    $token = bin2hex(random_bytes(32));
    $expires = new DateTime('+7 days');
    $expires_sql = $expires->format('Y-m-d H:i:s');

    $wpdb->update(
        $table_name,
        ['auth_token' => $token, 'token_expires' => $expires_sql],
        ['id' => $user_id]
    );

    return [
        'token' => $token,
        'expires' => $expires_sql,
    ];
}

/**
 * Helper: Verifikasi token
 * Mengembalikan [ 'user_id' => ID, 'role' => ROLE ] jika valid
 * Mengembalikan WP_Error jika tidak valid
 */
function umh_verify_auth_token($token) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';

    if (empty($token)) {
        return new WP_Error('token_missing', 'Token otentikasi tidak ada.', ['status' => 401]);
    }

    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, role, token_expires FROM $table_name WHERE auth_token = %s",
        $token
    ));

    if (!$user) {
        return new WP_Error('token_invalid', 'Token otentikasi tidak valid.', ['status' => 401]);
    }

    // Cek kadaluarsa
    $now = new DateTime();
    $expires = new DateTime($user->token_expires);

    if ($now > $expires) {
        return new WP_Error('token_expired', 'Token otentikasi telah kadaluarsa.', ['status' => 401]);
    }

    return [
        'user_id' => $user->id,
        'role' => $user->role,
    ];
}