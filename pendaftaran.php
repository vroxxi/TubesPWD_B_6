<?php
require_once __DIR__ . '/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$nama = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim(input('nama'));
    $email = strtolower(trim(input('email')));
    $password = input('password');
    $konfirmasi = input('konfirmasi');

    if (!csrf_valid()) {
        $error = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } elseif ($nama === '' || strlen($nama) > 100) {
        $error = 'Nama wajib diisi dan maksimal 100 karakter.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150
    ) {
        $error = 'Masukkan alamat email yang valid.';
    } elseif (
        strlen($password) < 8
        || strlen($password) > 72
    ) {
        $error = 'Password harus sepanjang 8–72 byte. '
            . 'Untuk huruf dan angka biasa, 1 karakter = 1 byte.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $localPart = strstr($email, '@', true);
            $username = strtolower(preg_replace('/[^a-z0-9]/', '', $localPart));

            if ($username === '') {
                $username = 'pengguna';
            }

            $username = substr($username, 0, 40);
            $baseUsername = $username;
            $counter = 1;

            $cekUsername = $pdo->prepare(
                'SELECT 1 FROM pengguna WHERE username = :username LIMIT 1'
            );

            do {
                $cekUsername->execute(['username' => $username]);

                if (!$cekUsername->fetchColumn()) {
                    break;
                }

                $suffix = (string) $counter++;
                $username = substr($baseUsername, 0, 50 - strlen($suffix)) . $suffix;
            } while ($username !== '');

            $query = $pdo->prepare(
                'INSERT INTO pengguna
                    (nama_lengkap, username, email, password_hash)
                 VALUES
                    (:nama, :username, :email, :password)'
            );

            $query->execute([
                'nama' => $nama,
                'username' => $username,
                'email' => $email,
                'password' => $hash,
            ]);

            $_SESSION['pesan'] =
                'Pendaftaran berhasil. Silakan masuk.';

            header('Location: login.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                $error = 'Email sudah terdaftar. Gunakan email lain.';
            } else {
                error_log($exception->getMessage());
                $error = 'Pendaftaran gagal. Silakan coba lagi.';
            }
        }
    }
}

require __DIR__ . '/pendaftaran.xhtml';