/* =========================================================
 * Smart Warnet Portal - Logika Client-Side Booking PC
 * Vanilla JavaScript Murni (tanpa framework / library)
 * ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
    var rawComputers = window.SERVER_PCS || [];
    var nightPrice = 30000;

    var state = {
        category: "Semua",
        selectedId: null,
        selectedPc: null,
        date: "",
        startHour: 20,
        hours: 3,
        isNight: false
    };

    var dateInput = document.getElementById("bookingDate");
    var startHourSelect = document.getElementById("startHour");
    var endHourSelect = document.getElementById("endHour");
    var mapContainer = document.getElementById("pcMapGrid");
    var confirmBtn = document.getElementById("btnConfirmBooking");
    var formEl = document.getElementById("bookingForm");

    // Hidden inputs untuk submit form PHP
    var hiddenPcId = document.getElementById("hidden_pc_id");
    var hiddenDate = document.getElementById("hidden_date");
    var hiddenStart = document.getElementById("hidden_start_hour");
    var hiddenDuration = document.getElementById("hidden_duration");
    var hiddenIsNight = document.getElementById("hidden_is_night");

    // Elemen Ringkasan
    var sPc = document.getElementById("sPc");
    var sTime = document.getElementById("sTime");
    var sRate = document.getElementById("sRate");
    var sTotal = document.getElementById("sTotal");

    function pad(n) {
        return String(n % 24).padStart(2, "0");
    }

    function formatRupiah(num) {
        return "Rp " + Number(num).toLocaleString("id-ID");
    }

    // Upgrade input tanggal ke date picker via JS agar markup XHTML 1.0 tetap valid
    if (dateInput) {
        try {
            dateInput.setAttribute("type", "date");
            if (window.TODAY_DATE) {
                dateInput.setAttribute("min", window.TODAY_DATE);
            }
        } catch (err) {}
        if (!dateInput.value) {
            var now = new Date();
            var yyyy = now.getFullYear();
            var mm = String(now.getMonth() + 1).padStart(2, "0");
            var dd = String(now.getDate()).padStart(2, "0");
            dateInput.value = yyyy + "-" + mm + "-" + dd;
        }
        state.date = dateInput.value;
    }

    // Inisialisasi dropdown jam 00 s/d 23
    if (startHourSelect && endHourSelect) {
        startHourSelect.innerHTML = "";
        endHourSelect.innerHTML = "";
        for (var h = 0; h < 24; h++) {
            var opt1 = document.createElement("option");
            opt1.value = h;
            opt1.textContent = pad(h) + ":00";
            startHourSelect.appendChild(opt1);

            var opt2 = document.createElement("option");
            opt2.value = h;
            opt2.textContent = pad(h) + ":00";
            endHourSelect.appendChild(opt2);
        }
        startHourSelect.value = state.startHour;
    }

    // Render Denah Grid PC
    function renderMap() {
        if (!mapContainer) return;
        mapContainer.innerHTML = "";

        rawComputers.forEach(function (pc) {
            var btn = document.createElement("button");
            btn.type = "button";

            var isTaken = (pc.status === "booked");
            var isMaint = (pc.status === "maintenance");
            var isLocked = isTaken || isMaint;

            var classes = ["pc-unit"];
            if (state.selectedId === pc.id) classes.push("sel");
            if (isTaken) classes.push("pc-booked");
            if (isMaint) classes.push("pc-maintenance");

            // Filter Kategori
            if (state.category !== "Semua") {
                if (pc.type.toLowerCase() !== state.category.toLowerCase()) {
                    classes.push("hide");
                }
            }

            btn.className = classes.join(" ");
            btn.disabled = isLocked;

            var badgeText = pc.type;
            if (isTaken) badgeText = "Dibooking";
            if (isMaint) badgeText = "Maintenance";

            btn.innerHTML = '<span class="pc-badge">' + badgeText + '</span><em>' + pc.name + '</em>';

            if (!isLocked) {
                btn.addEventListener("click", function () {
                    state.selectedId = pc.id;
                    state.selectedPc = pc;
                    renderMap();
                    renderSummary();
                });
            }

            mapContainer.appendChild(btn);
        });
    }

    // Hitung dan Perbarui Ringkasan Booking
    function renderSummary() {
        var endHour = (state.startHour + state.hours) % 24;
        if (endHourSelect) {
            endHourSelect.value = endHour;
        }

        // Format waktu Indonesia
        var tglFormatted = state.date;
        if (state.date) {
            try {
                var dParts = state.date.split("-");
                if (dParts.length === 3) {
                    var dObj = new Date(dParts[0], dParts[1] - 1, dParts[2]);
                    tglFormatted = dObj.toLocaleDateString("id-ID", {
                        day: "numeric",
                        month: "short",
                        year: "numeric"
                    });
                }
            } catch (err) {}
        }

        if (sTime) {
            var durLabel = state.isNight ? "Paket Malam (6 Jam)" : state.hours + " Jam";
            sTime.textContent = tglFormatted + ", " + pad(state.startHour) + ":00 - " + pad(endHour) + ":00 WIB (" + durLabel + ")";
        }

        var pc = state.selectedPc;
        if (!pc || pc.status !== "available") {
            if (sPc) sPc.textContent = "Belum dipilih";
            if (sRate) sRate.textContent = "-";
            if (sTotal) sTotal.textContent = "Rp 0";
            if (confirmBtn) confirmBtn.disabled = true;
            return;
        }

        var rate = Number(pc.rate);
        var total = state.isNight ? nightPrice : (rate * state.hours);

        if (sPc) sPc.textContent = pc.name + " (" + pc.type.toUpperCase() + ")";
        if (sRate) sRate.textContent = formatRupiah(rate);
        if (sTotal) sTotal.textContent = formatRupiah(total);
        if (confirmBtn) confirmBtn.disabled = false;

        // Sync hidden fields untuk submit POST
        if (hiddenPcId) hiddenPcId.value = pc.id;
        if (hiddenDate) hiddenDate.value = state.date;
        if (hiddenStart) hiddenStart.value = state.startHour;
        if (hiddenDuration) hiddenDuration.value = state.hours;
        if (hiddenIsNight) hiddenIsNight.value = state.isNight ? "1" : "0";
    }

    // Event Kategori Chips
    var catButtons = document.querySelectorAll("#categoryChips .booking-chip");
    catButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            catButtons.forEach(function (b) { b.classList.remove("on"); });
            btn.classList.add("on");
            state.category = btn.value || "Semua";

            // Jika PC terpilih tidak masuk kategori yang difilter, batalkan seleksi
            if (state.selectedPc && state.category !== "Semua") {
                if (state.selectedPc.type.toLowerCase() !== state.category.toLowerCase()) {
                    state.selectedId = null;
                    state.selectedPc = null;
                }
            }

            renderMap();
            renderSummary();
        });
    });

    // Event Durasi Chips
    var durButtons = document.querySelectorAll("#durationChips .booking-chip");
    durButtons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            durButtons.forEach(function (b) { b.classList.remove("on"); });
            btn.classList.add("on");
            var val = btn.value || "3";
            if (val === "6_night") {
                state.hours = 6;
                state.isNight = true;
            } else {
                state.hours = Number(val) || 3;
                state.isNight = false;
            }
            renderSummary();
        });
    });

    // Event Tanggal & Jam Mulai
    if (dateInput) {
        dateInput.addEventListener("change", function () {
            state.date = dateInput.value;
            renderSummary();
        });
    }

    if (startHourSelect) {
        startHourSelect.addEventListener("change", function () {
            state.startHour = Number(startHourSelect.value) || 0;
            renderSummary();
        });
    }

    // Submit validasi klien
    if (formEl) {
        formEl.addEventListener("submit", function (ev) {
            if (!state.selectedPc || state.selectedPc.status !== "available") {
                alert("Silakan pilih unit PC yang masih tersedia terlebih dahulu.");
                ev.preventDefault();
                return false;
            }
        });
    }

    // Jalankan render awal
    renderMap();
    renderSummary();
});
