<?php
// File: includes/db-schema.php
// File ini berisi fungsi aktivasi yang membuat/memperbarui
// semua tabel database yang diperlukan oleh plugin.

// Exit jika diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fungsi aktivasi plugin.
 * Dipanggil saat plugin diaktifkan.
 */
function umroh_manager_create_tables() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // === PERBAIKAN: Menambahkan Tabel User Kustom (Karyawan/Owner) ===
    // Tabel ini KRUSIAL untuk sistem login kustom Anda.
    $table_users = $wpdb->prefix . 'umh_users';
    $sql_users = "CREATE TABLE $table_users (
        user_id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(60) NOT NULL,
        password_hash varchar(255) NOT NULL,
        user_email varchar(100) NOT NULL,
        full_name varchar(250),
        role varchar(20) NOT NULL, -- 'owner' atau 'karyawan'
        auth_token varchar(100),
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (user_id),
        UNIQUE KEY username (username),
        UNIQUE KEY user_email (user_email),
        KEY auth_token (auth_token)
    ) $charset_collate;";
    dbDelta($sql_users);

    // === PERBAIKAN: Menstandardisasi prefix tabel menjadi 'umh_' ===
    // (Sebelumnya: 'travel_')
    // Ini agar konsisten dengan 'umh_users'

    // 1. Tabel Paket
    $table_packages = $wpdb->prefix . 'umh_packages';
    $sql_packages = "CREATE TABLE $table_packages (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        package_name varchar(255) NOT NULL,
        description text,
        price decimal(15,2) DEFAULT 0.00 NOT NULL,
        departure_date date,
        duration int(3) DEFAULT 9,
        destination varchar(255),
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_packages);

    // 2. Tabel Jamaah
    $table_jamaah = $wpdb->prefix . 'umh_jamaah';
    $sql_jamaah = "CREATE TABLE $table_jamaah (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        package_id mediumint(9),
        name varchar(255) NOT NULL,
        email varchar(255),
        phone varchar(20),
        address text,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY package_id (package_id)
    ) $charset_collate;";
    dbDelta($sql_jamaah);

    // 3. Tabel Keuangan (Finance)
    $table_finance = $wpdb->prefix . 'umh_finance';
    $sql_finance = "CREATE TABLE $table_finance (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        jamaah_id mediumint(9),
        description varchar(255) NOT NULL,
        amount decimal(15,2) DEFAULT 0.00 NOT NULL,
        type varchar(10) NOT NULL, -- 'income' or 'expense'
        transaction_date date,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY jamaah_id (jamaah_id)
    ) $charset_collate;";
    dbDelta($sql_finance);

    // 4. Tabel Hotel
    $table_hotels = $wpdb->prefix . 'umh_hotels';
    $sql_hotels = "CREATE TABLE $table_hotels (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        hotel_name varchar(255) NOT NULL,
        address text,
        stars int(1) DEFAULT 5,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_hotels);

    // 5. Tabel Penerbangan
    $table_flights = $wpdb->prefix . 'umh_flights';
    $sql_flights = "CREATE TABLE $table_flights (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        airline_name varchar(100) NOT NULL,
        flight_number varchar(20) NOT NULL,
        departure_airport varchar(100),
        arrival_airport varchar(100),
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_flights);

    // 6. Tabel Tugas
    $table_tasks = $wpdb->prefix . 'umh_tasks';
    $sql_tasks = "CREATE TABLE $table_tasks (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        task_name varchar(255) NOT NULL,
        description text,
        status varchar(20) DEFAULT 'pending', -- 'pending', 'in_progress', 'completed'
        assigned_to_user_id bigint(20) UNSIGNED,
        created_by_user_id bigint(20) UNSIGNED,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY assigned_to_user_id (assigned_to_user_id)
    ) $charset_collate;";
    dbDelta($sql_tasks);

    // 7. Tabel HR
    $table_hr = $wpdb->prefix . 'umh_hr';
    $sql_hr = "CREATE TABLE $table_hr (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        position varchar(100),
        email varchar(255),
        phone varchar(20),
        status varchar(20) DEFAULT 'active', -- 'active', 'inactive'
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_hr);

    // 8. Tabel Departures
    $table_departures = $wpdb->prefix . 'umh_departures';
    $sql_departures = "CREATE TABLE $table_departures (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        departure_name varchar(255) NOT NULL,
        package_id mediumint(9),
        flight_id mediumint(9),
        departure_date datetime,
        status varchar(20) DEFAULT 'scheduled', -- 'scheduled', 'departed', 'arrived', 'cancelled'
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY package_id (package_id),
        KEY flight_id (flight_id)
    ) $charset_collate;";
    dbDelta($sql_departures);

    // 9. Tabel Marketing
    $table_marketing = $wpdb->prefix . 'umh_marketing';
    $sql_marketing = "CREATE TABLE $table_marketing (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        campaign_name varchar(255) NOT NULL,
        type varchar(50), -- 'social_media', 'email', 'ads'
        status varchar(20) DEFAULT 'draft', -- 'draft', 'running', 'completed'
        start_date date,
        end_date date,
        budget decimal(15,2) DEFAULT 0.00,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_marketing);

    // === PERBAIKAN: Menambahkan tabel-tabel yang hilang dari API Anda ===
    // Tabel-tabel ini dirujuk oleh file API Anda (api-stats, api-manifest, dll)
    // tapi tidak ada di skema awal.

    $table_categories = $wpdb->prefix . 'umh_categories';
    $sql_categories = "CREATE TABLE $table_categories (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        parent_id mediumint(9) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_categories);

    $table_logs = $wpdb->prefix . 'umh_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED,
        action varchar(100) NOT NULL,
        object_type varchar(50),
        object_id mediumint(9),
        details text,
        timestamp timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY action (action)
    ) $charset_collate;";
    dbDelta($sql_logs);

    $table_manifest = $wpdb->prefix . 'umh_manifest';
    $sql_manifest = "CREATE TABLE $table_manifest (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        full_name varchar(255) NOT NULL,
        passport_no varchar(100),
        package_id mediumint(9),
        final_price decimal(15,2),
        payment_status varchar(50),
        visa_status varchar(50),
        equipment_taken tinyint(1) DEFAULT 0,
        status varchar(50),
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_manifest);

    $table_payments = $wpdb->prefix . 'umh_payments';
    $sql_payments = "CREATE TABLE $table_payments (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        manifest_id mediumint(9) NOT NULL,
        amount decimal(15,2) NOT NULL,
        payment_date date,
        method varchar(50),
        notes text,
        recorded_by bigint(20) UNSIGNED,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY manifest_id (manifest_id)
    ) $charset_collate;";
    dbDelta($sql_payments);

    $table_leads = $wpdb->prefix . 'umh_leads';
    $sql_leads = "CREATE TABLE $table_leads (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        full_name varchar(255) NOT NULL,
        phone varchar(20),
        status varchar(20) DEFAULT 'Cold', -- 'Hot', 'Warm', 'Cold'
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_leads);

    // Tabel 'uhp_packages' ini dirujuk oleh 'api-print.php' dan 'api-export.php'.
    // Ini adalah CONTOH inkonsistensi. Seharusnya file-file tsb merujuk ke 'umh_packages'.
    // Saya buatkan tabel ini agar tidak error, tapi ini harus Anda perbaiki.
    $table_uhp_packages = $wpdb->prefix . 'uhp_packages';
    $sql_uhp_packages = "CREATE TABLE $table_uhp_packages (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255),
        price_details text,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_uhp_packages);
}