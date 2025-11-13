<?php
if (!defined('ABSPATH')) exit;

function umroh_get_tasks() {
    global $wpdb;
    // Ambil semua tugas
    $tasks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}umroh_tasks ORDER BY deadline ASC");
    return new WP_REST_Response($tasks, 200);
}

function umroh_create_task($request) {
    global $wpdb;
    $p = $request->get_json_params();
    $admin_id = get_current_user_id();

    $wpdb->insert($wpdb->prefix . 'umroh_tasks', [
        'title' => sanitize_text_field($p['title']),
        'description' => sanitize_textarea_field($p['description']),
        'assigned_to' => intval($p['assigned_to']),
        'assigned_by' => $admin_id,
        'deadline' => $p['deadline'],
        'status' => 'Pending'
    ]);
    return new WP_REST_Response(['message' => 'Tugas dibuat'], 201);
}

function umroh_update_task($request) {
    global $wpdb;
    $id = $request['id'];
    $p = $request->get_json_params();
    
    // Biasanya staff hanya update status jadi 'Done'
    $wpdb->update($wpdb->prefix . 'umroh_tasks', 
        ['status' => sanitize_text_field($p['status'])], 
        ['id' => $id]
    );
    return new WP_REST_Response(['message' => 'Status diupdate'], 200);
}