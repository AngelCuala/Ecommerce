// Admin JavaScript Functions

// User Management Functions
function confirmSuspendUser(userName) {
    return confirm(`Suspend ${userName}?`);
}

function confirmDeactivateUser(userName) {
    return confirm(`Permanently deactivate ${userName}? They will not be able to log in.`);
}

function confirmApproveCustomer(customerName) {
    return confirm(`Approve ${customerName}?`);
}

function confirmRejectCustomer(customerName) {
    return confirm(`Reject ${customerName}?`);
}

function confirmSuspendCustomer(customerName) {
    return confirm(`Suspend ${customerName}?`);
}

// Customer rejection panel toggle
function toggleRejectPanel() {
    const panel = document.getElementById('reject-panel');
    if (panel) {
        panel.classList.toggle('hidden');
    }
}

// Image lightbox functions
function showImageLightbox(lightboxId) {
    const lightbox = document.getElementById(lightboxId);
    if (lightbox) {
        lightbox.classList.remove('hidden');
    }
}

function hideImageLightbox(lightboxId) {
    const lightbox = document.getElementById(lightboxId);
    if (lightbox) {
        lightbox.classList.add('hidden');
    }
}

// Parcel and logistics management
function confirmRejectPickupRequest() {
    return confirm('Reject this pickup request?');
}

function confirmRejectParcel() {
    return confirm('Reject?');
}

function confirmSortParcel(parcelId) {
    const form = document.getElementById(`sort-form-${parcelId}`);
    const areaSelect = form.querySelector('select[name="area_id"]');
    
    if (!areaSelect.value) {
        alert('Please select an area first');
        return false;
    }
    
    return confirm('Confirm sort to selected area?');
}

function assignParcelToRider(parcelId) {
    const riderSelect = document.getElementById(`rider-${parcelId}`);
    const hiddenInput = document.getElementById(`hidden-rider-${parcelId}`);
    const form = document.getElementById(`assign-${parcelId}`);
    
    if (!riderSelect.value) {
        alert('Please select a rider first');
        return false;
    }
    
    hiddenInput.value = riderSelect.value;
    
    if (confirm('Assign this parcel to the selected rider?')) {
        form.submit();
    }
}

// Search functions
function submitParcelSearch() {
    document.getElementById('search-parcels').submit();
}

function submitOrderStatusFilter(selectElement) {
    selectElement.form.submit();
}

// Settings management
function confirmDeleteAnnouncement() {
    return confirm('Delete this announcement?');
}

// Policy preview toggle
function togglePreview() {
    const previewDiv = document.getElementById('policy-preview');
    const toggleBtn = document.getElementById('toggle-preview');
    const textarea = document.querySelector('textarea[name="content"]');
    
    if (previewDiv.style.display === 'none' || !previewDiv.style.display) {
        // Show preview
        previewDiv.style.display = 'block';
        previewDiv.innerHTML = marked ? marked(textarea.value) : textarea.value.replace(/\n/g, '<br>');
        toggleBtn.textContent = '📝 Edit Mode';
    } else {
        // Hide preview
        previewDiv.style.display = 'none';
        toggleBtn.textContent = '👁 Preview Rendered Output';
    }
}

// Address management for user profiles
const ADMIN_PSGC = '/api/psgc';

async function loadUserAddressData(userId) {
    // Load regions, provinces, municipalities, and barangays for user profile editing
    try {
        const response = await fetch(`${ADMIN_PSGC}/regions`);
        const regions = await response.json();
        
        const regionSelect = document.getElementById('user-region');
        if (regionSelect) {
            regionSelect.innerHTML = '<option value="">— Select Region —</option>';
            regions.forEach(region => {
                regionSelect.innerHTML += `<option value="${region.code}">${region.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Failed to load regions:', error);
    }
}

// Customer ID lightbox
function showCustomerIdLightbox() {
    document.getElementById('customer-id-lightbox').classList.remove('hidden');
}

function hideCustomerIdLightbox() {
    document.getElementById('customer-id-lightbox').classList.add('hidden');
}

// Review management
function confirmDeleteReview() {
    return confirm('Delete this review?');
}

function confirmApproveReview() {
    return confirm('Approve this review?');
}

// Order management
function confirmUpdateOrderStatus() {
    return confirm('Update order status?');
}

// Product management
function confirmDeleteProduct() {
    return confirm('Delete this product?');
}

function confirmArchiveProduct() {
    return confirm('Archive this product?');
}

// Bulk operations
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name="selected_items[]"]');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

function confirmBulkAction() {
    const selected = document.querySelectorAll('input[name="selected_items[]"]:checked');
    if (selected.length === 0) {
        alert('Please select items first');
        return false;
    }
    
    const action = document.querySelector('select[name="bulk_action"]').value;
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    return confirm(`Apply ${action} to ${selected.length} selected item(s)?`);
}

// Initialize admin functions
document.addEventListener('DOMContentLoaded', function() {
    // Setup form confirmations
    setupFormConfirmations();
    
    // Setup lightbox event listeners
    setupLightboxes();
    
    // Setup bulk operation handlers
    setupBulkOperations();
});

function setupFormConfirmations() {
    // Add event listeners for all confirmation forms
    document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const onsubmit = this.getAttribute('onsubmit');
            if (onsubmit && !eval(onsubmit.replace('return ', ''))) {
                e.preventDefault();
            }
        });
    });
}

function setupLightboxes() {
    // Setup lightbox close handlers
    document.querySelectorAll('[id$="-lightbox"]').forEach(lightbox => {
        lightbox.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
}

function setupBulkOperations() {
    // Setup select all functionality
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            toggleSelectAll(this);
        });
    }
    
    // Setup bulk action forms
    document.querySelectorAll('form[id*="bulk"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirmBulkAction()) {
                e.preventDefault();
            }
        });
    });
}