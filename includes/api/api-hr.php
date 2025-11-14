<?php
// File: includes/api/api-hr.php
// KERANGKA (TEMPLATE) AMAN UNTUK API MANAJEMEN KARYAWAN (HR)

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Catatan: Karyawan disimpan di tabel 'umh_users' dengan role 'karyawan' atau 'owner'.
// API ini akan mengelola data dari tabel 'umh_users'.

// Daftarkan Rute API
add_action('rest_api_init', function () {
    $namespace = 'umh/v1';
    
    // Rute: /umh/v1/employees (GET) - Mendapatkan semua karyawan
    register_rest_route($namespace, '/employees', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_employees',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/employees (POST) - Membuat karyawan baru
    // Catatan: Ini sama dengan 'register' di api-users.php, tapi mungkin
    // Super Admin bisa membuat akun untuk karyawan.
    register_rest_route($namespace, '/employees', array(
        'methods'             => 'POST',
        'callback'            => 'umh_create_employee',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
    
    // Rute: /umh/v1/employees/<id> (GET) - Mendapatkan satu karyawan
    register_rest_route($namespace, '/employees/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'umh_get_employee',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));

    // Rute: /umh/v1/employees/<id> (PUT) - Update satu karyawan
    register_rest_route($namespace, '/employees/(?P<id>\d+)', array(
        'methods'             => 'PUT, POST',
        'callback'            => 'umh_update_employee',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
    
    // Rute: /umh/v1/employees/<id> (DELETE) - Hapus satu karyawan
    register_rest_route($namespace, '/employees/(?P<id>\d+)', array(
        'methods'             => 'DELETE',
        'callback'            => 'umh_delete_employee',
        'permission_callback' => 'umh_check_api_permission', // <-- PENGAMAN
    ));
});

/**
 * Callback untuk GET /employees
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_employees(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $results = $wpdb->get_results("SELECT id, username, email, full_name, role, status FROM $table_name WHERE role IN ('owner', 'karyawan')");
    // return new WP_REST_Response($results, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_employees belum diimplementasi.'], 501);
}

/**
 * Callback untuk POST /employees
 * (Mirip dengan register, tapi mungkin hanya untuk admin)
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_create_employee(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika insert Anda di sini.
    // Pastikan untuk hash password!
    // Contoh:
    // $data = [
    //     'username' => $params['username'],
    //     'email' => $params['email'],
    //     'password_hash' => wp_hash_password($params['password']),
    //     'full_name' => $params['full_name'],
    //     'role' => $params['role'] ?? 'karyawan', // (owner/karyawan)
    //     'status' => 'active',
    // ];
    // $format = ['%s', '%s', '%s', '%s', '%s', '%s'];
    // $result = $wpdb->insert($table_name, $data, $format);
    //
    // if ($result) {
    //     $new_id = $wpdb->insert_id;
    //     $new_user = $wpdb->get_row("SELECT id, username, email, full_name, role, status FROM $table_name WHERE id = $new_id");
    //     return new WP_REST_Response($new_user, 201);
    // } else {
    //     return new WP_Error('create_failed', 'Gagal membuat karyawan baru.', ['status' => 500]);
    // }

    return new WP_REST_Response(['message' => 'Fungsi umh_create_employee belum diimplementasi.'], 501);
}

/**
 * Callback untuk GET /employees/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_get_employee(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    $id = $request['id'];

    // TODO: Tulis logika query Anda di sini.
    // Contoh: $employee = $wpdb->get_row($wpdb->prepare("SELECT id, username, email, full_name, role, status FROM $table_name WHERE id = %d", $id));
    // if (!$employee) {
    //     return new WP_Error('not_found', 'Karyawan tidak ditemukan.', ['status' => 404]);
    // }
    // return new WP_REST_Response($employee, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_get_employee belum diimplementasi.'], 501);
}

/**
 * Callback untuk PUT /employees/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_update_employee(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    $id = $request['id'];
    
    $params = $request->get_json_params();
    if (empty($params)) $params = $request->get_body_params();

    // TODO: Tulis logika update Anda di sini.
    // Contoh:
    // $data = [
    //     'full_name' => $params['full_name'],
    //     'email' => $params['email'],
    //     'role' => $params['role'],
    //     'status' => $params['status'],
    // ];
    // $where = ['id' => $id];
    // $format = ['%s', '%s', '%s', '%s'];
    // $where_format = ['%d'];
    // $updated = $wpdb->update($table_name, $data, $where, $format, $where_format);
    //
    // if ($updated === false) {
    //     return new WP_Error('update_failed', 'Gagal memperbarui karyawan.', ['status' => 500]);
    // }
    // $updated_employee = $wpdb->get_row($wpdb->prepare("SELECT id, username, email, full_name, role, status FROM $table_name WHERE id = %d", $id));
    // return new WP_REST_Response($updated_employee, 200);

    return new WP_REST_Response(['message' => 'Fungsi umh_update_employee belum diimplementasi.'], 501);
}

/**
 * Callback untuk DELETE /employees/<id>
 * TODO: ISI LOGIKA ANDA DI SINI
 */
function umh_delete_employee(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    $id = $request['id'];

    // TODO: Tulis logika delete Anda di sini.
    // HATI-HATI: Jangan hapus Super Admin (role 'admin')
    // Contoh:
    // $employee = $wpdb->get_row($wpdb->prepare("SELECT role FROM $table_name WHERE id = %d", $id));
    // if ($employee && $employee->role !== 'admin') {
    //     $deleted = $wpdb->delete($table_name, ['id' => $id], ['%d']);
    //     if ($deleted) {
    //         return new WP_REST_Response(['message' => 'Karyawan berhasil dihapus.'], 200);
    //     }
    // }
    // return new WP_Error('delete_failed', 'Gagal menghapus karyawan.', ['status' => 500]);

    return new WP_REST_Response(['message' => 'Fungsi umh_delete_employee belum diimplementasi.'], 501);
}