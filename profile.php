<?php
require_once __DIR__ . '/koneksi.php';

/* 1. Proteksi Halaman: wajib login terlebih dahulu */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* Konstanta upload foto profil */
$uploadDir = __DIR__ . '/uploads/profiles/';
$uploadUrlPrefix = 'uploads/profiles/';
$defaultPhotoSrc = 'assets/images/default-avatar.svg';
$maxFileSize = 2 * 1024 * 1024; /* 2MB */
$allowedExtensions = ['jpg', 'jpeg', 'png'];

/* Helper cek apakah foto adalah foto default */
function is_default_photo(?string $foto): bool
{
    $foto = trim((string) $foto);
    return $foto === '' || $foto === 'default' || $foto === 'default.png' || $foto === 'default-avatar.svg';
}

/* Helper ambil path foto yang valid untuk ditampilkan */
function resolve_photo_src(?string $foto, string $uploadDir, string $uploadUrlPrefix, string $defaultPhotoSrc): string
{
    if (!is_default_photo($foto)) {
        $safeName = basename((string) $foto);
        $fullPath = $uploadDir . $safeName;
        if (file_exists($fullPath) && is_file($fullPath)) {
            return $uploadUrlPrefix . $safeName;
        }
    }
    return $defaultPhotoSrc;
}

/* Helper ambil inisial user untuk avatar topbar jika belum ada foto */
function get_user_initial(?string $namaLengkap, ?string $username): string
{
    $source = trim((string) $namaLengkap);
    if ($source === '') {
        $source = trim((string) $username);
    }
    if ($source === '') {
        return 'U';
    }
    return strtoupper(substr($source, 0, 1));
}

/* Helper format tampilan role */
function format_user_role(?string $role): string
{
    $role = trim((string) $role);
    if (strtolower($role) === 'pelanggan' || $role === '') {
        return 'Player';
    }
    return ucfirst($role);
}

/* 2. Read Data Profil pengguna yang sedang login dari tabel `pengguna` */
$userId = (int) $_SESSION['user_id'];

try {
    $queryUser = $pdo->prepare(
        'SELECT id_pengguna, nama_lengkap, username, email, no_hp, discord_tag, role, password_hash, foto
         FROM pengguna
         WHERE id_pengguna = :id
         LIMIT 1'
    );
    $queryUser->execute(['id' => $userId]);
    $pengguna = $queryUser->fetch();
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    exit('Data profil belum dapat dimuat. Pastikan kolom discord_tag dan foto sudah tersedia pada tabel pengguna.');
}

if (!$pengguna) {
    unset($_SESSION['user_id']);
    header('Location: login.php');
    exit;
}

/* Inisialisasi pesan notifikasi */
$errors = [];
$pesan = $_SESSION['pesan_profile'] ?? '';
unset($_SESSION['pesan_profile']);

if (isset($_SESSION['error_profile'])) {
    $errors[] = $_SESSION['error_profile'];
    unset($_SESSION['error_profile']);
}

/* Nilai awal form diambil dari data database */
$formNamaLengkap = (string) $pengguna['nama_lengkap'];
$formEmail = (string) $pengguna['email'];
$formNoHp = (string) ($pengguna['no_hp'] ?? '');
$formDiscordTag = (string) ($pengguna['discord_tag'] ?? '');

/* 3 & 4. Proses Form POST (Hapus Foto / Simpan Perubahan Profil & Password) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman.';
    } elseif (input('aksi') === 'hapus_foto') {
        /* Aksi 4: Hapus Foto Profil */
        if (is_default_photo($pengguna['foto'] ?? '')) {
            $_SESSION['error_profile'] = 'Foto profil saat ini sudah menggunakan foto default.';
            header('Location: profile.php', true, 303);
            exit;
        }

        $oldPhotoFile = $uploadDir . basename((string) $pengguna['foto']);

        try {
            $queryDel = $pdo->prepare(
                'UPDATE pengguna SET foto = :foto WHERE id_pengguna = :id'
            );
            $queryDel->execute([
                'foto' => 'default',
                'id' => $userId,
            ]);

            if (file_exists($oldPhotoFile) && is_file($oldPhotoFile)) {
                @unlink($oldPhotoFile);
            }

            $_SESSION['pesan_profile'] = 'Foto profil berhasil dihapus dan dikembalikan ke foto default.';
            header('Location: profile.php', true, 303);
            exit;
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            $errors[] = 'Gagal menghapus foto profil. Silakan coba lagi.';
        }
    } elseif (input('aksi') === 'simpan_profil') {
        /* Aksi 3: Update Profil, Upload Foto, & Ganti Password */
        $formNamaLengkap = trim(input('full_name'));
        $formEmail = strtolower(trim(input('email')));
        $formNoHp = trim(input('phone'));
        $formDiscordTag = trim(input('discord_tag'));

        $oldPassword = input('old_password');
        $newPassword = input('new_password');
        $confirmPassword = input('confirm_password');

        /* Validasi Nama Lengkap */
        if ($formNamaLengkap === '') {
            $errors[] = 'Nama Lengkap tidak boleh kosong.';
        } elseif (strlen($formNamaLengkap) > 100) {
            $errors[] = 'Nama Lengkap maksimal 100 karakter.';
        }

        /* Validasi Email & Cek Bentrok Email User Lain */
        if ($formEmail === '') {
            $errors[] = 'Email tidak boleh kosong.';
        } elseif (!filter_var($formEmail, FILTER_VALIDATE_EMAIL) || strlen($formEmail) > 100) {
            $errors[] = 'Format Email tidak valid.';
        } else {
            try {
                $cekEmail = $pdo->prepare(
                    'SELECT id_pengguna FROM pengguna WHERE email = :email AND id_pengguna != :id LIMIT 1'
                );
                $cekEmail->execute([
                    'email' => $formEmail,
                    'id' => $userId,
                ]);
                if ($cekEmail->fetch()) {
                    $errors[] = 'Email tersebut sudah digunakan oleh pengguna lain.';
                }
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $errors[] = 'Gagal memverifikasi ketersediaan email.';
            }
        }

        /* Validasi ringan No. Telepon & Discord Tag */
        if ($formNoHp !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $formNoHp)) {
            $errors[] = 'Format No. Telepon tidak valid (gunakan angka, spasi, +, atau -).';
        }
        if (strlen($formDiscordTag) > 50) {
            $errors[] = 'Discord Tag maksimal 50 karakter.';
        }

        /* Validasi Ganti Password (Opsional: hanya diproses jika Password Baru/Konfirmasi diisi) */
        $updatePassword = false;
        $newPasswordHash = '';

        if ($newPassword !== '' || $confirmPassword !== '') {
            if ($oldPassword === '') {
                $errors[] = 'Password Lama wajib diisi untuk mengubah password.';
            } elseif (!password_verify($oldPassword, (string) $pengguna['password_hash'])) {
                $errors[] = 'Password Lama yang Anda masukkan tidak sesuai.';
            }

            if ($newPassword === '') {
                $errors[] = 'Password Baru tidak boleh kosong.';
            } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
                $errors[] = 'Password Baru harus sepanjang 8-72 karakter.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Password Baru dan Konfirmasi Password tidak cocok.';
            }

            if (empty($errors)) {
                $updatePassword = true;
                $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            }
        }

        /* Validasi Upload Foto Baru (Opsional) */
        $hasNewPhoto = false;
        $newPhotoFilename = (string) ($pengguna['foto'] ?? 'default');

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadError = $_FILES['photo']['error'];

            if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                $errors[] = 'Ukuran file foto melebihi batas maksimal 2MB.';
            } elseif ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = 'Terjadi kesalahan saat mengunggah file foto.';
            } else {
                $fileTmp = $_FILES['photo']['tmp_name'];
                $fileName = $_FILES['photo']['name'];
                $fileSize = (int) $_FILES['photo']['size'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($fileExt, $allowedExtensions, true)) {
                    $errors[] = 'Format foto hanya boleh JPG, JPEG, atau PNG.';
                }

                if ($fileSize > $maxFileSize) {
                    $errors[] = 'Ukuran file foto maksimal 2MB.';
                }

                $imageInfo = @getimagesize($fileTmp);
                if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                    $errors[] = 'File yang diunggah bukan gambar JPG/PNG yang valid.';
                }

                if (empty($errors)) {
                    $hasNewPhoto = true;
                    $newPhotoFilename = 'profile_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                }
            }
        }

        /* Simpan file & update database jika semua validasi lolos */
        if (empty($errors)) {
            $movedNewFile = false;
            $targetFilePath = $uploadDir . $newPhotoFilename;

            if ($hasNewPhoto) {
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFilePath)) {
                    $movedNewFile = true;
                } else {
                    $errors[] = 'Gagal menyimpan file foto ke folder uploads/profiles/.';
                }
            }

            if (empty($errors)) {
                try {
                    if ($updatePassword) {
                        $queryUpdate = $pdo->prepare(
                            'UPDATE pengguna
                             SET nama_lengkap = :nama,
                                 email = :email,
                                 no_hp = :no_hp,
                                 discord_tag = :discord_tag,
                                 foto = :foto,
                                 password_hash = :password_hash
                             WHERE id_pengguna = :id'
                        );
                        $queryUpdate->execute([
                            'nama' => $formNamaLengkap,
                            'email' => $formEmail,
                            'no_hp' => $formNoHp !== '' ? $formNoHp : null,
                            'discord_tag' => $formDiscordTag !== '' ? $formDiscordTag : null,
                            'foto' => $newPhotoFilename,
                            'password_hash' => $newPasswordHash,
                            'id' => $userId,
                        ]);
                    } else {
                        $queryUpdate = $pdo->prepare(
                            'UPDATE pengguna
                             SET nama_lengkap = :nama,
                                 email = :email,
                                 no_hp = :no_hp,
                                 discord_tag = :discord_tag,
                                 foto = :foto
                             WHERE id_pengguna = :id'
                        );
                        $queryUpdate->execute([
                            'nama' => $formNamaLengkap,
                            'email' => $formEmail,
                            'no_hp' => $formNoHp !== '' ? $formNoHp : null,
                            'discord_tag' => $formDiscordTag !== '' ? $formDiscordTag : null,
                            'foto' => $newPhotoFilename,
                            'id' => $userId,
                        ]);
                    }

                    /* Hapus foto lama jika user mengunggah foto baru dan foto lama bukan default */
                    if ($hasNewPhoto && !is_default_photo($pengguna['foto'] ?? '')) {
                        $oldFilePath = $uploadDir . basename((string) $pengguna['foto']);
                        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
                            @unlink($oldFilePath);
                        }
                    }

                    if ($updatePassword && $hasNewPhoto) {
                        $_SESSION['pesan_profile'] = 'Profil, foto profil, dan password berhasil diperbarui.';
                    } elseif ($updatePassword) {
                        $_SESSION['pesan_profile'] = 'Profil dan password berhasil diperbarui.';
                    } elseif ($hasNewPhoto) {
                        $_SESSION['pesan_profile'] = 'Profil dan foto profil berhasil diperbarui.';
                    } else {
                        $_SESSION['pesan_profile'] = 'Perubahan profil berhasil disimpan.';
                    }

                    header('Location: profile.php', true, 303);
                    exit;
                } catch (PDOException $exception) {
                    if ($movedNewFile && file_exists($targetFilePath)) {
                        @unlink($targetFilePath);
                    }
                    error_log($exception->getMessage());
                    if (($exception->errorInfo[1] ?? null) === 1062) {
                        $errors[] = 'Email sudah digunakan oleh akun lain.';
                    } else {
                        $errors[] = 'Gagal memperbarui profil. Silakan coba lagi.';
                    }
                }
            }
        }
    }
}

/* Siapkan variabel tampilan untuk profile.xhtml */
$isAdmin = (strtolower(trim((string) ($pengguna['role'] ?? 'pelanggan'))) === 'admin');
$photoSrc = resolve_photo_src($pengguna['foto'] ?? '', $uploadDir, $uploadUrlPrefix, $defaultPhotoSrc);
$hasCustomPhoto = ($photoSrc !== $defaultPhotoSrc);
$userInitial = get_user_initial($pengguna['nama_lengkap'] ?? '', $pengguna['username'] ?? '');
$userRoleDisplay = format_user_role($pengguna['role'] ?? 'pelanggan');

require __DIR__ . '/profile.xhtml';
