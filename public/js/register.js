// Buyer registration — age auto-calc + PSGC cascading address dropdowns

// ── Age auto-calculation ─────────────────────────────────
document.getElementById('birthday').addEventListener('change', function () {
    const dob = new Date(this.value);
    const ageField = document.getElementById('age');
    if (isNaN(dob.getTime())) { ageField.value = ''; return; }
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    ageField.value = age >= 0 ? age : '';
});

// ── PSGC cascading address dropdowns ─────────────────────
const PSGC = '/api/psgc';

function setLoading(id, msg) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">${msg}</option>`;
    el.disabled = true;
}

function populate(id, items, placeholder) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.name;
        opt.dataset.code = item.code;
        opt.textContent = item.name;
        el.appendChild(opt);
    });
    el.disabled = false;
}

function reset(id, placeholder) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">${placeholder}</option>`;
    el.disabled = true;
}

// Load regions on page load
window.addEventListener('DOMContentLoaded', async function () {
    setLoading('region_select', 'Loading regions…');
    try {
        const res  = await fetch(`${PSGC}/regions`);
        const data = await res.json();
        populate('region_select', data, '— Select Region —');
    } catch (e) {
        document.getElementById('region_select').innerHTML =
            '<option value="">⚠ Could not load regions. Refresh to retry.</option>';
    }
});

// Region → Province
async function loadProvincesByRegion(regionName, label) {
    reset('province_select', '— Select Province —');
    reset('municipality_select', '— Select Province first —');
    reset('barangay_select', '— Select Municipality first —');
    if (!regionName) return;

    const sel  = document.getElementById('region_select');
    const code = sel.options[sel.selectedIndex].dataset.code;

    setLoading('province_select', 'Loading provinces…');
    try {
        const res  = await fetch(`${PSGC}/regions/${code}/provinces`);
        const data = await res.json();
        if (data.length === 0) {
            // NCR — no provinces, load cities directly
            populate('province_select', [{ code: code, name: 'Metro Manila (NCR)' }], '— Select Province —');
            await loadMunicipalities('Metro Manila (NCR)', null, code);
        } else {
            populate('province_select', data, '— Select Province —');
        }
    } catch (e) {
        document.getElementById('province_select').innerHTML =
            '<option value="">⚠ Could not load provinces</option>';
    }
}

// Province → Municipality
async function loadMunicipalities(provinceName, label, overrideCode) {
    reset('municipality_select', '— Select Municipality / City —');
    reset('barangay_select', '— Select Municipality first —');

    let code = overrideCode;
    if (!code) {
        const sel = document.getElementById('province_select');
        code = sel.options[sel.selectedIndex]?.dataset.code;
    }
    document.getElementById('province_code').value = code || '';
    if (!code) return;

    setLoading('municipality_select', 'Loading cities…');
    try {
        const res  = await fetch(`${PSGC}/provinces/${code}/municipalities`);
        const data = await res.json();
        populate('municipality_select', data, '— Select Municipality / City —');
    } catch (e) {
        document.getElementById('municipality_select').innerHTML =
            '<option value="">⚠ Could not load municipalities</option>';
    }
}

// Municipality → Barangay
async function loadBarangays(municipalityName, label) {
    reset('barangay_select', '— Select Barangay —');

    const sel  = document.getElementById('municipality_select');
    const code = sel.options[sel.selectedIndex]?.dataset.code;
    document.getElementById('municipality_code').value = code || '';
    if (!code) return;

    setLoading('barangay_select', 'Loading barangays…');
    try {
        const res  = await fetch(`${PSGC}/municipalities/${code}/barangays`);
        const data = await res.json();
        populate('barangay_select', data, '— Select Barangay —');
    } catch (e) {
        document.getElementById('barangay_select').innerHTML =
            '<option value="">⚠ Could not load barangays</option>';
    }
}
