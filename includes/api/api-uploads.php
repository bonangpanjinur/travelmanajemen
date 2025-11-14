<?php
// File: includes/api/api-uploads.php
// Mengelola endpoint REST untuk upload file.

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_uploads_routes');

function umh_register_uploads_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten

    // PERBAIKAN: Izinkan semua role yang login untuk mengupload
    $permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff']);

    // Endpoint untuk meng-upload file
    register_rest_route($namespace, '/uploads', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'umh_handle_rest_upload',
            'permission_callback' => $permissions, // PERBAIKAN
            'args' => [
                'file' => [
                    'type' => 'file',
                    'description' => 'File to upload.',
                    'required' => true,
                ],
                'jamaah_id' => [
                    'type' => 'integer',
                    'description' => 'ID Jemaah to associate with.',
                    'required' => false,
                ],
                'upload_type' => [
                    'type' => 'string',
                    'description' => 'Type of upload (e.g., passport, photo).',
                    'required' => false,
                ],
            ],
        ],
    ]);
}

// Callback: Handle REST Upload
function umh_handle_rest_upload(WP_REST_Request $request) {
    global $wpdb;
    
    // Membutuhkan file-file WordPress untuk upload
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Ambil file dari request
    $files = $request->get_file_params();
    if (empty($files['file'])) {
        return new WP_Error('no_file', 'No file provided.', ['status' => 400]);
    }

    $file = $files['file'];
    
    // Dapatkan ID pengguna yang terautentikasi
    $user_context = umh_get_current_user_context($request); // PERBAIKAN: Gunakan $request
    if (is_wp_error($user_context)) {
        return $user_context;
    }
    $user_id = $user_context['user_id'];

    // Menangani upload
    $upload_overrides = ['test_form' => false];
    $movefile = wp_handle_upload($file, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        $filename = basename($movefile['url']);
        $file_type = $movefile['type'];
        $file_url = $movefile['url'];

        // Menyiapkan data attachment untuk disimpan di media library
        $attachment = [
            'guid' => $file_url,
            'post_mime_type' => $file_type,
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        // Menyimpan attachment dan mendapatkan ID
        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
        
        // Generate metadata untuk attachment (penting untuk gambar)
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // --- Logika Asosiasi File ---
        // Anda dapat menyimpan $attach_id atau $file_url ke tabel lain
        
        $jamaah_id = $request->get_param('jamaah_id');
        $upload_type = $request->get_param('upload_type');

        if (!empty($jamaah_id) && !empty($upload_type)) {
            // Jika jamaah_id dan tipe upload ada, update tabel jamaah
            $table_name = $wpdb->prefix . 'umh_jamaah';
            
            // Sanitasi upload_type agar hanya kolom yang valid
            $allowed_columns = ['passport_scan', 'ktp_scan', 'profile_photo']; // Sesuaikan dengan kolom di db
            
            if (in_array($upload_type, $allowed_columns)) {
                $wpdb->update(
                    $table_name,
                    [$upload_type => $file_url], // Simpan URL file
                    ['id' => $jamaah_id]
                );
                // umh_create_log_entry($user_id, 'upload', 'jamaah', $jamaah_id, "Uploaded $upload_type");
            }
        }
        
        // Simpan ke tabel UMH Uploads
        $uploads_table = $wpdb->prefix . 'umh_uploads';
        $wpdb->insert($uploads_table, [
            'user_id' => $user_id,
            'jamaah_id' => $jamaah_id ? (int)$jamaah_id : null,
            'attachment_id' => $attach_id,
            'file_url' => $file_url,
            'file_type' => $file_type,
            'upload_type' => $upload_type,
            'created_at' => current_time('mysql'),
        ]);

        return new WP_REST_Response([
            'message' => 'File uploaded successfully.',
            'url' => $file_url,
            'attachment_id' => $attach_id,
        ], 201);

    } else {
        return new WP_Error('upload_error', $movefile['error'], ['status' => 500]);
    }
}