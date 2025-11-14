<?php
/**
 * Plugin Name:       Umroh Manager Hybrid (Backend + Admin UI)
 * Plugin URI:        https://www.jannahfirdaustravel.com/
 * Description:       Menyediakan REST API (Headless) DAN UI Admin untuk Super Admin.
 * Version:           1.6.0 (Full Refactor)
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Bonang Panji Nur
 * Author URI:        https://bonang.my.id/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       umroh-manager
 * Domain Path:       /languages
 */

// Exit jika diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

// Definisi konstanta plugin
define('UMROH_MANAGER_VERSION', '1.6.0');
define('UMROH_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMROH_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Class utama UmrohManagerHybrid
 */
final class UmrohManagerHybrid {

    private static $_instance = null;

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Memuat file-file yang diperlukan.
     * Ini sekarang memuat SEMUA file API secara eksplisit.
     */
    private function includes() {
        // Core Utilities
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/utils.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/db-schema.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/cors.php';

        // Admin UI Files
        require_once UMROH_MANAGER_PLUGIN_DIR . 'admin/dashboard-react.php'; 
        require_once UMROH_MANAGER_PLUGIN_DIR . 'admin/settings-page.php';

        // === API FILES (Aman & Modern) ===
        // Auth & Users
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-users.php'; // Handle Login & User Management
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-jamaah.php';

        // Modul Inti Bisnis (Baru di-refaktor)
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-packages.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-hotels.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-flights.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-finance.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-hr.php'; // (Karyawan)
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-tasks.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-departures.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-marketing.php';
        
        // Modul Pendukung (Asumsi sudah modern)
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-categories.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-export.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-logs.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-manifest.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-print.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-stats.php';
        require_once UMROH_MANAGER_PLUGIN_DIR . 'includes/api/api-uploads.php';

        // CATATAN: Pastikan untuk MENGHAPUS file 'includes/api-loader.php' dan 'includes/api/api-auth.php'
    }

    /**
     * Inisialisasi hooks WordPress.
     */
    private function init_hooks() {
        // Hook aktivasi
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Hooks Admin UI
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Hooks Backend API
        $umh_cors = new UMH_CORS();
        add_action('rest_api_init', array($umh_cors, 'add_cors_headers'));

        // Init Halaman Pengaturan
        $umh_settings_page = new UMH_Settings_Page();
        add_action('admin_init', array($umh_settings_page, 'register_settings'));
    }

    public function activate() {
        umroh_manager_create_tables();
        // Set default options
        if (get_option('umh_settings') === false) {
            add_option('umh_settings', ['allowed_origins' => home_url()]);
        }
    }

    public function deactivate() {
        // Kosongkan
    }

    /**
     * Tambahkan menu di admin dashboard.
     */
    public function admin_menu() {
        add_menu_page(
            'Umroh Manager',
            'Umroh Manager',
            'manage_options', 
            'umroh-manager-dashboard',
            'umroh_manager_render_dashboard_react',
            'dashicons-airplane',
            6
        );

        add_submenu_page(
            'umroh-manager-dashboard',
            'Pengaturan CORS & API',
            'Pengaturan',
            'manage_options',
            'umh-settings',
            'umh_render_settings_page'
        );
    }

    /**
     * Enqueue scripts dan styles untuk admin.
     */
    public function enqueue_admin_scripts($hook) {
        // Halaman Dashboard React
        if ($hook === 'toplevel_page_umroh-manager-dashboard') {
            
            $asset_file_path = UMROH_MANAGER_PLUGIN_DIR . 'build/index.asset.php';
            if (!file_exists($asset_file_path)) {
                wp_die('File asset build/index.asset.php tidak ditemukan. Jalankan npm run build.');
                return;
            }
            
            $asset_file = include($asset_file_path);

            wp_enqueue_script(
                'umroh-manager-react-app',
                UMROH_MANAGER_PLUGIN_URL . 'build/index.js',
                $asset_file['dependencies'],
                $asset_file['version'],
                true
            );

            wp_enqueue_style(
                'umroh-manager-admin-style',
                UMROH_MANAGER_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                UMROH_MANAGER_VERSION
            );

            wp_localize_script('umroh-manager-react-app', 'umh_wp_data', array(
                'api_url'      => esc_url_raw(rest_url('umh/v1/')), 
                'api_nonce'    => wp_create_nonce('wp_rest'),
                'is_wp_admin'  => true,
                'home_url'     => home_url(),
                'plugin_url'   => UMROH_MANAGER_PLUGIN_URL,
            ));
        }

        // Halaman Pengaturan
        if ($hook === 'umroh-manager_page_umh-settings') {
             wp_enqueue_style(
                'umroh-manager-settings-style',
                UMROH_MANAGER_PLUGIN_URL . 'assets/css/admin-style.css',
                array(),
                UMROH_MANAGER_VERSION
            );
        }
    }
}

/**
 * Inisialisasi plugin.
 */
function umroh_manager_init() {
    return UmrohManagerHybrid::instance();
}

// Mulai plugin
umroh_manager_init();