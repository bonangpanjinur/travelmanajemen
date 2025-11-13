<?php
// Lokasi: wp-content/plugins/umroh-manager/admin/dashboard-react.php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <!-- 
      Ini adalah "kanvas" kosong. 
      React (dari file JS yang di-enqueue) akan mengambil alih div ini.
    -->
    <div id="root">
        <div style="display: flex; align-items: center; justify-content: center; height: 80vh; flex-direction: column;">
            <svg style="width: 50px; height: 50px; color: #4F46E5; animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p style="margin-top: 15px; font-size: 1.1rem; font-family: sans-serif; color: #333;">Memuat Panel Kontrol...</p>
        </div>
        <style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
    </div>
</div>