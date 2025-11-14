<?php
// File: admin/settings-page.php
// Membuat halaman pengaturan untuk CORS dinamis

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Fungsi callback untuk me-render halaman pengaturan.
 * Dipanggil oleh add_submenu_page().
 */
function umh_render_settings_page() {
    ?>
    <div class="wrap umh-settings-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Konfigurasi penting untuk Umroh Manager Hybrid.</p>
        
        <form action="options.php" method="post">
            <?php
            // Output fields keamanan WordPress
            settings_fields('umh_settings_group');
            // Output seksi pengaturan
            do_settings_sections('umh-settings');
            // Output tombol submit
            submit_button('Simpan Pengaturan');
            ?>
        </form>
    </div>
    <?php
}

/**
 * Class untuk mendaftarkan pengaturan.
 */
class UMH_Settings_Page {

    public function __construct() {
        // Didaftarkan di 'admin_init' hook
    }

    /**
     * Mendaftarkan settings, sections, dan fields.
     */
    public function register_settings() {
        // Nama grup pengaturan
        $option_group = 'umh_settings_group';
        // Nama opsi di tabel wp_options
        $option_name = 'umh_settings';

        // Daftarkan pengaturan
        register_setting(
            $option_group,
            $option_name,
            array($this, 'sanitize_settings') // Fungsi sanitasi
        );

        // Tambah seksi pengaturan
        add_settings_section(
            'umh_cors_section',
            'Pengaturan CORS (Cross-Origin Resource Sharing)',
            array($this, 'render_cors_section_text'),
            'umh-settings' // Halaman
        );

        // Tambah field untuk 'Allowed Origins'
        add_settings_field(
            'umh_allowed_origins',
            'Allowed Origins',
            array($this, 'render_allowed_origins_field'),
            'umh-settings', // Halaman
            'umh_cors_section' // Seksi
        );
    }

    /**
     * Teks deskripsi untuk seksi CORS.
     */
    public function render_cors_section_text() {
        echo '<p>Masukkan URL domain frontend (milik Karyawan/Owner) yang diizinkan untuk mengakses API. Masukkan satu URL per baris.</p>';
        echo '<p>Contoh: <code>https://app.travel-anda.com</code></p>';
    }

    /**
     * Render field <textarea> untuk 'Allowed Origins'.
     */
    public function render_allowed_origins_field() {
        // Ambil pengaturan yang tersimpan
        $options = get_option('umh_settings');
        $allowed_origins = $options['allowed_origins'] ?? '';
        
        ?>
        <textarea name="umh_settings[allowed_origins]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($allowed_origins); ?></textarea>
        <?php
    }

    /**
     * Sanitasi input sebelum disimpan ke database.
     */
    public function sanitize_settings($input) {
        $sanitized_input = [];

        if (isset($input['allowed_origins'])) {
            // Sanitasi setiap baris sebagai URL
            $origins = explode("\n", $input['allowed_origins']);
            $sanitized_origins = [];
            foreach ($origins as $origin) {
                $trimmed_origin = trim($origin);
                if (!empty($trimmed_origin)) {
                    // Validasi sederhana, bisa diperketat jika perlu
                    $sanitized_origins[] = esc_url_raw($trimmed_origin); 
                }
            }
            $sanitized_input['allowed_origins'] = implode("\n", $sanitized_origins);
        }

        return $sanitized_input;
    }
}