<?php
// Lokasi: wp-content/plugins/umroh-manager-headless/admin/dashboard.php

if (!defined('ABSPATH')) exit;

function umroh_register_admin_page() {
    add_menu_page(
        'Umroh Manager API',
        'Umroh Manager API',
        'manage_options',
        'umroh-manager-api',
        'umroh_render_admin_info',
        'dashicons-database', // Ikon database
        99
    );
}
add_action('admin_menu', 'umroh_register_admin_page');

function umroh_render_admin_info() {
    // Cek status plugin paket
    global $wpdb;
    $table_paket = $wpdb->prefix . 'uhp_packages';
    $paket_active = ($wpdb->get_var("SHOW TABLES LIKE '$table_paket'") == $table_paket);
    
    ?>
    <div class="wrap">
        <h1>Umroh Manager Headless API</h1>
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>Status Sistem</h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td style="width: 200px;"><strong>Status API</strong></td>
                        <td><span style="color: green; font-weight: bold;">AKTIF</span></td>
                    </tr>
                    <tr>
                        <td><strong>API Base URL</strong></td>
                        <td><code><?php echo site_url('/wp-json/umroh/v1'); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Integrasi Plugin Paket</strong></td>
                        <td>
                            <?php if($paket_active): ?>
                                <span class="dashicons dashicons-yes" style="color: green;"></span> Terhubung (Tabel ditemukan)
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: red;"></span> Error: Plugin Paket Umroh Haji tidak aktif!
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <hr>
            <h3>Cara Koneksi Frontend React:</h3>
            <ol>
                <li>Buka Aplikasi React Dashboard.</li>
                <li>Masukkan URL WordPress: <strong><?php echo site_url(); ?></strong></li>
                <li>Gunakan Username & <strong>Application Password</strong> (Bukan password login biasa).</li>
            </ol>
            <p><em>Untuk membuat Application Password, pergi ke Users > Profile > Scroll ke bawah.</em></p>
        </div>
    </div>
    <?php
}