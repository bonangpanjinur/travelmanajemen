<?php
// File: includes/api/api-finance.php
// Menggunakan CRUD Controller untuk mengelola Keuangan.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Definisikan Skema Data (cocokkan dengan db-schema.php)
$finance_schema = [
    'jamaah_id'        => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
    'user_id'          => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'], // Diisi oleh controller?
    'category_id'      => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
    'transaction_type' => ['type' => 'string', 'required' => true, 'enum' => ['income', 'expense']],
    'description'      => ['type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_textarea_field'],
    'amount'           => ['type' => 'number', 'required' => true],
    'transaction_date' => ['type' => 'string', 'format' => 'date', 'required' => true],
    'status'           => ['type' => 'string', 'required' => false, 'default' => 'completed', 'enum' => ['pending', 'completed']],
];

// 2. Definisikan Izin (Hanya Owner & Staf Keuangan)
$finance_permissions = [
    'get_items'    => ['owner', 'finance_staff'],
    'get_item'     => ['owner', 'finance_staff'],
    'create_item'  => ['owner', 'finance_staff'],
    'update_item'  => ['owner', 'finance_staff'],
    'delete_item'  => ['owner', 'finance_staff'],
];

// 3. Inisialisasi Controller
// Parameter: ('endpoint_base', 'slug_tabel_db', $skema, $izin)
new UMH_CRUD_Controller('finance', 'umh_finance', $finance_schema, $finance_permissions);