<?php
// Lokasi: wp-content/plugins/umroh-manager-headless/includes/api/api-hr.php

if (!defined('ABSPATH')) exit;

// --- ABSENSI ---

/**
 * GET /umroh/v1/hr/attendance
 * Mengambil data absensi (misal: per bulan)
 */
function umroh_get_attendance($request) {
    global $wpdb;
    // Ambil parameter ?month=YYYY-MM, jika tidak ada, pakai bulan ini
    $month = $request->get_param('month') ? $request->get_param('month') : current_time('Y-m');
    
    $data = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, u.display_name 
         FROM {$wpdb->prefix}umroh_attendance a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         WHERE a.date LIKE %s
         ORDER BY a.date DESC, u.display_name ASC",
        $month . '%'
    ));
    return new WP_REST_Response($data, 200);
}

/**
 * POST /umroh/v1/hr/attendance
 * Karyawan submit absensi hari ini
 */
function umroh_submit_attendance($request) {
    global $wpdb;
    $p = $request->get_json_params();
    $user_id = get_current_user_id();
    
    // Cek duplikat absen hari ini
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}umroh_attendance WHERE user_id = %d AND date = %s",
        $user_id, current_time('Y-m-d')
    ));
    
    if ($existing) {
        return new WP_Error('duplicate_attendance', 'Anda sudah absen hari ini', ['status' => 400]);
    }

    $wpdb->insert($wpdb->prefix . 'umroh_attendance', [
        'user_id' => $user_id,
        'date' => current_time('Y-m-d'),
        'status' => sanitize_text_field($p['status']), // Hadir, Sakit, Izin
        'check_in_time' => ($p['status'] == 'Hadir') ? current_time('H:i:s') : null,
        'notes' => sanitize_textarea_field($p['notes'])
    ]);
    
    $new_id = $wpdb->insert_id;
    umroh_log_activity('ATTENDANCE', $new_id, "Absen: " . $p['status']);

    return new WP_REST_Response(['success' => true, 'id' => $new_id], 201);
}

// --- CUTI / LIBUR ---

/**
 * GET /umroh/v1/hr/leave
 * Mengambil daftar pengajuan cuti (semua)
 */
function umroh_get_leave_requests($request) {
    global $wpdb;
    
    $data = $wpdb->get_results("
        SELECT l.*, u.display_name 
        FROM {$wpdb->prefix}umroh_leave l
        LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
        ORDER BY l.start_date DESC
    ");
    return new WP_REST_Response($data, 200);
}


/**
 * POST /umroh/v1/hr/leave
 * Karyawan submit pengajuan cuti
 */
function umroh_request_leave($request) {
    global $wpdb;
    $p = $request->get_json_params();
    $user_id = get_current_user_id();

    $wpdb->insert($wpdb->prefix . 'umroh_leave', [
        'user_id' => $user_id,
        'type' => sanitize_text_field($p['type']),
        'start_date' => $p['start_date'],
        'end_date' => $p['end_date'],
        'reason' => sanitize_textarea_field($p['reason']),
        'status' => 'Pending'
    ]);
    
    $new_id = $wpdb->insert_id;
    umroh_log_activity('LEAVE_REQUEST', $new_id, "Pengajuan Cuti: " . $p['type']);

    return new WP_REST_Response(['success' => true, 'id' => $new_id], 201);
}

/**
 * PUT /umroh/v1/hr/leave/{id}
 * Admin approve / reject cuti
 */
function umroh_approve_leave($request) {
    global $wpdb;
    $id = $request['id'];
    $p = $request->get_json_params(); // Misal: { "status": "Approved" }
    
    // Hanya Admin yg bisa approve
    if (!umroh_check_permission_admin()) {
        return new WP_Error('forbidden', 'Hanya admin yang bisa approve cuti', ['status' => 403]);
    }

    $wpdb->update($wpdb->prefix . 'umroh_leave',
        [
            'status' => sanitize_text_field($p['status']), // Approved / Rejected
            'approved_by' => get_current_user_id()
        ],
        ['id' => $id]
    );

    umroh_log_activity('LEAVE_STATUS', $id, "Status Cuti diubah ke: " . $p['status']);
    
    return new WP_REST_Response(['success' => true, 'message' => 'Status cuti diupdate'], 200);
}
?>