<?php
require_once __DIR__ . '/koneksi.php';

function require_admin(PDO $pdo): array
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php', true, 303);
        exit;
    }

    try {
        $query = $pdo->prepare(
            'SELECT id_pengguna, nama_lengkap, username, role
             FROM pengguna
             WHERE id_pengguna = :id
             LIMIT 1'
        );
        $query->execute(['id' => (int) $_SESSION['user_id']]);
        $admin = $query->fetch();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        http_response_code(500);
        exit('Gagal memverifikasi akses admin. Coba lagi nanti.');
    }

    if (!$admin) {
        unset($_SESSION['user_id']);
        header('Location: login.php', true, 303);
        exit;
    }

    if (strtolower(trim((string) $admin['role'])) !== 'admin') {
        http_response_code(403);
        exit('Halaman ini hanya dapat diakses oleh admin.');
    }

    return $admin;
}
