<?php
require_once __DIR__ . '/koneksi.php';
require __DIR__ . '/index.xhtml';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

// Mengambil identitas terbaru dari database.
try {
    $query = $pdo->prepare(
        'SELECT id, nama, email, dibuat_pada
         FROM pengguna
         WHERE id = :id'
    );

    $query->execute(['id' => $_SESSION['user_id']]);
    $pengguna = $query->fetch();

    if (!$pengguna) {
        unset($_SESSION['user_id']);
        header('Location: login.php');
        exit;
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Data pengguna belum dapat dimuat. Coba lagi nanti.');
}

require __DIR__ . '/index.html';