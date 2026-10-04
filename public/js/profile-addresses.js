// Buyer addresses — modal close + edit-modal prefill
['add-address-modal', 'edit-address-modal'].forEach(function (id) {
    document.getElementById(id).addEventListener('click', function (e) {
        if (e.target === this) this.classList.add('hidden');
    });
});

function openEditModal(id, addr) {
    var form = document.getElementById('edit-address-form');
    var base = (form.dataset.addressesBase || '') + '/';
    form.action = base + id;
    document.getElementById('edit_label').value        = addr.label        || 'Home';
    document.getElementById('edit_full_name').value    = addr.full_name    || '';
    document.getElementById('edit_phone').value        = addr.phone        || '';
    document.getElementById('edit_address_line').value = addr.address_line || '';
    // Province / city / barangay dropdowns (psgc-address.js) — preselect the saved names.
    if (window.editAddressPsgc) {
        window.editAddressPsgc.reload({ province: addr.province || '', municipality: addr.city || '', barangay: addr.barangay || '' });
    }
    document.getElementById('edit_zip').value          = addr.zip          || '';
    document.getElementById('edit_country').value      = addr.country      || 'Philippines';
    document.getElementById('edit_is_default').checked = addr.is_default   == 1;
    document.getElementById('edit-address-modal').classList.remove('hidden');
}
