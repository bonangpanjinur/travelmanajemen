<?php
/**
 * File: includes/api/api-flights.php
 *
 * Mengganti file kerangka yang kosong dengan implementasi UMH_CRUD_Controller
 * untuk mengaktifkan CRUD pada tabel umh_flights.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Definisikan Skema Data (sesuai db-schema.php)
$flights_schema = [
    'airline'                => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'flight_number'          => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'departure_airport_code' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'arrival_airport_code'   => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
    'departure_time'         => ['type' => 'string', 'format' => 'date-time', 'required' => true],
    'arrival_time'           => ['type' => 'string', 'format' => 'date-time', 'required' => true],
    'cost_per_seat'          => ['type' => 'number', 'required' => false],
    'total_seats'            => ['type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint'],
];

// 2. Definisikan Izin
$flights_permissions = [
    'get_items'    => ['owner', 'admin_staff'],
    'get_item'     => ['owner', 'admin_staff'],
    'create_item'  => ['owner', 'admin_staff'],
    'update_item'  => ['owner', 'admin_staff'],
    'delete_item'  => ['owner'],
];

// 3. Inisialisasi Controller
// Ini secara otomatis membuat endpoint: /wp-json/umh/v1/flights
new UMH_CRUD_Controller('flights', 'umh_flights', $flights_schema, $flights_permissions);

// TODO: Buat endpoint kustom untuk 'umh_flight_bookings' jika diperlukan