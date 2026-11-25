/* =========================================================
 * Smart Warnet Portal - Interaktivitas & Validasi Profile
 * Vanilla JavaScript Murni (selaras dengan js/login.js & js/pendaftaran.js)
 * ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("profileForm");
    var deletePhotoForm = document.getElementById("deletePhotoForm");
    var photoInput = document.getElementById("photo");
    var profilePreview = document.getElementById("profilePreview");
    var selectedFileInfo = document.getElementById("selectedFileInfo");
    var btnDeletePhoto = document.getElementById("btnDeletePhoto");
    var btnCancel = document.getElementById("btnCancel");

    var fullNameInput = document.getElementById("full_name");
    var emailInput = document.getElementById("email");
    var phoneInput = document.getElementById("phone");
    var discordInput = document.getElementById("discord_tag");

    var oldPassInput = document.getElementById("old_password");
    var newPassInput = document.getElementById("new_password");
    var confirmPassInput = document.getElementById("confirm_password");
    var passwordFeedback = document.getElementById("passwordMatchFeedback");
    var clientAlert = document.getElementById("clientAlert");

    if (!form) {
        return;
    }

    var originalPhotoSrc = profilePreview ? profilePreview.src : "";

    /* Pasang placeholder via JS agar markup XHTML 1.0 Strict tetap valid */
    if (fullNameInput) {
        fullNameInput.setAttribute("placeholder", "nama lengkap");
    }
    if (emailInput) {
        emailInput.setAttribute("placeholder", "username@gmail.com");
    }
    if (phoneInput) {
        phoneInput.setAttribute("placeholder", "0812-3456-7890");
    }
    if (discordInput) {
        discordInput.setAttribute("placeholder", "user#tag");
    }
    if (oldPassInput) {
        oldPassInput.setAttribute("placeholder", "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022");
    }
    if (newPassInput) {
        newPassInput.setAttribute("placeholder", "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022");
    }
    if (confirmPassInput) {
        confirmPassInput.setAttribute("placeholder", "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022");
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function showClientErrors(messages) {
        if (!clientAlert) {
            return;
        }
        var html = "<ul class=\"profile-alert-list\">";
        var i;
        for (i = 0; i < messages.length; i++) {
            html += "<li>" + escapeHtml(messages[i]) + "</li>";
        }
        html += "</ul>";
        clientAlert.innerHTML = html;
        clientAlert.className = "profile-alert profile-alert-error";
        window.scrollTo(0, 0);
    }

    function hideClientErrors() {
        if (!clientAlert) {
            return;
        }
        clientAlert.innerHTML = "";
        clientAlert.className = "profile-alert profile-alert-error profile-alert-hidden";
    }

    function setInputError(el, isError) {
        if (!el) {
            return;
        }
        el.className = isError ? "form-input input-error" : "form-input";
    }

    /* 1. Live preview foto profil sebelum Simpan Perubahan diklik */
    if (photoInput && profilePreview) {
        photoInput.addEventListener("change", function () {
            hideClientErrors();

            if (!photoInput.files || photoInput.files.length === 0) {
                profilePreview.src = originalPhotoSrc;
                if (selectedFileInfo) {
                    selectedFileInfo.innerHTML = "";
                }
                return;
            }

            var file = photoInput.files[0];
            var fileName = file.name;
            var fileSize = file.size;
            var maxBytes = 2 * 1024 * 1024;
            var extPattern = /\.(jpg|jpeg|png)$/i;
            var fileErrors = [];

            if (!extPattern.test(fileName)) {
                fileErrors.push("Format foto harus berupa JPG, JPEG, atau PNG.");
            }
            if (fileSize > maxBytes) {
                fileErrors.push("Ukuran file foto maksimal adalah 2MB.");
            }

            if (fileErrors.length > 0) {
                showClientErrors(fileErrors);
                photoInput.value = "";
                profilePreview.src = originalPhotoSrc;
                if (selectedFileInfo) {
                    selectedFileInfo.innerHTML = "";
                }
                return;
            }

            if (typeof FileReader !== "undefined") {
                var reader = new FileReader();
                reader.onload = function (event) {
                    profilePreview.src = event.target.result;
                };
                reader.readAsDataURL(file);
            }

            if (selectedFileInfo) {
                selectedFileInfo.innerHTML = "Dipilih: " + escapeHtml(fileName) + " (klik Simpan Perubahan)";
            }
        });
    }

    /* 2. Validasi real-time kecocokan Password Baru & Konfirmasi Password */
    function checkPasswordMatchLive() {
        if (!newPassInput || !confirmPassInput || !passwordFeedback) {
            return;
        }

        var newPass = newPassInput.value;
        var confirmPass = confirmPassInput.value;

        if (newPass === "" && confirmPass === "") {
            passwordFeedback.innerHTML = "";
            passwordFeedback.className = "field-feedback";
            setInputError(confirmPassInput, false);
            return;
        }

        if (confirmPass === "") {
            passwordFeedback.innerHTML = "Masukkan Konfirmasi Password yang sama.";
            passwordFeedback.className = "field-feedback feedback-error";
            setInputError(confirmPassInput, true);
            return;
        }

        if (newPass !== confirmPass) {
            passwordFeedback.innerHTML = "Konfirmasi Password tidak cocok dengan Password Baru.";
            passwordFeedback.className = "field-feedback feedback-error";
            setInputError(confirmPassInput, true);
        } else {
            passwordFeedback.innerHTML = "Konfirmasi Password cocok.";
            passwordFeedback.className = "field-feedback feedback-ok";
            setInputError(confirmPassInput, false);
        }
    }

    if (newPassInput) {
        newPassInput.addEventListener("keyup", checkPasswordMatchLive);
    }
    if (confirmPassInput) {
        confirmPassInput.addEventListener("keyup", checkPasswordMatchLive);
    }

    /* 3. Validasi form saat submit ("Simpan Perubahan") */
    form.addEventListener("submit", function (event) {
        var errors = [];
        var fullName = fullNameInput ? fullNameInput.value.trim() : "";
        var email = emailInput ? emailInput.value.trim() : "";
        var oldPass = oldPassInput ? oldPassInput.value : "";
        var newPass = newPassInput ? newPassInput.value : "";
        var confirmPass = confirmPassInput ? confirmPassInput.value : "";
        var polaEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        setInputError(fullNameInput, false);
        setInputError(emailInput, false);
        setInputError(oldPassInput, false);
        setInputError(newPassInput, false);
        setInputError(confirmPassInput, false);

        if (fullName === "") {
            errors.push("Nama Lengkap tidak boleh kosong.");
            setInputError(fullNameInput, true);
        }

        if (email === "") {
            errors.push("Email tidak boleh kosong.");
            setInputError(emailInput, true);
        } else if (!polaEmail.test(email)) {
            errors.push("Format Email tidak valid.");
            setInputError(emailInput, true);
        }

        if (newPass !== "" || confirmPass !== "") {
            if (oldPass === "") {
                errors.push("Password Lama wajib diisi untuk mengubah password.");
                setInputError(oldPassInput, true);
            }

            if (newPass === "") {
                errors.push("Password Baru tidak boleh kosong.");
                setInputError(newPassInput, true);
            } else if (newPass.length < 8 || newPass.length > 72) {
                errors.push("Password Baru harus sepanjang 8-72 karakter.");
                setInputError(newPassInput, true);
            }

            if (newPass !== confirmPass) {
                errors.push("Password Baru dan Konfirmasi Password tidak cocok.");
                setInputError(confirmPassInput, true);
            }
        }

        if (errors.length > 0) {
            showClientErrors(errors);
            event.preventDefault();
            return;
        }

        hideClientErrors();
    });

    /* 4. Konfirmasi & submit form terpisah saat tombol Hapus ditekan */
    if (btnDeletePhoto && deletePhotoForm) {
        btnDeletePhoto.addEventListener("click", function () {
            var yakin = confirm("Apakah Anda yakin ingin menghapus foto profil dan mengembalikannya ke foto default?");
            if (yakin) {
                deletePhotoForm.submit();
            }
        });
    }

    /* 5. Reset preview & pesan error saat tombol Batal ditekan */
    if (btnCancel) {
        btnCancel.addEventListener("click", function () {
            hideClientErrors();

            if (profilePreview) {
                profilePreview.src = originalPhotoSrc;
            }
            if (selectedFileInfo) {
                selectedFileInfo.innerHTML = "";
            }
            if (passwordFeedback) {
                passwordFeedback.innerHTML = "";
                passwordFeedback.className = "field-feedback";
            }

            setInputError(fullNameInput, false);
            setInputError(emailInput, false);
            setInputError(oldPassInput, false);
            setInputError(newPassInput, false);
            setInputError(confirmPassInput, false);
        });
    }
});
