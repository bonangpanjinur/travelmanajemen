<?php
/*
Plugin Name: Umroh Manager Hybrid
Plugin URI: https://github.com/bonangpanjinur/travelmanajemen
Description: Plugin kustom untuk manajemen Umroh, menggabungkan backend WordPress dengan frontend React.
Version: 2.0.1
Author: Bonang Panji Nur
Author URI: https://bonang.id
License: GPLv2 or later
Text Domain: umroh-manager-hybrid
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('UMH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UMH_PLUGIN_VERSION', '2.0.1'); // Versi dinaikkan

// Include necessary files
require_once UMH_PLUGIN_DIR . 'includes/db-schema.php';
// PERBAIKAN: class-umh-crud-controller.php harus di-load SEBELUM file api
require_once UMH_PLUGIN_DIR . 'includes/class-umh-crud-controller.php';
require_once UMH_PLUGIN_DIR . 'includes/utils.php';
require_once UMH_PLUGIN_DIR . 'includes/cors.php';
require_once UMH_PLUGIN_DIR . 'admin/dashboard-react.php';
require_once UMH_PLUGIN_DIR . 'admin/settings-page.php';

// Include API files
// File-file ini sekarang akan menginstansiasi UMH_CRUD_Controller
// atau meng-extend-nya.
require_once UMH_PLUGIN_DIR . 'includes/api/api-stats.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-roles.php'; // File yang menyebabkan error
require_once UMH_PLUGIN_DIR . 'includes/api/api-categories.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-packages.php'; // (Direfaktor)
require_once UMH_PLUGIN_DIR . 'includes/api/api-jamaah.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-payments.php'; // (Direfaktor)
require_once UMH_PLUGIN_DIR . 'includes/api/api-flights.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-hotels.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-departures.php'; // (Direfaktor)
require_once UMH_PLUGIN_DIR . 'includes/api/api-tasks.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-users.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-uploads.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-finance.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-hr.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-marketing.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-flight-bookings.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-hotel-bookings.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-logs.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-print.php';
require_once UMH_PLUGIN_DIR . 'includes/api/api-export.php';


// Activation hook
register_activation_hook(__FILE__, 'umh_activate_plugin');
function umh_activate_plugin() {
    umh_create_tables();
    // Fungsi-fungsi ini sepertinya tidak ada, saya komentar dulu
    // umh_create_default_roles();
    // umh_register_default_user_roles();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'umh_deactivate_plugin');
function umh_deactivate_plugin() {
    // Code to run on deactivation
}

// Enqueue admin scripts and styles
function umh_enqueue_admin_scripts($hook) {
    if ('toplevel_page_umroh-manager' !== $hook) {
        return;
    }

    $asset_file_path = UMH_PLUGIN_DIR . 'build/index.asset.php';
    if (!file_exists($asset_file_path)) {
        wp_die('File build/index.asset.php tidak ditemukan. Jalankan `npm run build`.');
    }
    $asset_file = include($asset_file_path);

    wp_enqueue_script(
        'umh-react-app',
        UMH_PLUGIN_URL . 'build/index.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );

    wp_enqueue_style(
        'umh-admin-style',
        UMH_PLUGIN_URL . 'assets/css/admin-style.css',
        [],
        UMH_PLUGIN_VERSION
    );

    // Fungsi umh_get_current_user_data() tidak ada, ganti dengan umh_get_current_user_data_for_react()
    // Fungsi umh_get_all_roles_data() tidak ada, saya tambahkan
    if (!function_exists('umh_get_all_roles_data')) {
        function umh_get_all_roles_data() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'umh_roles';
            $results = $wpdb->get_results("SELECT role_key, role_name FROM $table_name", ARRAY_A);
            $roles = [];
            foreach ($results as $row) {
                $roles[$row['role_key']] = ['display_name' => $row['role_name']];
            }
            // Tambahkan role Super Admin WP secara manual
            $roles['super_admin'] = ['display_name' => 'Super Admin'];
            return $roles;
        }
    }
    
    if (!function_exists('umh_get_current_user_data_for_react')) {
         /**
         * Sinkronkan/buat data user untuk Super Admin WP di tabel umh_users.
         * Mengembalikan data user untuk di-pass ke React.
         */
        function umh_get_current_user_data_for_react() {
            global $wpdb;
            $wp_user = wp_get_current_user();
            if (!$wp_user || $wp_user->ID === 0 || !current_user_can('manage_options')) {
                return ['id' => null, 'name' => 'Guest', 'email' => '', 'role' => 'guest', 'token' => null, 'capabilities' => []];
            }

            $table_name = $wpdb->prefix . 'umh_users';
            $umh_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE wp_user_id = %d AND role = 'super_admin'", $wp_user->ID));

            if (!$umh_user) {
                // Buat entri baru untuk Super Admin jika belum ada
                $wpdb->insert($table_name, [
                    'wp_user_id' => $wp_user->ID,
                    'full_name' => $wp_user->display_name,
                    'email' => $wp_user->user_email,
                    'role' => 'super_admin',
                    'status' => 'active',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $umh_user_id = $wpdb->insert_id;
            } else {
                $umh_user_id = $umh_user->id;
            }
            
            // Buat token sementara untuk sesi ini
            $token_data = umh_generate_auth_token($umh_user_id, 'super_admin');

            return [
                'id' => $umh_user_id,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => 'super_admin',
                'token' => $token_data['token'],
                'capabilities' => ['manage_options'], // Super admin bisa melakukan segalanya
            ];
        }
    }

    wp_localize_script('umh-react-app', 'umhData', [
        'apiUrl' => esc_url_raw(rest_url('umh/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'currentUser' => umh_get_current_user_data_for_react(),
        'roles' => umh_get_all_roles_data(),
    ]);
}
add_action('admin_enqueue_scripts', 'umh_enqueue_admin_scripts');

// Add PWA manifest and service worker
function umh_add_pwa_links() {
    // Hanya tampilkan di halaman login atau halaman plugin kita
    global $pagenow;
    if ($pagenow === 'wp-login.php' || (isset($_GET['page']) && $_GET['page'] === 'umroh-manager')) {
        echo '<link rel="manifest" href="' . esc_url(UMH_PLUGIN_URL . 'pwa/manifest.json') . '">';
        echo '<script>
            if ("serviceWorker" in navigator) {
                window.addEventListener("load", () => {
                    navigator.serviceWorker.register("' . esc_url(UMH_PLUGIN_URL . 'pwa/service-worker.js') . '")
                        .then(registration => console.log("ServiceWorker registration successful with scope: ", registration.scope))
                        .catch(error => console.log("ServiceWorker registration failed: ", error));
                });
            }
        </script>';
    }
}
add_action('login_head', 'umh_add_pwa_links');
add_action('admin_head', 'umh_add_pwa_links');


// Custom login page styling
function umh_custom_login_page() {
    $bg_url = UMH_PLUGIN_URL . 'assets/images/login-bg.jpg.png';
    ?>
    <style type="text/css">
        body.login {
            background-image: url('<?php echo esc_url($bg_url); ?>') !important;
            background-size: cover !important;
            background-position: center !important;
        }
        #login h1 a, .login h1 a {
            background-image: none !important;
            width: 100% !important;
            text-align: center !important;
            color: #fff !important;
            font-size: 24px !important;
            font-weight: bold !important;
            text-indent: 0 !important;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5) !important;
        }
        #loginform {
            border-radius: 10px !important;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2) !important;
        }
         .login #login_error, .login .message, .login .success {
            border-radius: 5px !important;
        }
    </style>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var link = document.querySelector('#login h1 a');
            if (link) {
                link.href = '<?php echo esc_url(home_url('/')); ?>';
                link.textContent = '<?php echo esc_html(get_bloginfo('name')); ?>';
            }
        });
    </script>
    <?php
}
add_action('login_enqueue_scripts', 'umh_custom_login_page');