document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("loginForm");
    var email = document.getElementById("email");
    var password = document.getElementById("password");

    form.addEventListener("submit", function (event) {
        var polaEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

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

        if (password.value.trim() === "") {
            alert("Password wajib diisi.");
            password.focus();
            event.preventDefault();
        }
    });
});