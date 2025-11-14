<?php
/**
 * File utilitas untuk plugin Umroh Manager.
 *
 * PERBAIKAN BESAR (Sistem Izin / RBAC):
 * - Menambahkan `umh_get_current_user_context` untuk mendapatkan user,
 * baik dari cookie WordPress (Super Admin) maupun dari Bearer Token (Owner, Karyawan, dll).
 * - Menulis ulang `umh_check_api_permission` menjadi "factory function" yang:
 * 1. Selalu mengizinkan 'super_admin' (Super Admin WordPress).
 * 2. Memeriksa role pengguna (dari token) terhadap daftar $allowed_roles.
 *
 * Ini akan memperbaiki masalah di mana Super Admin tidak bisa mengakses
 * endpoint API yang dibatasi oleh role.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Memeriksa apakah pengguna WordPress saat ini adalah Super Admin (Administrator).
 *
 * @return bool True jika super admin, false jika bukan.
 */
function umh_is_super_admin() {
    // wp_get_current_user() mungkin tidak berfungsi di semua hook REST API,
    // jadi current_user_can() adalah cara yang lebih andal.
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }
    return false;
}

/**
 * Mendapatkan konteks pengguna yang sedang login, baik via Cookie WP atau Bearer Token.
 *
 * @param WP_REST_Request $request Objek request.
 * @return array|WP_Error Array berisi ['role', 'user_id'] atau WP_Error jika gagal.
 */
function umh_get_current_user_context(WP_REST_Request $request) {
    // 1. Cek Super Admin via Cookie WordPress
    // Fungsi umh_handle_wp_admin_login di api-users.php akan membuat
    // user 'super_admin' di tabel umh_users saat admin login pertama kali.
    // wp_get_current_user() akan valid jika admin me-refresh halaman dashboard.
    $wp_user_id = get_current_user_id();
    if ( $wp_user_id !== 0 && current_user_can('manage_options') ) {
        return [
            'role'    => 'super_admin',
            'user_id' => $wp_user_id,
        ];
    }

    // 2. Cek Pengguna via Bearer Token (Untuk Owner, Karyawan, dll)
    $auth_header = $request->get_header('authorization');
    if (empty($auth_header)) {
        return new WP_Error('rest_unauthorized', 'Authorization header not found.', ['status' => 401]);
    }

    // Format header: "Bearer <token>"
    if (sscanf($auth_header, 'Bearer %s', $token) !== 1) {
        return new WP_Error('rest_unauthorized', 'Invalid authorization header format.', ['status' => 401]);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    
    // Cari user berdasarkan token yang valid dan belum expired
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT id, role, email FROM $table_name WHERE auth_token = %s AND token_expires > %s",
        $token,
        current_time('mysql')
    ));

    if (!$user) {
        // Cek juga user super_admin yang mungkin token-nya dibuat via /auth/wp-login
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, role, email, wp_user_id FROM $table_name WHERE auth_token = %s AND token_expires > %s AND role = 'super_admin'",
            $token,
            current_time('mysql')
        ));
        
        if (!$user) {
             return new WP_Error('rest_invalid_token', 'Invalid or expired token.', ['status' => 403]);
        }
        
        // Jika ini token super_admin, pastikan user WP-nya masih ada
        if ( !empty($user->wp_user_id) && !get_user_by('id', $user->wp_user_id) ) {
            return new WP_Error('rest_invalid_token', 'Super admin WordPress account not found.', ['status' => 403]);
        }
    }

    // Jika kita sampai di sini, token valid.
    return [
        'role'    => $user->role,
        'user_id' => $user->id,
    ];
}


/**
 * [PERBAIKAN TOTAL] Factory Function untuk Permission Callback.
 *
 * Fungsi ini MENGEMBALIKAN sebuah fungsi (callback) yang akan digunakan
 * oleh WordPress REST API.
 *
 * @param array $allowed_roles Daftar role yang diizinkan (e.g., ['owner', 'admin_staff']).
 * @return callable Fungsi callback untuk 'permission_callback'.
 */
function umh_check_api_permission( $allowed_roles = [] ) {
    
    /**
     * Ini adalah fungsi callback yang sebenarnya akan dijalankan oleh WP REST API.
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    return function(WP_REST_Request $request) use ($allowed_roles) {
        
        // 1. Dapatkan konteks pengguna (bisa dari cookie atau token)
        $context = umh_get_current_user_context($request);

        // Jika token tidak valid atau ada error, kembalikan error
        if (is_wp_error($context)) {
            return $context;
        }

        $user_role = $context['role'];

        // 2. [KUNCI] Super Admin SELALU diizinkan melakukan apa pun.
        if ($user_role === 'super_admin') {
            return true;
        }

        // 3. Jika bukan Super Admin, periksa apakah role-nya ada di daftar $allowed_roles
        if (is_array($allowed_roles) && in_array($user_role, $allowed_roles, true)) {
            return true;
        }
        
        // 4. Jika tidak ada di daftar, tolak akses.
        return new WP_Error(
            'rest_forbidden',
            'Sorry, you are not allowed to do that.',
            ['status' => 403]
        );
    };
}