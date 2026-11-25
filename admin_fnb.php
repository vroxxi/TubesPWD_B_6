<?php
require_once __DIR__ . '/admin_auth.php';

$adminUser = require_admin($pdo);
$errors = [];
$success = $_SESSION['admin_fnb_success'] ?? '';
unset($_SESSION['admin_fnb_success']);

$categories = ['makanan', 'minuman', 'snack'];
$form = [
    'nama_menu' => '',
    'kategori' => 'makanan',
    'harga' => '',
    'stok' => '0',
    'tersedia' => '1',
];
$editingId = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('aksi');
    $editingId = (int) input('id');
    $form = [
        'nama_menu' => trim(input('nama_menu')),
        'kategori' => input('kategori'),
        'harga' => trim(input('harga')),
        'stok' => trim(input('stok')),
        'tersedia' => input('tersedia'),
    ];

    if (!csrf_valid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } elseif ($action === 'delete') {
        if ($editingId <= 0) {
            $errors[] = 'Menu yang dipilih tidak valid.';
        } else {
            try {
                $query = $pdo->prepare('DELETE FROM menu WHERE id_menu = :id');
                $query->execute(['id' => $editingId]);

                if ($query->rowCount() === 0) {
                    $errors[] = 'Menu tidak ditemukan.';
                } else {
                    $_SESSION['admin_fnb_success'] = 'Menu berhasil dihapus.';
                    header('Location: admin_fnb.php', true, 303);
                    exit;
                }
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                if (($exception->errorInfo[1] ?? null) === 1451) {
                    $errors[] = 'Menu sudah digunakan pada pesanan dan tidak dapat dihapus. Nonaktifkan menu agar tidak dipesan lagi.';
                } else {
                    $errors[] = 'Gagal menghapus menu. Coba lagi.';
                }
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        if ($action === 'update' && $editingId <= 0) {
            $errors[] = 'Menu yang dipilih tidak valid.';
        }
        if ($form['nama_menu'] === '' || strlen($form['nama_menu']) > 100) {
            $errors[] = 'Nama menu wajib diisi dan maksimal 100 karakter.';
        }
        if (!in_array($form['kategori'], $categories, true)) {
            $errors[] = 'Kategori menu tidak valid.';
        }
        if (
            !preg_match('/^\d+(?:\.\d{1,2})?$/', $form['harga'])
            || (float) $form['harga'] > 99999999.99
        ) {
            $errors[] = 'Harga harus berupa angka non-negatif dengan maksimal 2 angka desimal.';
        }
        if (!preg_match('/^\d+$/', $form['stok']) || (float) $form['stok'] > 2147483647) {
            $errors[] = 'Stok harus berupa bilangan bulat positif.';
        }
        if (!in_array($form['tersedia'], ['0', '1'], true)) {
            $errors[] = 'Status ketersediaan tidak valid.';
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $query = $pdo->prepare(
                        'INSERT INTO menu (nama_menu, kategori, harga, stok, tersedia)
                         VALUES (:nama, :kategori, :harga, :stok, :tersedia)'
                    );
                    $query->execute([
                        'nama' => $form['nama_menu'],
                        'kategori' => $form['kategori'],
                        'harga' => $form['harga'],
                        'stok' => (int) $form['stok'],
                        'tersedia' => (int) $form['tersedia'],
                    ]);
                    $_SESSION['admin_fnb_success'] = 'Menu F&B berhasil ditambahkan.';
                } else {
                    $query = $pdo->prepare(
                        'UPDATE menu
                         SET nama_menu = :nama,
                             kategori = :kategori,
                             harga = :harga,
                             stok = :stok,
                             tersedia = :tersedia
                         WHERE id_menu = :id'
                    );
                    $query->execute([
                        'nama' => $form['nama_menu'],
                        'kategori' => $form['kategori'],
                        'harga' => $form['harga'],
                        'stok' => (int) $form['stok'],
                        'tersedia' => (int) $form['tersedia'],
                        'id' => $editingId,
                    ]);
                    $_SESSION['admin_fnb_success'] = 'Menu F&B berhasil diperbarui.';
                }

                header('Location: admin_fnb.php', true, 303);
                exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $errors[] = 'Gagal menyimpan menu. Coba lagi.';
            }
        }
    } else {
        $errors[] = 'Aksi tidak dikenali.';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $editingId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if (!$editingId || $editingId < 1) {
        $errors[] = 'Menu yang dipilih tidak valid.';
    } else {
        try {
            $query = $pdo->prepare(
                'SELECT nama_menu, kategori, harga, stok, tersedia
                 FROM menu
                 WHERE id_menu = :id'
            );
            $query->execute(['id' => $editingId]);
            $menuToEdit = $query->fetch();

            if ($menuToEdit) {
                $form = [
                    'nama_menu' => (string) $menuToEdit['nama_menu'],
                    'kategori' => (string) $menuToEdit['kategori'],
                    'harga' => (string) $menuToEdit['harga'],
                    'stok' => (string) $menuToEdit['stok'],
                    'tersedia' => (string) $menuToEdit['tersedia'],
                ];
            } else {
                $editingId = 0;
                $errors[] = 'Menu tidak ditemukan.';
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Data menu gagal dimuat.';
        }
    }
}

try {
    $menus = $pdo->query(
        'SELECT id_menu, nama_menu, kategori, harga, stok, tersedia
         FROM menu
         ORDER BY id_menu DESC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $menus = [];
    $errors[] = 'Daftar menu gagal dimuat.';
}

require __DIR__ . '/admin_fnb.xhtml';
