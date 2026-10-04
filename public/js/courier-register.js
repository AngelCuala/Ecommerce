// Courier registration — age auto-calc, PSGC cascading dropdowns, file uploads
(function () {
    var OLD = window.COURIER_OLD || {};

    // ── Age auto-calculate ─────────────────────────────────────
    var bday = document.getElementById('sc_birthday');
    if (bday) {
        bday.addEventListener('change', function () {
            var dob = new Date(this.value);
            if (isNaN(dob)) return;
            var today = new Date();
            var age = today.getFullYear() - dob.getFullYear();
            var m = today.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
            document.getElementById('sc_age').value = age >= 0 ? age : '';
        });
        if (bday.value) bday.dispatchEvent(new Event('change'));
    }

    // ── Province → Municipality/City → Barangay (local PSA PSGC data) ──
    // Shared cascade in public/js/psgc-address.js. The province list is nationwide and also
    // offers NCR and the highly urbanized cities, which are not under a province.
    PsgcAddress.attach({
        province: '#sc_province',
        city:     '#sc_municipality',
        barangay: '#sc_barangay',
        placeholders: { province: '— Select Province —' },
        old: OLD,
    });
})();

// ── File choose / remove / preview (global for inline handlers) ──
function courierFileChosen(input) {
    var box  = input.closest('[data-upload]');
    var meta = box.querySelector('[data-file-meta]');
    var name = box.querySelector('[data-file-name]');
    var prev = box.querySelector('[data-file-preview]');

    if (input.files && input.files[0]) {
        var file = input.files[0];
        name.textContent = file.name;
        meta.classList.remove('hidden');
        meta.classList.add('flex');
        if (file.type.indexOf('image/') === 0) {
            var reader = new FileReader();
            reader.onload = function (e) { prev.src = e.target.result; prev.classList.remove('hidden'); };
            reader.readAsDataURL(file);
        } else {
            prev.classList.add('hidden');
        }
    }
}

function courierFileRemove(btn) {
    var box   = btn.closest('[data-upload]');
    var input = box.querySelector('[data-file-input]');
    var meta  = box.querySelector('[data-file-meta]');
    var prev  = box.querySelector('[data-file-preview]');
    input.value = '';
    meta.classList.add('hidden');
    meta.classList.remove('flex');
    prev.classList.add('hidden');
    prev.removeAttribute('src');
}
