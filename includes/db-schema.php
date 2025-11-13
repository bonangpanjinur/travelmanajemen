<?php
// Lokasi: wp-content/plugins/umroh-manager/includes/db-schema.php

if (!defined('ABSPATH')) exit;

/**
 * Fungsi ini berjalan 1x saat plugin diaktifkan.
 * Membuat semua 9 tabel kustom yang dibutuhkan oleh sistem.
 * (Versi ini sudah menghapus semua komentar di dalam SQL untuk dbDelta)
 */
function umroh_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // 1. Tabel Tugas / Jobdesc
    $table_tasks = $wpdb->prefix . 'umroh_tasks';
    $sql_tasks = "CREATE TABLE $table_tasks (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text,
        assigned_to bigint(20) UNSIGNED NOT NULL,
        assigned_by bigint(20) UNSIGNED NOT NULL,
        deadline datetime,
        status varchar(50) DEFAULT 'Pending',
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_tasks);

    // 2. Tabel Leads / Calon Jemaah
    $table_leads = $wpdb->prefix . 'umroh_leads';
    $sql_leads = "CREATE TABLE $table_leads (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        phone varchar(50),
        source varchar(100),
        status varchar(50) DEFAULT 'Cold',
        notes text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_leads);

    // 3. Tabel Manifest Jemaah (Data Inti)
    $table_manifest = $wpdb->prefix . 'umroh_manifest';
    $sql_manifest = "CREATE TABLE $table_manifest (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        package_id bigint(20) UNSIGNED,
        full_name varchar(255) NOT NULL,
        passport_no varchar(50),
        passport_expiry date,
        final_price decimal(15,2) DEFAULT 0.00,
        payment_status varchar(50) DEFAULT 'Belum Bayar',
        visa_status varchar(50) DEFAULT 'Belum Submit',
        equipment_taken tinyint(1) DEFAULT 0,
        status varchar(50) DEFAULT 'Active',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY package_id (package_id)
    ) $charset_collate;";
    dbDelta($sql_manifest);

    // 4. Tabel Marketing (Iklan & Sosmed)
    $table_marketing = $wpdb->prefix . 'umroh_marketing';
    $sql_marketing = "CREATE TABLE $table_marketing (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        type varchar(50) NOT NULL,
        platform varchar(100),
        topic_or_campaign varchar(255),
        status varchar(50),
        metrics text,
        date datetime,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_marketing);

    // 5. Tabel Keuangan Kantor (Buku Besar)
    $table_finance = $wpdb->prefix . 'umroh_finance';
    $sql_finance = "CREATE TABLE $table_finance (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        type enum('Pemasukan','Gaji','Kasbon','Operasional','Refund') NOT NULL,
        amount decimal(15,2) NOT NULL,
        description varchar(255),
        user_id bigint(20) UNSIGNED,
        manifest_id mediumint(9) DEFAULT NULL,
        date date,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY manifest_id (manifest_id)
    ) $charset_collate;";
    dbDelta($sql_finance);

    // 6. Tabel Absensi Karyawan
    $table_attendance = $wpdb->prefix . 'umroh_attendance';
    $sql_attendance = "CREATE TABLE $table_attendance (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        date date NOT NULL,
        status varchar(50) NOT NULL,
        check_in_time time,
        notes text,
        PRIMARY KEY  (id),
        UNIQUE KEY user_date (user_id, date)
    ) $charset_collate;";
    dbDelta($sql_attendance);

    // 7. Tabel Cuti Karyawan
    $table_leave = $wpdb->prefix . 'umroh_leave';
    $sql_leave = "CREATE TABLE $table_leave (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        type varchar(100) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        reason text,
        status varchar(50) DEFAULT 'Pending',
        approved_by bigint(20) UNSIGNED,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql_leave);

    // 8. Tabel Audit Log (CCTV Digital)
    $table_logs = $wpdb->prefix . 'umroh_audit_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED,
        action varchar(100) NOT NULL,
        item_id mediumint(9) DEFAULT 0,
        details text,
        ip_address varchar(100),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY action (action)
    ) $charset_collate;";
    dbDelta($sql_logs);

    // 9. Tabel Riwayat Pembayaran Jemaah (Cicilan)
    $table_payments = $wpdb->prefix . 'umroh_payments';
    $sql_payments = "CREATE TABLE $table_payments (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        manifest_id mediumint(9) NOT NULL,
        amount decimal(15,2) NOT NULL,
        payment_date date NOT NULL,
        method varchar(100),
        proof_url varchar(255),
        notes text,
        recorded_by bigint(20) UNSIGNED,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY manifest_id (manifest_id)
    ) $charset_collate;";
    dbDelta($sql_payments);
}
?>