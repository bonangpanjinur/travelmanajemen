<?php
// Lokasi: wp-content/plugins/umroh-manager-headless/includes/api/api-users.php

if (!defined('ABSPATH')) exit;

/**
 * GET /umroh/v1/users
 * Mengambil daftar user WordPress untuk dropdown pilihan karyawan.
 * Biasanya kita hanya ingin mengambil user dengan role tertentu (misal: Editor/Author).
 */
function umroh_get_users($request) {
    // Cek permission: Hanya staff/admin yang boleh lihat daftar teman kantor
    if (!umroh_check_permission_staff()) {
        return new WP_Error('forbidden', 'Akses ditolak', array('status' => 403));
    }

    // Ambil semua user (bisa difilter role=['editor', 'administrator'] jika mau)
    $args = array(
        'fields' => array('ID', 'display_name', 'user_email'),
        'orderby' => 'display_name'
    );
    
    $users = get_users($args);
    
    $data = array();
    foreach ($users as $user) {
        $data[] = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            // Kita bisa tambah avatar url jika mau
            'avatar' => get_avatar_url($user->ID)
        );
    }

    return new WP_REST_Response($data, 200);
}

/**
 * GET /umroh/v1/users/me
 * Cek siapa saya (untuk validasi login di frontend)
 */
function umroh_get_current_user($request) {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        return new WP_Error('no_auth', 'Tidak login', array('status' => 401));
    }
    
    $user = get_userdata($user_id);
    $roles = $user->roles;
    
    return new WP_REST_Response(array(
        'id' => $user->ID,
        'name' => $user->display_name,
        'roles' => $roles,
        'is_admin' => in_array('administrator', $roles)
    ), 200);
}