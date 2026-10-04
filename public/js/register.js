// Buyer registration — age auto-calc + Philippine address dropdowns (local PSA PSGC data)

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

// ── Region → Province → Municipality/City → Barangay ─────
// Shared cascade in public/js/psgc-address.js (fills province_code / municipality_code).
PsgcAddress.attach({
    region:   '#region_select',
    province: '#province_select',
    city:     '#municipality_select',
    barangay: '#barangay_select',
    old:      window.REGISTER_OLD || {},
});
