<?php
/**
 * File: includes/api/api-categories.php
 *
 * PERBAIKAN:
 * - Menghapus semua fungsi CRUD duplikat (umh_get_items, dll).
 * - Mengimplementasikan UMH_CRUD_Controller yang standar
 * sama seperti api-flights, api-hotels, dll.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Definisikan Skema Data (sesuai db-schema.php)
$categories_schema = [
    'name'        => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'description' => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
    // 'type' sepertinya tidak ada di db-schema.php, jadi saya hapus.
    // Jika ada, tambahkan kembali ke schema.
];

// 2. Definisikan Izin
$categories_permissions = [
    'get_items'    => ['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff'],
    'get_item'     => ['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff'],
    'create_item'  => ['owner', 'admin_staff'],
    'update_item'  => ['owner', 'admin_staff'],
    'delete_item'  => ['owner', 'admin_staff'],
];

// 3. Tentukan Kolom yang Bisa Dicari
$categories_searchable_fields = ['name', 'description'];

// 4. Inisialisasi Controller
new UMH_CRUD_Controller(
    'categories', 
    'umh_categories', 
    $categories_schema, 
    $categories_permissions,
    $categories_searchable_fields
);