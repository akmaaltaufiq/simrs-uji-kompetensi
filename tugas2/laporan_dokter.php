<?php
/**
 * Controller untuk endpoint laporan dokter
 */

require_once __DIR__ . '/DokterRepository.php';

header('Content-Type: application/json');

// hanya GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

// ambil parameter
$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : 0;
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : 0;

// validasi
if ($bulan < 1 || $bulan > 12) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Bulan harus 1-12'
    ]);
    exit;
}

if ($tahun < 2000 || $tahun > 2100) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Tahun tidak valid'
    ]);
    exit;
}

try {
    $repo = new DokterRepository();
    $data = $repo->getLaporanKunjungan($bulan, $tahun);

    // ✅ CASTING BIAR JSON BERSIH
    foreach ($data as &$row) {
        $row['total_kunjungan'] = (int) $row['total_kunjungan'];
        $row['total_pendapatan'] = (float) $row['total_pendapatan'];
        $row['rata_rata_kepuasan'] = (float) $row['rata_rata_kepuasan'];
    }

    echo json_encode([
        'status' => 'success',
        'bulan' => $bulan,
        'tahun' => $tahun,
        'data' => $data
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}