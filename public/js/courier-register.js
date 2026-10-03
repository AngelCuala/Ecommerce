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

    // ── PSGC cascading dropdowns ───────────────────────────────
    var PSGC = '/api/psgc';

    function scPopulate(id, items, placeholder) {
        var el = document.getElementById(id);
        el.innerHTML = '<option value="">' + placeholder + '</option>';
        items.forEach(function (item) {
            var o = document.createElement('option');
            o.value = item.name;
            o.dataset.code = item.code;
            o.textContent = item.name;
            el.appendChild(o);
        });
        el.disabled = false;
    }

    (function loadProvinces() {
        fetch(PSGC + '/provinces')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                scPopulate('sc_province', data, '— Select Province —');
                if (OLD.province) {
                    [].forEach.call(document.getElementById('sc_province').options, function (o) {
                        if (o.value === OLD.province) { o.selected = true; window.scLoadMunicipalities(o.dataset.code); }
                    });
                }
            })
            .catch(function () {
                var el = document.getElementById('sc_province');
                el.innerHTML = '<option value="">Could not load provinces — refresh to retry</option>';
                el.disabled = false;
            });
    })();

    window.scLoadMunicipalities = function (code) {
        var mEl = document.getElementById('sc_municipality');
        var bEl = document.getElementById('sc_barangay');
        mEl.innerHTML = '<option>Loading…</option>'; mEl.disabled = true;
        bEl.innerHTML = '<option>— Select Municipality first —</option>'; bEl.disabled = true;
        if (!code) return;
        fetch(PSGC + '/provinces/' + code + '/municipalities')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                scPopulate('sc_municipality', data, '— Select Municipality —');
                if (OLD.municipality) {
                    [].forEach.call(document.getElementById('sc_municipality').options, function (o) {
                        if (o.value === OLD.municipality) { o.selected = true; window.scLoadBarangays(o.dataset.code); }
                    });
                }
            })
            .catch(function () {});
    };

    window.scLoadBarangays = function (code) {
        var bEl = document.getElementById('sc_barangay');
        bEl.innerHTML = '<option>Loading…</option>'; bEl.disabled = true;
        if (!code) return;
        fetch(PSGC + '/municipalities/' + code + '/barangays')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                scPopulate('sc_barangay', data, '— Select Barangay —');
                if (OLD.barangay) {
                    [].forEach.call(document.getElementById('sc_barangay').options, function (o) {
                        if (o.value === OLD.barangay) o.selected = true;
                    });
                }
            })
            .catch(function () {});
    };
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
