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
 *
 * PERBAIKAN: Nama fungsi diubah dari 'umroh_manager_activate' 
 * menjadi 'umroh_manager_create_tables' agar sesuai dengan
 * panggilan di file 'umroh-manager-hybrid.php'.
 */
function umroh_manager_create_tables() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Tabel Paket
    $table_packages = $wpdb->prefix . 'travel_packages';
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
    $table_jamaah = $wpdb->prefix . 'travel_jamaah';
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
    $table_finance = $wpdb->prefix . 'travel_finance';
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
    $table_hotels = $wpdb->prefix . 'travel_hotels';
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
    $table_flights = $wpdb->prefix . 'travel_flights';
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
    $table_tasks = $wpdb->prefix . 'travel_tasks';
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

    // 7. Tabel HR (BARU)
    $table_hr = $wpdb->prefix . 'travel_hr';
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

    // 8. Tabel Departures (BARU)
    $table_departures = $wpdb->prefix . 'travel_departures';
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

    // 9. Tabel Marketing (BARU)
    $table_marketing = $wpdb->prefix . 'travel_marketing';
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

}