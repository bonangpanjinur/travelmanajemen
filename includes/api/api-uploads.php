<?php
// Lokasi: wp-content/plugins/umroh-manager-headless/includes/api/api-uploads.php

if (!defined('ABSPATH')) exit;

// Kita butuh file-file ini untuk menangani upload
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

/**
 * POST /umroh/v1/upload
 * Menerima file (bukti bayar, foto paspor) dari Frontend React.
 * * Frontend harus mengirim menggunakan 'multipart/form-data'
 * dengan nama field 'file'.
 */
function umroh_handle_upload($request) {
    // Cek permission, minimal Staff
    if (!umroh_check_permission_staff()) {
        return new WP_Error('forbidden', 'Hanya staff yang bisa upload file', ['status' => 403]);
    }

    if (!isset($_FILES['file'])) {
        return new WP_Error('no_file', 'File tidak ditemukan', ['status' => 400]);
    }
    
    // 'file' adalah nama field yang dikirim dari React (new FormData().append('file', ...))
    $file_id = media_handle_upload('file', 0); // 0 = tidak ada parent post

    if (is_wp_error($file_id)) {
        // Gagal upload
        return new WP_Error('upload_error', $file_id->get_error_message(), ['status' => 500]);
    }

    // Sukses, ambil URL file yang baru diupload
    $file_url = wp_get_attachment_url($file_id);
    
    // Catat log
    umroh_log_activity('FILE_UPLOAD', $file_id, "Upload: " . $file_url);

    // Kirim balasan ke React
    return new WP_REST_Response([
        'success' => true,
        'id' => $file_id, // ID attachment
        'url' => $file_url // URL file
    ], 201);
}
?>