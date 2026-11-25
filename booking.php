<?php
require_once __DIR__ . '/koneksi.php';

/* 1. Proteksi Halaman: wajib login terlebih dahulu */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

if (isset($_SESSION['booking_success'])) {
    $successMsg = $_SESSION['booking_success'];
    unset($_SESSION['booking_success']);
}
if (isset($_SESSION['booking_error'])) {
    $errorMsg = $_SESSION['booking_error'];
    unset($_SESSION['booking_error']);
}

/* 2. Ambil Profil User yang sedang Login untuk Topbar */
$pengguna = null;
$photoSrc = 'assets/images/default-avatar.svg';
$hasCustomPhoto = false;
$userInitial = 'U';
$userRoleDisplay = 'Player';
$isAdmin = false;

try {
    $stmtUser = $pdo->prepare('SELECT id_pengguna, nama_lengkap, username, email, role, foto FROM pengguna WHERE id_pengguna = :id LIMIT 1');
    $stmtUser->execute(['id' => $userId]);
    $pengguna = $stmtUser->fetch();
} catch (PDOException $e) {
    // Fallback jika kolom foto belum ditambahkan pada tabel pengguna
    try {
        $stmtUser = $pdo->prepare('SELECT id_pengguna, nama_lengkap, username, email, role FROM pengguna WHERE id_pengguna = :id LIMIT 1');
        $stmtUser->execute(['id' => $userId]);
        $pengguna = $stmtUser->fetch();
    } catch (PDOException $e2) {
        error_log($e2->getMessage());
    }
}

if ($pengguna) {
    $userRole = strtolower(trim((string) ($pengguna['role'] ?? 'pelanggan')));
    $isAdmin = ($userRole === 'admin');
    $userRoleDisplay = ($userRole === 'pelanggan' || $userRole === '') ? 'Player' : ucfirst($userRole);

    $fotoDb = trim((string) ($pengguna['foto'] ?? ''));
    if ($fotoDb !== '' && $fotoDb !== 'default' && $fotoDb !== 'default.png' && $fotoDb !== 'default-avatar.svg') {
        $safeName = basename($fotoDb);
        $fullPath = __DIR__ . '/uploads/profiles/' . $safeName;
        if (file_exists($fullPath) && is_file($fullPath)) {
            $photoSrc = 'uploads/profiles/' . $safeName;
            $hasCustomPhoto = true;
        }
    }

    $sourceName = trim((string) ($pengguna['nama_lengkap'] ?? ''));
    if ($sourceName === '') {
        $sourceName = trim((string) ($pengguna['username'] ?? ''));
    }
    if ($sourceName !== '') {
        $userInitial = strtoupper(substr($sourceName, 0, 1));
    }
}

/* 3. Handle POST: Konfirmasi Booking dari Member */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input('action') === 'book_pc') {
    if (!csrf_valid()) {
        $_SESSION['booking_error'] = 'Token keamanan formulir tidak valid. Muat ulang halaman.';
        header('Location: booking.php', true, 303);
        exit;
    }

    $pcId = (int) input('pc_id');
    $tglBooking = trim(input('booking_date'));
    $jamMulai = (int) input('start_hour');
    $durasi = (int) input('duration');
    $isNight = (input('is_night') === '1');

    if (
        $pcId <= 0
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglBooking)
        || $jamMulai < 0
        || $jamMulai > 23
        || !in_array($durasi, [1, 2, 3, 6], true)
    ) {
        $_SESSION['booking_error'] = 'Data pemesanan tidak lengkap atau tidak valid.';
        header('Location: booking.php', true, 303);
        exit;
    }

    $waktuMulai = sprintf('%s %02d:00:00', $tglBooking, $jamMulai);
    $startTs = strtotime($waktuMulai);
    if ($startTs === false) {
        $_SESSION['booking_error'] = 'Format tanggal booking tidak valid.';
        header('Location: booking.php', true, 303);
        exit;
    }
    $waktuSelesai = date('Y-m-d H:i:s', $startTs + ($durasi * 3600));

    try {
        $pdo->beginTransaction();

        // Lock record PC untuk mencegah double booking bersamaan
        $stmtCheck = $pdo->prepare('SELECT id_meja, nomor_meja, tarif_per_jam, status FROM meja_pc WHERE id_meja = :id FOR UPDATE');
        $stmtCheck->execute(['id' => $pcId]);
        $pcData = $stmtCheck->fetch();

        if (!$pcData) {
            $pdo->rollBack();
            $_SESSION['booking_error'] = 'Unit PC tidak ditemukan.';
            header('Location: booking.php', true, 303);
            exit;
        }

        if ($pcData['status'] !== 'tersedia') {
            $pdo->rollBack();
            $_SESSION['booking_error'] = 'Maaf, ' . $pcData['nomor_meja'] . ' saat ini sudah tidak tersedia (' . $pcData['status'] . ').';
            header('Location: booking.php', true, 303);
            exit;
        }

        // Hitung total biaya di sisi server agar aman dari manipulasi client
        $tarifPc = (float) $pcData['tarif_per_jam'];
        $totalBiaya = ($isNight && $durasi === 6) ? 30000.0 : ($tarifPc * $durasi);

        // Generate kode booking unik
        $kodeBooking = 'BK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Cek kolom pada tabel booking agar kompatibel dengan kedua versi SQL
        $bookingCols = $pdo->query('SHOW COLUMNS FROM booking')->fetchAll(PDO::FETCH_COLUMN);
        $hasKodeBooking = in_array('kode_booking', $bookingCols, true);
        $hasTarifCol = in_array('tarif_per_jam', $bookingCols, true);

        // 1. Simpan ke tabel booking
        if ($hasKodeBooking && $hasTarifCol) {
            $stmtBook = $pdo->prepare(
                'INSERT INTO booking (kode_booking, id_pengguna, id_meja, waktu_mulai, waktu_selesai, tarif_per_jam, total_biaya, status)
                 VALUES (:kode, :id_user, :id_meja, :mulai, :selesai, :tarif, :total, "dipesan")'
            );
            $stmtBook->execute([
                'kode' => $kodeBooking,
                'id_user' => $userId,
                'id_meja' => $pcId,
                'mulai' => $waktuMulai,
                'selesai' => $waktuSelesai,
                'tarif' => $pcData['tarif_per_jam'],
                'total' => $totalBiaya
            ]);
        } else {
            $stmtBook = $pdo->prepare(
                'INSERT INTO booking (id_pengguna, id_meja, waktu_mulai, waktu_selesai, total_biaya, status)
                 VALUES (:id_user, :id_meja, :mulai, :selesai, :total, "dipesan")'
            );
            $stmtBook->execute([
                'id_user' => $userId,
                'id_meja' => $pcId,
                'mulai' => $waktuMulai,
                'selesai' => $waktuSelesai,
                'total' => $totalBiaya
            ]);
        }

        // 2. Ubah status PC di tabel meja_pc menjadi 'dipakai'
        $stmtUpdPc = $pdo->prepare('UPDATE meja_pc SET status = "dipakai" WHERE id_meja = :id');
        $stmtUpdPc->execute(['id' => $pcId]);

        $pdo->commit();

        $_SESSION['booking_success'] = 'Booking ' . $pcData['nomor_meja'] . ' berhasil dikonfirmasi! Kode Booking: ' . $kodeBooking;
        header('Location: booking.php', true, 303);
        exit;
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        $_SESSION['booking_error'] = 'Terjadi kesalahan sistem saat memproses booking. Coba lagi.';
        header('Location: booking.php', true, 303);
        exit;
    }
}

/* 4. Ambil Daftar Semua PC dari Database untuk Dirender ke Peta/Denah */
$computers = [];
try {
    $stmtPcs = $pdo->query('SELECT id_meja, nomor_meja, kategori, tarif_per_jam, status FROM meja_pc ORDER BY id_meja ASC');
    $computers = $stmtPcs->fetchAll();
} catch (PDOException $e) {
    // Fallback jika tabel meja_pc belum memiliki kolom kategori (versi SQL lama)
    try {
        $stmtPcs = $pdo->query('SELECT id_meja, nomor_meja, tarif_per_jam, status FROM meja_pc ORDER BY id_meja ASC');
        $computers = $stmtPcs->fetchAll();
    } catch (PDOException $e2) {
        error_log($e2->getMessage());
    }
}

// Konversi data PC untuk JavaScript client-side
$jsPcs = [];
foreach ($computers as $pc) {
    // Normalisasi status ke format: available, booked, maintenance
    $statusNorm = 'available';
    if ($pc['status'] === 'dipakai' || $pc['status'] === 'booked') {
        $statusNorm = 'booked';
    } elseif ($pc['status'] === 'maintenance') {
        $statusNorm = 'maintenance';
    }

    // Tentukan kategori dari kolom DB atau fallback berdasarkan id_meja / tarif
    $idMeja = (int) $pc['id_meja'];
    $tarif = (float) $pc['tarif_per_jam'];
    if (isset($pc['kategori']) && trim((string) $pc['kategori']) !== '') {
        $katRaw = strtolower(trim((string) $pc['kategori']));
        $typeLabel = ($katRaw === 'vip') ? 'VIP' : (($katRaw === 'vvip') ? 'VVIP' : 'Reguler');
    } else {
        if ($tarif >= 12000 || $idMeja >= 11) {
            $typeLabel = 'VVIP';
        } elseif ($tarif >= 8000 || $idMeja >= 7) {
            $typeLabel = 'VIP';
        } else {
            $typeLabel = 'Reguler';
        }
    }

    $jsPcs[] = [
        'id' => $idMeja,
        'name' => (string) $pc['nomor_meja'],
        'type' => $typeLabel,
        'rate' => $tarif,
        'status' => $statusNorm
    ];
}

$todayDate = date('Y-m-d');

require __DIR__ . '/booking.xhtml';
