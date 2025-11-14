<?php
// File: includes/api/api-print.php
// Mengelola endpoint untuk data yang siap cetak (HTML).

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('rest_api_init', 'umh_register_print_routes');

function umh_register_print_routes() {
    $namespace = 'umh/v1'; // Namespace baru yang konsisten

    // PERBAIKAN: Tentukan izin (baca-saja)
    $read_permissions = umh_check_api_permission(['owner', 'admin_staff', 'finance_staff', 'marketing_staff', 'hr_staff']);

    // Endpoint untuk print daftar jemaah per paket
    register_rest_route($namespace, '/print/jamaah-list/(?P<id>\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'umh_print_jamaah_list',
            'permission_callback' => $read_permissions, // PERBAIKAN
        ],
    ]);
}

// Callback: Print Jamaah List
function umh_print_jamaah_list(WP_REST_Request $request) {
    global $wpdb;
    
    $package_id = (int) $request['id'];
    
    // Menggunakan tabel UMH yang baru
    $jamaah_table = $wpdb->prefix . 'umh_jamaah';
    $packages_table = $wpdb->prefix . 'umh_packages';

    $package = $wpdb->get_row($wpdb->prepare("SELECT package_name FROM $packages_table WHERE id = %d", $package_id), ARRAY_A);
    
    if (!$package) {
        return new WP_Error('not_found', __('Package not found.', 'umh'), ['status' => 404]);
    }

    $query = $wpdb->prepare("
        SELECT full_name, id_number, passport_number, phone, status, payment_status 
        FROM $jamaah_table 
        WHERE package_id = %d
    ", $package_id);
    
    $data = $wpdb->get_results($query, ARRAY_A);

    if ($data === false) {
        return new WP_Error('db_error', __('Database error.', 'umh'), ['status' => 500]);
    }

    // Generate HTML output
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Daftar Jemaah: <?php echo esc_html($package['package_name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            @media print {
                body { font-size: 10pt; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <button onclick="window.print()" class="no-print" style="padding: 10px 20px; margin: 10px 0;">Cetak</button>
        <h1>Daftar Jemaah</h1>
        <h2>Paket: <?php echo esc_html($package['package_name']); ?></h2>
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Nama Lengkap</th>
                    <th>No. KTP</th>
                    <th>No. Paspor</th>
                    <th>No. Telepon</th>
                    <th>Status</th>
                    <th>Pembayaran</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Tidak ada data jemaah.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo esc_html($row['full_name']); ?></td>
                            <td><?php echo esc_html($row['id_number']); ?></td>
                            <td><?php echo esc_html($row['passport_number']); ?></td>
                            <td><?php echo esc_html($row['phone']); ?></td>
                            <td><?php echo esc_html(ucfirst($row['status'])); ?></td>
                            <td><?php echo esc_html(ucfirst($row['payment_status'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Menggunakan WP_REST_Response untuk mengirim HTML
    $response = new WP_REST_Response($html, 200);
    $response->header('Content-Type', 'text/html');
    return $response;
}