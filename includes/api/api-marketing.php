<?php
if (!defined('ABSPATH')) exit;

function umroh_get_leads() {
    global $wpdb;
    $leads = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}umroh_leads ORDER BY created_at DESC");
    return new WP_REST_Response($leads, 200);
}

function umroh_create_lead($request) {
    global $wpdb;
    $p = $request->get_json_params();
    
    $wpdb->insert($wpdb->prefix . 'umroh_leads', [
        'name' => sanitize_text_field($p['name']),
        'phone' => sanitize_text_field($p['phone']),
        'source' => sanitize_text_field($p['source']),
        'status' => 'Cold',
        'notes' => sanitize_textarea_field($p['notes'])
    ]);
    return new WP_REST_Response(['success'=>true], 201);
}