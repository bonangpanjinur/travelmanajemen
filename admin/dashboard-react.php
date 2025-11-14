<?php
// File: admin/dashboard-react.php
// Ini adalah file "host" untuk aplikasi React Anda.

// Exit jika diakses langsung
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fungsi ini adalah yang dipanggil oleh add_menu_page() 
 * di file umroh-manager-hybrid.php.
 */
function umroh_manager_render_dashboard_react() {

    // Script dan style sudah di-enqueue dengan benar di class utama.
    // Kita hanya perlu menyediakan div target untuk React.

    ?>
    <div class="wrap">
        <!-- 
          PERBAIKAN: ID diubah menjadi 'umh-react-app-root' 
          agar sesuai dengan yang dicari oleh build/index.js 
          (dari src/index.jsx)
        -->
        <div id="umh-react-app-root">
            <!-- React akan menggantikan konten ini -->
            <p style="padding: 20px; text-align: center;">Memuat aplikasi manajemen...</p>
        </div>
    </div>
    <?php
}