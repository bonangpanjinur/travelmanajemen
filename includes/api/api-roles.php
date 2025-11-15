<?php
/**
 * File: includes/api/api-roles.php
 *
 * File BARU untuk mengelola role karyawan dinamis.
 * Menggunakan UMH_CRUD_Controller untuk CRUD pada tabel umh_roles.
 *
 * [CATATAN]: File ini sudah benar. Perbaikan pada class-umh-crud-controller.php
 * akan membuat baris 'new UMH_CRUD_Controller' di bawah ini berfungsi.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Definisikan Skema Data
$schema = [
    'role_key'  => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'role_name' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
];

// 2. Definisikan Izin
$permissions = [
    'get_items'   => ['owner', 'admin_staff', 'hr_staff'], // Bisa dilihat banyak orang
    'get_item'    => ['owner', 'admin_staff', 'hr_staff'],
    'create_item' => ['owner', 'hr_staff'], // Hanya owner dan HR
    'update_item' => ['owner', 'hr_staff'],
    'delete_item' => ['owner'], // Hanya owner
];

// 3. Inisialisasi Controller
// Ini akan membuat endpoint: /wp-json/umh/v1/roles
// Constructor di UMH_CRUD_Controller akan otomatis mendaftarkan rute.
new UMH_CRUD_Controller('roles', 'umh_roles', $schema, $permissions);