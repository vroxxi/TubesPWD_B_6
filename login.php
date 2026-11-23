<?php
require_once __DIR__ . '/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';
$pesan = $_SESSION['pesan'] ?? '';

unset($_SESSION['pesan']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(input('email')));
    $password = input('password');

    if (!csrf_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === ''
    ) {
        $error = 'Isi email dan password dengan benar.';
    } else {
        try {
            $query = $pdo->prepare(
                'SELECT id, nama, email, password
                 FROM pengguna
                 WHERE email = :email
                 LIMIT 1'
            );

            $query->execute(['email' => $email]);
            $pengguna = $query->fetch();

            if (
                $pengguna
                && password_verify($password, $pengguna['password'])
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $pengguna['id'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));

                header('Location: index.php', true, 303);
                exit;
            }

            $error = 'Email atau password salah.';
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $error = 'Login sedang bermasalah. Silakan coba lagi.';
        }
    }
}

require __DIR__ . '/login.xhtml';