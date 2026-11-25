<?php
require_once __DIR__ . '/koneksi.php';

// Proses logout.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && input('aksi') === 'logout'
) {
    if (!csrf_valid()) {
        http_response_code(403);
        exit('Permintaan logout tidak valid. Muat ulang halaman.');
    }

    $_SESSION = [];

    $cookie = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'],
    ]);

    session_destroy();

    header('Location: login.php', true, 303);
    exit;
}

// Mengambil identitas terbaru dari database ketika pengguna sudah login.
$pengguna = null;
$hasCustomPhoto = false;
$photoSrc = 'assets/images/default-avatar.svg';
$userInitial = 'U';
$userRoleDisplay = 'Player';

if (isset($_SESSION['user_id'])) {
    try {
        $query = $pdo->prepare(
            'SELECT id_pengguna, nama_lengkap, username, email, role, foto, dibuat_pada
             FROM pengguna
             WHERE id_pengguna = :id'
        );

        $query->execute(['id' => $_SESSION['user_id']]);
        $pengguna = $query->fetch();

        if (!$pengguna) {
            unset($_SESSION['user_id']);
        } else {
            $fotoDb = trim((string) ($pengguna['foto'] ?? ''));
            if (
                $fotoDb !== ''
                && $fotoDb !== 'default'
                && $fotoDb !== 'default.png'
                && $fotoDb !== 'default-avatar.svg'
            ) {
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

            $roleDb = trim((string) ($pengguna['role'] ?? 'pelanggan'));
            $isAdmin = (strtolower($roleDb) === 'admin');
            $userRoleDisplay = (strtolower($roleDb) === 'pelanggan' || $roleDb === '')
                ? 'Player'
                : ucfirst($roleDb);
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        exit('Data pengguna belum dapat dimuat. Coba lagi nanti.');
    }
}

require __DIR__ . '/index.xhtml';