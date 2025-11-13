<?php
/*
Plugin Name: Umroh Manager (Hybrid)
Description: Sistem manajemen travel umroh dengan UI React di dalam dashboard WordPress.
Version: 1.3
Author: (Nama Anda)
*/

if (!defined('ABSPATH')) exit;

define('UMROH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMROH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UMROH_API_NAMESPACE', 'umroh/v1');

// === AKTIVASI PLUGIN ===
// Daftarkan semua file logika API (Backend)
require_once(UMROH_PLUGIN_DIR . 'includes/db-schema.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api-loader.php');
require_once(UMROH_PLUGIN_DIR . 'includes/utils.php');

// 1. Buat Role Karyawan & Owner saat aktivasi
function umroh_create_roles() {
    add_role('karyawan', 'Karyawan', ['read' => true, 'level_1' => true]);
    add_role('owner', 'Owner', [
        'read' => true, 'level_1' => true, 'list_users' => true,
        'edit_users' => true, 'promote_users' => true
    ]);
    umroh_create_tables();
}
register_activation_hook(__FILE__, 'umroh_create_roles');

// === PENGATURAN HALAMAN ADMIN ===

// 2. Buat halaman "kanvas" untuk React
function umroh_add_admin_menu() {
    add_menu_page(
        'Manajemen Travel',
        'Umroh Panel',
        'read',
        'umroh-dashboard',
        'umroh_render_react_app',
        'dashicons-airplane',
        2
    );
}
add_action('admin_menu', 'umroh_add_admin_menu');

// 3. Render file "kanvas" (div id="root")
function umroh_render_react_app() {
    $kanvas_file = UMROH_PLUGIN_DIR . 'admin/dashboard-react.php';
    if (file_exists($kanvas_file)) {
        require_once($kanvas_file);
    } else {
        echo "<div class='notice notice-error'><p><strong>Error Kritis:</strong> File 'admin/dashboard-react.php' tidak ditemukan.</p></div>";
    }
}

// 4. "Bajak" (Redirect) Karyawan & Owner ke UI React
function umroh_redirect_non_admins() {
    if (is_admin() && !defined('DOING_AJAX')) {
        $user = wp_get_current_user();
        $is_owner_or_karyawan = in_array('owner', $user->roles) || in_array('karyawan', $user->roles);
        global $pagenow;
        $is_login_page = ($pagenow === 'wp-login.php');
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
        
        if ($is_owner_or_karyawan && $current_page !== 'umroh-dashboard' && !$is_login_page) {
            wp_redirect(admin_url('admin.php?page=umroh-dashboard'));
            exit;
        }
    }
}
add_action('admin_init', 'umroh_redirect_non_admins');


// === SUNTIK (ENQUEUE) REACT ===
// [PERBAIKAN UTAMA DISINI]

// 5. Load file build React (JS & CSS) ke "kanvas"
function umroh_load_react_scripts($hook) {
    // Hanya load di halaman "kanvas" kita ('toplevel_page_umroh-dashboard')
    if ($hook !== 'toplevel_page_umroh-dashboard') {
        return;
    }

    // Path ke file build (hasil dari 'npm run build')
    $build_path = UMROH_PLUGIN_DIR . 'build/';
    $build_url = UMROH_PLUGIN_URL . 'build/';
    
    // --- METODE BARU: Membaca index.asset.php ---
    $asset_file_path = $build_path . 'index.asset.php';
    if (!file_exists($asset_file_path)) {
        echo "<div class='notice notice-error'><p><strong>Error:</strong> File <code>build/index.asset.php</code> tidak ditemukan. Harap jalankan <code>npm run build</code> dan upload folder <code>build/</code> ke server.</p></div>";
        return;
    }
    
    // Muat file aset
    $asset_file = require($asset_file_path);
    $dependencies = $asset_file['dependencies'];
    $version = $asset_file['version'];
    
    // Enqueue file CSS utama
    // (Diasumsikan namanya index.css, sesuai standar build)
    wp_enqueue_style(
        'umroh-react-css',
        $build_url . 'index.css',
        [],
        $version
    );

    // Enqueue file JS utama
    $js_handle = 'umroh-react-js-main';
    wp_enqueue_script(
        $js_handle,
        $build_url . 'index.js',
        $dependencies, // Otomatis load 'react', 'react-dom'
        $version,
        true // load di footer
    );

    // "Lem" (Glue) antara PHP dan React
    $user = wp_get_current_user();
    $user_role = !empty($user->roles) ? $user->roles[0] : 'guest';
    
    wp_localize_script($js_handle, 'umrohData', [
        'apiUrl'   => esc_url_raw(rest_url(UMROH_API_NAMESPACE)),
        'apiNonce' => wp_create_nonce('wp_rest'), // Kunci keamanan
        'userRole' => $user_role,
        'userName' => $user->display_name
    ]);
}
add_action('admin_enqueue_scripts', 'umroh_load_react_scripts');
?>