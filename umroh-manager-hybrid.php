<?php
/**
 * File: umroh-manager-hybrid.php
 *
 * File plugin utama, dimodifikasi untuk me-load file API baru
 * (api-roles.php dan api-payments.php)
 *
 * Plugin Name: Umroh Manager Hybrid
 * Plugin URI:  https://github.com/bonangpanjinur/travelmanajemen
 * Description: A hybrid WordPress plugin using React for managing Umroh travels.
 * Version:     0.1.0
 * Author:      Bonang Panji Nur
 * Author URI:  https://bonang.dev
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: umroh-manager-hybrid
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('UMH_VERSION', '0.1.0');
define('UMH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMH_PLUGIN_URL', plugin_dir_url(__FILE__));

// Aktivasi Plugin: Buat tabel database
register_activation_hook(__FILE__, 'umh_activate_plugin');
function umh_activate_plugin() {
    require_once(UMH_PLUGIN_DIR . 'includes/db-schema.php');
    umh_create_db_schema();
    
    // Tambahkan role dasar jika belum ada
    umh_add_default_roles_and_caps();
    
    // Tambahkan admin default ke tabel umh_users jika belum ada
    umh_add_default_admin_user();
}

// Tambahkan role WP kustom
function umh_add_default_roles_and_caps() {
    add_role('umh_agent', 'Travel Agent', [
        'read' => true,
        'umh_view_dashboard' => true,
        'umh_manage_own_jamaah' => true,
    ]);
    
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->add_cap('umh_manage_all');
        $admin_role->add_cap('umh_view_dashboard');
    }
}

// Tambahkan admin WP saat ini ke tabel umh_users
function umh_add_default_admin_user() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'umh_users';
    $current_user = wp_get_current_user();
    
    if ($current_user->ID != 0 && in_array('administrator', $current_user->roles)) {
        // Cek apakah user sudah ada di tabel
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE wp_user_id = %d",
            $current_user->ID
        ));
        
        if (!$existing) {
            $wpdb->insert($table_name, [
                'wp_user_id' => $current_user->ID,
                'full_name'  => $current_user->display_name,
                'role'       => 'owner', // 'owner' atau 'super_admin'
                'status'     => 'active'
            ]);
        }
    }
}


// Deaktivasi Plugin
register_deactivation_hook(__FILE__, 'umh_deactivate_plugin');
function umh_deactivate_plugin() {
    // Bisa tambahkan logic cleanup jika perlu
    // Misalnya, hapus role
    remove_role('umh_agent');
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('umh_manage_all');
        $admin_role->remove_cap('umh_view_dashboard');
    }
}

// Uninstall Plugin
register_uninstall_hook(__FILE__, 'umh_uninstall_plugin');
function umh_uninstall_plugin() {
    // Hapus tabel database
    global $wpdb;
    $tables = [
        'umh_categories', 'umh_packages', 'umh_jamaah', 'umh_finance',
        'umh_flights', 'umh_hotels', 'umh_flight_bookings', 'umh_hotel_bookings',
        'umh_tasks', 'umh_users', 'umh_agents', 'umh_logs', 'umh_documents',
        'umh_departures', 'umh_manifest', 'umh_settings', 'umh_notifications',
        'umh_roles', 'umh_payments' // Termasuk tabel baru
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
    }

    // Hapus options
    delete_option('umh_db_version');
    delete_option('umh_settings');

    // Hapus role
    remove_role('umh_agent');
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('umh_manage_all');
        $admin_role->remove_cap('umh_view_dashboard');
    }
}


// Inisialisasi Plugin
add_action('plugins_loaded', 'umh_init');
function umh_init() {
    // Load text domain untuk translasi
    load_plugin_textdomain('umroh-manager-hybrid', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // Load file-file penting
    require_once(UMH_PLUGIN_DIR . 'includes/utils.php');
    require_once(UMH_PLUGIN_DIR . 'includes/cors.php');
    require_once(UMH_PLUGIN_DIR . 'includes/class-umh-crud-controller.php');
    
    // Load Halaman Admin
    require_once(UMH_PLUGIN_DIR . 'admin/dashboard-react.php');
    require_once(UMH_PLUGIN_DIR . 'admin/settings-page.php');

    // Load API Endpoints
    umh_load_api_endpoints();
}

// Fungsi untuk me-load semua file API
function umh_load_api_endpoints() {
    $api_files = [
        'api-stats',
        'api-categories',
        'api-packages',
        'api-jamaah',
        'api-finance',
        'api-flights',
        'api-hotels',
        'api-tasks',
        'api-users',
        'api-departures',
        'api-marketing',
        'api-hr',
        'api-uploads',
        'api-print',
        'api-export',
        'api-logs',

        // ==================================================
        // == FILE BARU DITAMBAHKAN DARI RENCANA AKSI ==
        // ==================================================
        'api-roles',
        'api-payments',
        // ==================================================
    ];

    foreach ($api_files as $file) {
        $filepath = UMH_PLUGIN_DIR . "includes/api/{$file}.php";
        if (file_exists($filepath)) {
            require_once($filepath);
        }
    }

    // Hook untuk endpoint kustom
    do_action('umh_load_custom_api');
}

// Enqueue script dan style untuk admin
add_action('admin_enqueue_scripts', 'umh_enqueue_admin_assets');
function umh_enqueue_admin_assets($hook) {
    // Hanya load di halaman plugin kita
    if ($hook != 'toplevel_page_umroh-manager-hybrid') {
        return;
    }

    // Load React Build
    $asset_file = include(UMH_PLUGIN_DIR . 'build/index.asset.php');

    wp_enqueue_script(
        'umh-react-app',
        UMH_PLUGIN_URL . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true // Load di footer
    );

    // Load CSS
    wp_enqueue_style(
        'umh-admin-style',
        UMH_PLUGIN_URL . 'assets/css/admin-style.css',
        [],
        UMH_VERSION
    );

    // Loloskan data dari PHP ke JavaScript
    wp_localize_script('umh-react-app', 'umh_wp_data', [
        'api_url'  => esc_url_raw(rest_url('umh/v1/')),
        'nonce'    => wp_create_nonce('wp_rest'), // Nonce untuk keamanan
        'user'     => umh_get_current_user_data(), // Data user yang login
    ]);
}