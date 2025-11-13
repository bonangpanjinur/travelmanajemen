<?php
// Lokasi: includes/utils.php
if (!defined('ABSPATH')) exit;

/**
 * PENTING: Pengecekan Hak Akses
 * Memeriksa apakah user yang sedang login adalah Administrator.
 */
function umroh_check_permission_admin() {
    // 'manage_options' adalah capability default untuk Administrator
    return current_user_can('manage_options');
}

/**
 * PENTING: Pengecekan Hak Akses
 * Memeriksa apakah user adalah Staff (Editor) ATAU Administrator.
 */
function umroh_check_permission_staff() {
    // 'edit_posts' adalah capability default untuk Editor (Staff)
    // Kita cek juga 'manage_options' agar Admin juga dianggap sebagai Staff
    return current_user_can('edit_posts') || current_user_can('manage_options');
}


/**
 * Helper untuk mencatat Log Aktivitas
 */
function umroh_log_activity($action, $item_id = 0, $details = '') {
    global $wpdb;
    
    if (is_array($details) || is_object($details)) {
        $details = json_encode($details);
    }

    $wpdb->insert($wpdb->prefix . 'umroh_audit_logs', [
        'user_id' => get_current_user_id(),
        'action' => $action,
        'item_id' => $item_id,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}