<?php
/**
 * Plugin Name:       Umroh Manager Hybrid
 * Plugin URI:        https://www.jannahfirdaustravel.com/
 * Description:       Manajemen Umroh, Jamaah, Paket, dan Keuangan.
 * Version:           1.1.0
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
define('UMROH_MANAGER_VERSION', '1.1.0');
define('UMROH_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMROH_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Class utama UmrohManagerHybrid
 */
final class UmrohManagerHybrid {

    /**
     * Versi plugin.
     *
     * @var string
     */
    public $version = UMROH_MANAGER_VERSION;

    /**
     * Instance tunggal dari class.
     *
     * @var UmrohManagerHybrid|null
     */
    private static $_instance = null;

    /**
     * Main UmrohManagerHybrid Instance.
     *
     * Memastikan hanya satu instance dari UmrohManagerHybrid yang berjalan.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Memuat file-file yang diperlukan.
     */
    private function includes() {
        require_once plugin_dir_path(__FILE__) . 'includes/utils.php';
        require_once plugin_dir_path(__FILE__) . 'includes/db-schema.php';
        require_once plugin_dir_path(__FILE__) . 'includes/api-loader.php';
        require_once plugin_dir_path(__FILE__) . 'includes/cors.php';
        
        // Admin
        require_once plugin_dir_path(__FILE__) . 'admin/dashboard.php';
        require_once plugin_dir_path(__FILE__) . 'admin/dashboard-react.php';
    }

    /**
     * Inisialisasi hooks WordPress.
     */
    private function init_hooks() {
        // Hook aktivasi
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Hook deaktivasi
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Inisialisasi REST API
        add_action('rest_api_init', array('UmrohManagerAPILoader', 'init'));

        // Tambah menu admin
        add_action('admin_menu', array($this, 'admin_menu'));

        // Enqueue script dan style
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Custom login
        add_action('login_enqueue_scripts', array($this, 'custom_login_style'));
    }

    /**
     * Fungsi yang dipanggil saat aktivasi plugin.
     *
     * @param bool $network_wide Apakah diaktifkan di seluruh jaringan.
     */
    public function activate($network_wide) {
        umroh_manager_create_tables(); // Panggil fungsi pembuatan tabel
    }

    /**
     * Fungsi yang dipanggil saat deaktivasi plugin.
     */
    public function deactivate() {
        // Kosongkan - bisa diisi jika perlu membersihkan sesuatu
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
            'umroh_manager_render_dashboard_react', // Fungsi callback untuk render React App
            'dashicons-airplane',
            6
        );
    }

    /**
     * Enqueue scripts dan styles untuk admin.
     */
    public function enqueue_admin_scripts($hook) {
        // Hanya load di halaman plugin kita
        if ('toplevel_page_umroh-manager-dashboard' !== $hook) {
            return;
        }

        $asset_file_path = plugin_dir_path(__FILE__) . 'build/index.asset.php';
        if (file_exists($asset_file_path)) {
            $asset_file = include($asset_file_path);

            // Enqueue script React utama
            wp_enqueue_script(
                'umroh-manager-react-app',
                plugin_dir_url(__FILE__) . 'build/index.js',
                $asset_file['dependencies'],
                $asset_file['version'],
                true // Muat di footer
            );

            // Lokalisasi script untuk data PHP ke JS (cth: REST URL, nonce)
            wp_localize_script('umroh-manager-react-app', 'umrohManagerData', array(
                'rest_url' => esc_url_raw(rest_url('umroh/v1/')),
                'nonce'    => wp_create_nonce('wp_rest'),
                'home_url' => home_url(),
                'plugin_url' => UMROH_MANAGER_PLUGIN_URL,
            ));
            
            // (Opsional) Enqueue CSS jika ada
            // wp_enqueue_style(
            //     'umroh-manager-react-app-style',
            //     plugin_dir_url(__FILE__) . 'build/index.css',
            //     array(),
            //     $asset_file['version']
            // );

        } else {
            wp_die('File asset build/index.asset.php tidak ditemukan. Jalankan npm run build.');
        }
    }
    
    /**
     * Custom style untuk halaman login.
     */
    public function custom_login_style() {
        wp_enqueue_style(
            'umroh-manager-custom-login',
            plugin_dir_url(__FILE__) . 'assets/css/admin-style.css',
            array(),
            UMROH_MANAGER_VERSION
        );
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