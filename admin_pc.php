<?php
require_once __DIR__ . '/admin_auth.php';

$adminUser = require_admin($pdo);
$errors = [];
$success = $_SESSION['admin_pc_success'] ?? '';
unset($_SESSION['admin_pc_success']);

$form = [
    'nomor_meja' => '',
    'spesifikasi' => '',
    'tarif_per_jam' => '',
    'status' => 'tersedia',
];
$editingId = 0;
$statuses = ['tersedia', 'dipakai', 'maintenance'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('aksi');
    $editingId = (int) input('id');
    $form = [
        'nomor_meja' => trim(input('nomor_meja')),
        'spesifikasi' => trim(input('spesifikasi')),
        'tarif_per_jam' => trim(input('tarif_per_jam')),
        'status' => input('status'),
    ];

    if (!csrf_valid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } elseif ($action === 'delete') {
        if ($editingId <= 0) {
            $errors[] = 'PC yang dipilih tidak valid.';
        } else {
            try {
                $query = $pdo->prepare('DELETE FROM meja_pc WHERE id_meja = :id');
                $query->execute(['id' => $editingId]);

                if ($query->rowCount() === 0) {
                    $errors[] = 'PC tidak ditemukan.';
                } else {
                    $_SESSION['admin_pc_success'] = 'Data PC berhasil dihapus.';
                    header('Location: admin_pc.php', true, 303);
                    exit;
                }
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                if (($exception->errorInfo[1] ?? null) === 1451) {
                    $errors[] = 'PC memiliki riwayat booking sehingga tidak dapat dihapus. Ubah statusnya menjadi maintenance.';
                } else {
                    $errors[] = 'Gagal menghapus data PC. Coba lagi.';
                }
            }
        }
    } elseif ($action === 'create' || $action === 'update') {
        if ($action === 'update' && $editingId <= 0) {
            $errors[] = 'PC yang dipilih tidak valid.';
        }
        if ($form['nomor_meja'] === '' || strlen($form['nomor_meja']) > 10) {
            $errors[] = 'Nomor PC wajib diisi dan maksimal 10 karakter.';
        }
        if ($form['spesifikasi'] === '') {
            $errors[] = 'Spesifikasi PC wajib diisi.';
        }
        if (
            !preg_match('/^\d+(?:\.\d{1,2})?$/', $form['tarif_per_jam'])
            || (float) $form['tarif_per_jam'] > 99999999.99
        ) {
            $errors[] = 'Tarif harus berupa angka non-negatif dengan maksimal 2 angka desimal.';
        }
        if (!in_array($form['status'], $statuses, true)) {
            $errors[] = 'Status PC tidak valid.';
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $query = $pdo->prepare(
                        'INSERT INTO meja_pc (nomor_meja, spesifikasi, tarif_per_jam, status)
                         VALUES (:nomor, :spesifikasi, :tarif, :status)'
                    );
                    $query->execute([
                        'nomor' => $form['nomor_meja'],
                        'spesifikasi' => $form['spesifikasi'],
                        'tarif' => $form['tarif_per_jam'],
                        'status' => $form['status'],
                    ]);
                    $_SESSION['admin_pc_success'] = 'PC berhasil ditambahkan.';
                } else {
                    $query = $pdo->prepare(
                        'UPDATE meja_pc
                         SET nomor_meja = :nomor,
                             spesifikasi = :spesifikasi,
                             tarif_per_jam = :tarif,
                             status = :status
                         WHERE id_meja = :id'
                    );
                    $query->execute([
                        'nomor' => $form['nomor_meja'],
                        'spesifikasi' => $form['spesifikasi'],
                        'tarif' => $form['tarif_per_jam'],
                        'status' => $form['status'],
                        'id' => $editingId,
                    ]);
                    $_SESSION['admin_pc_success'] = 'Data PC berhasil diperbarui.';
                }

                header('Location: admin_pc.php', true, 303);
                exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $errors[] = 'Nomor PC tersebut sudah digunakan.';
                } else {
                    $errors[] = 'Gagal menyimpan data PC. Coba lagi.';
                }
            }
        }
    } else {
        $errors[] = 'Aksi tidak dikenali.';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $editingId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if (!$editingId || $editingId < 1) {
        $errors[] = 'PC yang dipilih tidak valid.';
    } else {
        try {
            $query = $pdo->prepare(
                'SELECT nomor_meja, spesifikasi, tarif_per_jam, status
                 FROM meja_pc
                 WHERE id_meja = :id'
            );
            $query->execute(['id' => $editingId]);
            $pcToEdit = $query->fetch();

            if ($pcToEdit) {
                $form = $pcToEdit;
            } else {
                $editingId = 0;
                $errors[] = 'PC tidak ditemukan.';
            }
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Data PC gagal dimuat.';
        }
    }
}

try {
    $pcs = $pdo->query(
        'SELECT id_meja, nomor_meja, spesifikasi, tarif_per_jam, status
         FROM meja_pc
         ORDER BY id_meja DESC'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    $pcs = [];
    $errors[] = 'Daftar PC gagal dimuat.';
}

require __DIR__ . '/admin_pc.xhtml';
