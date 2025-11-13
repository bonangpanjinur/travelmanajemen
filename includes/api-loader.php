<?php
// File: includes/api-loader.php
// File ini mendaftarkan SEMUA endpoint API ke WordPress

if (!defined('ABSPATH')) exit;

// Load semua file logika API
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-manifest.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-packages.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-tasks.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-marketing.php'); // Leads
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-hr.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-users.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-finance.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-uploads.php');
// File baru yang "tertinggal":
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-stats.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-logs.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-export.php');
require_once(UMROH_PLUGIN_DIR . 'includes/api/api-print.php');

/**
 * Mendaftarkan semua rute REST API
 */
function umroh_register_api_routes() {
    $ns = UMROH_API_NAMESPACE; // 'umroh/v1'

    // --- MANIFEST JEMAAH ---
    register_rest_route($ns, '/manifest', [
        'methods' => 'GET',
        'callback' => 'umroh_get_manifest',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/manifest', [
        'methods' => 'POST',
        'callback' => 'umroh_create_manifest',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/manifest/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'umroh_update_manifest',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);

    // --- PEMBAYARAN & REFUND (terkait Manifest) ---
    register_rest_route($ns, '/manifest/(?P<id>\d+)/payments', [
        'methods' => 'GET',
        'callback' => 'umroh_get_payments_by_manifest_id',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/manifest/(?P<id>\d+)/payment', [
        'methods' => 'POST',
        'callback' => 'umroh_add_payment',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/manifest/(?P<id>\d+)/refund', [
        'methods' => 'POST',
        'callback' => 'umroh_process_refund',
        'permission_callback' => 'umroh_check_permission_admin' // Hanya Admin/Owner
    ]);

    // --- PAKET ---
    register_rest_route($ns, '/packages', [
        'methods' => 'GET',
        'callback' => 'umroh_get_packages',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/packages', [
        'methods' => 'POST',
        'callback' => 'umroh_create_package',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);

    // --- TUGAS (TASKS) ---
    register_rest_route($ns, '/tasks', [
        'methods' => 'GET',
        'callback' => 'umroh_get_tasks',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/tasks', [
        'methods' => 'POST',
        'callback' => 'umroh_create_task',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/tasks/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'umroh_update_task',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);

    // --- LEADS (MARKETING) ---
    register_rest_route($ns, '/leads', [
        'methods' => 'GET',
        'callback' => 'umroh_get_leads',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/leads', [
        'methods' => 'POST',
        'callback' => 'umroh_create_lead',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);

    // --- HR (ABSEN & CUTI) ---
    register_rest_route($ns, '/hr/attendance', [
        'methods' => 'GET',
        'callback' => 'umroh_get_attendance',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/hr/checkin', [
        'methods' => 'POST',
        'callback' => 'umroh_handle_checkin',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/hr/leave', [
        'methods' => 'GET',
        'callback' => 'umroh_get_leave_requests',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/hr/leave', [
        'methods' => 'POST',
        'callback' => 'umroh_submit_leave_request',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
     register_rest_route($ns, '/hr/leave/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'umroh_approve_leave_request',
        'permission_callback' => 'umroh_check_permission_admin' // Hanya Admin/Owner
    ]);

    // --- KARYAWAN (USERS) ---
    register_rest_route($ns, '/users', [
        'methods' => 'GET',
        'callback' => 'umroh_get_users',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);

    // --- KEUANGAN (FINANCE) - Admin Only ---
    register_rest_route($ns, '/finance', [
        'methods' => 'GET',
        'callback' => 'umroh_get_finance_logs',
        'permission_callback' => 'umroh_check_permission_admin'
    ]);
    register_rest_route($ns, '/finance', [
        'methods' => 'POST',
        'callback' => 'umroh_create_finance_entry',
        'permission_callback' => 'umroh_check_permission_admin'
    ]);

    // --- UPLOAD FILE ---
    register_rest_route($ns, '/upload', [
        'methods' => 'POST',
        'callback' => 'umroh_handle_upload',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);

    // --- DASHBOARD & LOGS (File Baru) ---
    register_rest_route($ns, '/dashboard/stats', [
        'methods' => 'GET',
        'callback' => 'umroh_get_dashboard_stats',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    register_rest_route($ns, '/logs', [
        'methods' => 'GET',
        'callback' => 'umroh_get_audit_logs',
        'permission_callback' => 'umroh_check_permission_admin'
    ]);

    // --- EXPORT (File Baru) ---
    register_rest_route($ns, '/export/manifest', [
        'methods' => 'GET',
        'callback' => 'umroh_export_manifest',
        'permission_callback' => 'umroh_check_permission_staff'
    ]);
    
    // --- PRINT (File Baru) ---
    // Note: Permission callback 'true' agar bisa dibuka di tab baru
    // Keamanan ditangani oleh Nonce di URL
    register_rest_route($ns, '/print/invoice', [
        'methods' => 'GET',
        'callback' => 'umroh_print_invoice',
        'permission_callback' => '__return_true' 
    ]);
}
add_action('rest_api_init', 'umroh_register_api_routes');