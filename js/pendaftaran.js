document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("registerForm");
    var nama = document.getElementById("nama");
    var email = document.getElementById("email");
    var password = document.getElementById("password");
    var konfirmasi = document.getElementById("konfirmasi");

    form.addEventListener("submit", function (event) {
        var polaEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (nama.value.trim() === "") {
            alert("Nama lengkap wajib diisi.");
            nama.focus();
            event.preventDefault();
            return;
        }

        if (email.value.trim() === "") {
            alert("Email wajib diisi.");
            email.focus();
            event.preventDefault();
            return;
        }

        if (!polaEmail.test(email.value.trim())) {
            alert("Format email tidak valid.");
            email.focus();
            event.preventDefault();
            return;
        }

        if (password.value.length < 8) {
            alert("Password minimal 8 karakter.");
            password.focus();
            event.preventDefault();
            return;
        }

        if (konfirmasi.value === "") {
            alert("Konfirmasi password wajib diisi.");
            konfirmasi.focus();
            event.preventDefault();
            return;
        }

        if (password.value !== konfirmasi.value) {
            alert("Password dan konfirmasi password tidak sama.");
            konfirmasi.focus();
            event.preventDefault();
        }
    });
});