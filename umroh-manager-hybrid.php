<?php
/**
 * Plugin Name: Umroh Manager Hybrid
 * Plugin URI:  https://example.com/
 * Description: Manages Umroh packages, jamaah, finance, and HR with a hybrid WP-Admin and Headless API approach.
 * Version:     1.2.3
 * Author:      Your Name
 * Author URI:  https://example.com/
 * Text Domain: umh
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('UMH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMH_PLUGIN_URL', plugin_dir_url(__FILE__));

// 1. Inisialisasi Database
require_once UMH_PLUGIN_DIR . 'includes/db-schema.php';

// 2. Utilitas Inti & Keamanan
// PENTING: Pastikan file ini adalah versi 'Peningkatan Keamanan (RBAC)'
// yang saya berikan sebelumnya (dengan function factory umh_check_api_permission)
require_once UMH_PLUGIN_DIR . 'includes/utils.php';

// 3. Penanganan CORS (jika diperlukan untuk headless)
require_once UMH_PLUGIN_DIR . 'includes/cors.php'; 

// 4. Load Generic CRUD Controller
require_once UMH_PLUGIN_DIR . 'includes/class-umh-crud-controller.php';

// 5. Muat semua file API Endpoints
$api_files = glob(UMH_PLUGIN_DIR . 'includes/api/*.php');
foreach ($api_files as $file) {
    if (basename($file) !== 'api-manifest.php') { // Jangan muat file yang sudah usang
        require_once $file;
    }
}

// 6. Halaman Admin (Dashboard & Pengaturan)
require_once UMH_PLUGIN_DIR . 'admin/dashboard-react.php';
require_once UMH_PLUGIN_DIR . 'admin/settings-page.php';

// --- HOOKS ---

// 7. Menambahkan Halaman Menu Admin
function umh_admin_menu() {
    add_menu_page(
        'Umroh Manager', // Judul Halaman
        'Umroh Manager', // Judul Menu
        'manage_options', // Kapabilitas
        'umroh-manager-dashboard', // Slug Menu
        'umroh_manager_render_dashboard_react', // Fungsi callback
        'dashicons-airplane', // Ikon
        6 // Posisi
    );

    add_submenu_page(
        'umroh-manager-dashboard', // Slug Induk
        'Pengaturan API', // Judul Halaman Submenu
        'Pengaturan API', // Judul Menu Submenu
        'manage_options', // Kapabilitas
        'umh-settings', // Slug Submenu
        'umh_render_settings_page' // Fungsi callback
    );
}
add_action('admin_menu', 'umh_admin_menu');

// 8. Inisialisasi Halaman Pengaturan
function umh_settings_init() {
    // Pastikan class-nya sudah di-load
    if (class_exists('UMH_Settings_Page')) {
        $umh_settings_page = new UMH_Settings_Page();
        $umh_settings_page->register_settings();
    }
}
add_action('admin_init', 'umh_settings_init'); // Hook yang benar untuk mendaftarkan pengaturan


// 9. Enqueue scripts untuk Admin Dashboard React
function umh_admin_enqueue_scripts($hook) {
    
    // Hook harus sesuai dengan slug menu yang dibuat di umh_admin_menu
    // 'toplevel_page_' + 'umroh-manager-dashboard'
    // 'umroh-manager' (nama menu) + '_page_' + 'umh-settings'
    if ('toplevel_page_umroh-manager-dashboard' !== $hook && 'umroh-manager_page_umh-settings' !== $hook) {
        return;
    }

    // Hanya muat di halaman dashboard kita
    if ($hook === 'toplevel_page_umroh-manager-dashboard') {
        $asset_file = include(UMH_PLUGIN_DIR . 'build/index.asset.php');

        wp_enqueue_script(
            'umh-admin-react-app',
            UMH_PLUGIN_URL . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        wp_enqueue_style(
            'umh-admin-style',
            UMH_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            filemtime(UMH_PLUGIN_DIR . 'assets/css/admin-style.css')
        );

        // Amankan API untuk WP Admin (Super Admin)
        $current_user = wp_get_current_user();
        if (umh_is_super_admin($current_user)) {
            // [PERBAIKAN KUNCI] Nama variabel di sini harus 'umh_wp_data'
            // agar cocok dengan yang dicari oleh index.jsx.
            wp_localize_script('umh-admin-react-app', 'umh_wp_data', [
                'api_url' => esc_url_raw(rest_url('umh/v1/')),
                'api_nonce' => wp_create_nonce('wp_rest'),
                'is_wp_admin' => true,
                'current_user' => [
                    'wp_user_id' => $current_user->ID, // Kirim ID WP
                    'display_name' => $current_user->display_name,
                    'email' => $current_user->user_email,
                    'role' => 'super_admin', // Beri role eksplisit
                ],
            ]);
        }
    }
    
    // Muat style untuk halaman pengaturan
    if ($hook === 'umroh-manager_page_umh-settings') {
         wp_enqueue_style(
            'umh-admin-style',
            UMH_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            filemtime(UMH_PLUGIN_DIR . 'assets/css/admin-style.css')
        );
    }
}
add_action('admin_enqueue_scripts', 'umh_admin_enqueue_scripts');


// 10. Menyajikan Service Worker untuk PWA
function umh_serve_service_worker() {
    // Pastikan request ada di root
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/service-worker.js') {
        $sw_file = UMH_PLUGIN_DIR . 'pwa/service-worker.js';
        if (file_exists($sw_file)) {
            header('Content-Type: application/javascript');
            header('Service-Worker-Allowed: /'); // Izinkan service worker di root
            readfile($sw_file);
            exit;
        }
    }
    
    // Pastikan request ada di root
    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] === '/manifest.json') {
        $manifest_file = UMH_PLUGIN_DIR . 'pwa/manifest.json';
        if (file_exists($manifest_file)) {
            header('Content-Type: application/json');
            readfile($manifest_file);
            exit;
        }
    }
}
// Hook 'init' mungkin terlalu cepat, 'parse_request' lebih baik untuk file di root
add_action('parse_request', 'umh_serve_service_worker');

// 11. Inisialisasi CORS
function umh_init_cors() {
    if (class_exists('UMH_CORS')) {
        $umh_cors = new UMH_CORS();
        add_action('rest_api_init', array($umh_cors, 'add_cors_headers'));
    }
}
add_action('init', 'umh_init_cors');