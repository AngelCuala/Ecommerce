// Buyer orders — cancel-order modal + reason handling
function openCancelModal(orderId) {
    var form = document.getElementById('cancel-order-form');
    var base = (form.dataset.ordersBase || '') + '/';
    form.action = base + orderId + '/cancel';
    // Reset state
    document.querySelectorAll('#cancel-order-form input[type=radio]').forEach(function (r) { r.checked = false; });
    document.getElementById('custom-reason-wrap').classList.add('hidden');
    document.getElementById('custom-reason-text').value = '';
    document.getElementById('cancel-order-modal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancel-order-modal').classList.add('hidden');
}

function toggleCustomReason(val) {
    var wrap = document.getElementById('custom-reason-wrap');
    var txt  = document.getElementById('custom-reason-text');
    if (val === 'Other reason') {
        wrap.classList.remove('hidden');
        txt.name = 'cancellation_reason';
    } else {
        wrap.classList.add('hidden');
        txt.name = 'cancellation_reason_custom'; // disable submission
    }
}

// Close on backdrop click
document.getElementById('cancel-order-modal').addEventListener('click', function (e) {
    if (e.target === this) closeCancelModal();
});

// Handle form submit — ensure a reason is provided
document.getElementById('cancel-order-form').addEventListener('submit', function (e) {
    var chosen = this.querySelector('input[name="cancellation_reason"]:checked');
    if (!chosen || !chosen.value.trim()) {
        e.preventDefault();
        alert('Please select a cancellation reason.');
    }
});
