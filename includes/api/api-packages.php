<?php
// File: includes/api/api-packages.php
// Menggunakan CRUD Controller untuk mengelola Paket Umroh.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Definisikan Skema Data (cocokkan dengan db-schema.php)
$packages_schema = [
    'package_name'    => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'description'     => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
    'price'           => ['type' => 'number', 'required' => true],
    'departure_date'  => ['type' => 'string', 'format' => 'date', 'required' => true],
    'duration'        => ['type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint'],
    'status'          => ['type' => 'string', 'required' => false, 'default' => 'draft', 'enum' => ['draft', 'published', 'archived']],
    'slots_available' => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
    'slots_filled'    => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
];

// 2. Definisikan Izin (Hanya Owner & Admin Staff yang bisa kelola paket)
$packages_permissions = [
    'get_items'    => ['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff'], // Semua bisa lihat
    'get_item'     => ['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff'], // Semua bisa lihat
    'create_item'  => ['owner', 'admin_staff'],
    'update_item'  => ['owner', 'admin_staff'],
    'delete_item'  => ['owner'],
];

// 3. Inisialisasi Controller
// Parameter: ('endpoint_base', 'slug_tabel_db', $skema, $izin)
new UMH_CRUD_Controller('packages', 'umh_packages', $packages_schema, $packages_permissions);