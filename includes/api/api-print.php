<?php
// File: includes/api/api-print.php
// API untuk generate HTML Kwitansi/Invoice

if (!defined('ABSPATH')) exit;

/**
 * Menampilkan HTML kwitansi untuk di-print.
 * Endpoint: GET /umroh/v1/print/invoice
 */
function umroh_print_invoice(WP_REST_Request $request) {
    global $wpdb;
    
    // Ambil ID jemaah dari parameter URL
    $jemaah_id = $request->get_param('id');
    if (empty($jemaah_id)) {
        return new WP_Error('no_id', 'ID Jemaah dibutuhkan', ['status' => 400]);
    }

    // Ambil data Jemaah
    $jemaah = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT m.*, p.title as package_name 
             FROM {$wpdb->prefix}umroh_manifest m
             LEFT JOIN {$wpdb->prefix}uhp_packages p ON m.package_id = p.id
             WHERE m.id = %d",
            $jemaah_id
        )
    );

    if (empty($jemaah)) {
        return new WP_Error('not_found', 'Data Jemaah tidak ditemukan', ['status' => 404]);
    }

    // Ambil riwayat pembayaran
    $payments = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}umroh_payments WHERE manifest_id = %d ORDER BY date ASC",
            $jemaah_id
        )
    );

    // Hitung total bayar dan sisa
    $total_paid = 0;
    foreach ($payments as $payment) {
        $total_paid += (float)$payment->amount;
    }
    $total_price = (float)$jemaah->final_price;
    $remaining = $total_price - $total_paid;

    // --- Mulai Tampilan HTML ---
    // Kita tidak mengembalikan JSON, tapi langsung HTML
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Kwitansi Pembayaran - <?php echo esc_html($jemaah->full_name); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
            body { font-family: 'Inter', sans-serif; }
            @media print {
                body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body class="bg-gray-100 p-8">
        <div class="max-w-2xl mx-auto bg-white shadow-xl rounded-lg">
            <header class="bg-indigo-600 text-white p-8 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold">KWITANSI</h1>
                        <p class="text-indigo-200">No. Inv: <?php echo esc_html(str_pad($jemaah->id, 4, '0', STR_PAD_LEFT) . '/' . date('Y')); ?></p>
                    </div>
                    <div class="text-right">
                        <!-- Ganti dengan logo travel Anda -->
                        <div class="text-2xl font-bold">TRAVEL ANDA</div>
                        <p class="text-sm text-indigo-200">Jl. Contoh Alamat No. 123</p>
                    </div>
                </div>
            </header>

            <main class="p-8">
                <div class="grid grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Telah Diterima Dari:</h3>
                        <p class="text-lg font-semibold text-gray-900 mt-1"><?php echo esc_html($jemaah->full_name); ?></p>
                        <p class="text-gray-600"><?php echo esc_html($jemaah->passport_number); ?></p>
                    </div>
                    <div class="text-right">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Tanggal Cetak:</h3>
                        <p class="text-lg font-semibold text-gray-900 mt-1"><?php echo date('d F Y'); ?></p>
                    </div>
                </div>

                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Detail Pembayaran:</h3>
                <div class="border rounded-lg overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Keterangan</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Nominal (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-center text-gray-500">Belum ada pembayaran</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $index => $payment): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm text-gray-700"><?php echo date('d/m/Y', strtotime($payment->date)); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-900">Pembayaran Cicilan ke-<?php echo $index + 1; ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-900 font-medium text-right"><?php echo number_format($payment->amount, 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 gap-8 mt-8">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Paket:</h3>
                        <p class="text-lg font-semibold text-gray-900 mt-1"><?php echo esc_html($jemaah->package_name); ?></p>
                    </div>
                    <div class="text-right">
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Total Harga Paket:</dt>
                                <dd class="text-sm font-medium text-gray-800">Rp <?php echo number_format($total_price, 0, ',', '.'); ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500">Total Telah Dibayar:</dt>
                                <dd class="text-sm font-medium text-gray-800">Rp <?php echo number_format($total_paid, 0, ',', '.'); ?></dd>
                            </div>
                            <div class="flex justify-between pt-2 border-t">
                                <dt class="text-lg font-bold text-gray-900">Sisa Tagihan:</dt>
                                <dd class="text-lg font-bold text-gray-900">Rp <?php echo number_format($remaining, 0, ',', '.'); ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="text-center mt-12 no-print">
                    <button onclick="window.print()" class="px-6 py-3 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Cetak Kwitansi
                    </button>
                </div>
            </main>
        </div>
    </body>
    </html>
    <?php
    exit; // Wajib ada untuk menghentikan eksekusi WP
}