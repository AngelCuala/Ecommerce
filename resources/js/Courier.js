// Courier JavaScript Functions

// Delivery management functions
function confirmPickupFromSeller() {
    return confirm('Confirm you have picked up this package from the seller?');
}

function confirmDeliveryComplete() {
    return confirm('Confirm this package was delivered to the buyer?');
}

function confirmAcceptDeliveryJob() {
    return confirm('Accept this delivery job?');
}

// Courier registration address functions
const COURIER_PSGC = '/api/psgc';

async function scLoadMunicipalities(provinceCode) {
    const municipalitySelect = document.getElementById('sc_municipality');
    const barangaySelect = document.getElementById('sc_barangay');
    
    municipalitySelect.innerHTML = '<option value="">Loading municipalities...</option>';
    if (barangaySelect) barangaySelect.innerHTML = '<option value="">— Select Municipality first —</option>';
    
    municipalitySelect.disabled = true;
    if (barangaySelect) barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${COURIER_PSGC}/municipalities/${provinceCode}`);
        const municipalities = await response.json();
        
        municipalitySelect.innerHTML = '<option value="">— Select Municipality / City —</option>';
        municipalities.forEach(municipality => {
            municipalitySelect.innerHTML += `<option value="${municipality.name}" data-code="${municipality.code}">${municipality.name}</option>`;
        });
        
        municipalitySelect.disabled = false;
    } catch (error) {
        console.error('Failed to load municipalities:', error);
        municipalitySelect.innerHTML = '<option value="">Failed to load municipalities</option>';
    }
}

async function scLoadBarangays(municipalityCode) {
    const barangaySelect = document.getElementById('sc_barangay');
    if (!barangaySelect) return;
    
    barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
    barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${COURIER_PSGC}/barangays/${municipalityCode}`);
        const barangays = await response.json();
        
        barangaySelect.innerHTML = '<option value="">— Select Barangay —</option>';
        barangays.forEach(barangay => {
            barangaySelect.innerHTML += `<option value="${barangay.name}">${barangay.name}</option>`;
        });
        
        barangaySelect.disabled = false;
    } catch (error) {
        console.error('Failed to load barangays:', error);
        barangaySelect.innerHTML = '<option value="">Failed to load barangays</option>';
    }
}

// File upload functions for courier registration
function courierFileChosen(input) {
    const container = input.closest('[data-upload]');
    const fileMetaDiv = container.querySelector('[data-file-meta]');
    const fileNameSpan = container.querySelector('[data-file-name]');
    const filePreview = container.querySelector('[data-file-preview]');
    const uploadButton = container.querySelector('button[onclick*="click"]');
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Show file info
        fileNameSpan.textContent = file.name;
        fileMetaDiv.classList.remove('hidden');
        fileMetaDiv.classList.add('flex');
        
        // Update button text
        uploadButton.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
            </svg>
            📎 Change File
        `;
        
        // Show image preview if it's an image
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                filePreview.src = e.target.result;
                filePreview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            filePreview.classList.add('hidden');
        }
    }
}

function courierFileRemove(button) {
    const container = button.closest('[data-upload]');
    const fileInput = container.querySelector('[data-file-input]');
    const fileMetaDiv = container.querySelector('[data-file-meta]');
    const filePreview = container.querySelector('[data-file-preview]');
    const uploadButton = container.querySelector('button[onclick*="click"]');
    
    // Clear file input
    fileInput.value = '';
    
    // Hide file info
    fileMetaDiv.classList.add('hidden');
    fileMetaDiv.classList.remove('flex');
    
    // Hide preview
    filePreview.classList.add('hidden');
    
    // Reset button text
    uploadButton.innerHTML = `
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
        </svg>
        📎 Choose File
    `;
}

// Package status update functions
function updatePackageStatus(packageId, status) {
    if (!confirm(`Update package status to ${status}?`)) {
        return false;
    }
    
    // You can add AJAX call here to update status without form submission
    return true;
}

// Delivery route optimization
function optimizeRoute() {
    if (!confirm('Optimize your delivery route based on current assignments?')) {
        return;
    }
    
    // This would integrate with a mapping service
    alert('Route optimization feature coming soon!');
}

// Delivery proof of delivery
function uploadProofOfDelivery(deliveryId) {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.multiple = true;
    
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            // Handle proof of delivery upload
            handleProofUpload(deliveryId, this.files);
        }
    });
    
    fileInput.click();
}

function handleProofUpload(deliveryId, files) {
    const formData = new FormData();
    formData.append('delivery_id', deliveryId);
    
    for (let i = 0; i < files.length; i++) {
        formData.append('proof_images[]', files[i]);
    }
    
    // You would send this to your backend
    console.log('Uploading proof of delivery for:', deliveryId);
    alert(`${files.length} proof image(s) ready for upload`);
}

// Courier earnings calculator
function calculateDailyEarnings() {
    const deliveries = document.querySelectorAll('.delivery-completed');
    let totalEarnings = 0;
    
    deliveries.forEach(delivery => {
        const earnings = parseFloat(delivery.dataset.earnings || 0);
        totalEarnings += earnings;
    });
    
    const earningsDisplay = document.getElementById('daily-earnings');
    if (earningsDisplay) {
        earningsDisplay.textContent = `₱${totalEarnings.toFixed(2)}`;
    }
    
    return totalEarnings;
}

// Initialize courier functions
document.addEventListener('DOMContentLoaded', function() {
    // Load provinces for courier registration
    loadCourierRegistrationData();
    
    // Calculate earnings if on dashboard
    if (document.querySelector('.delivery-completed')) {
        calculateDailyEarnings();
    }
    
    // Setup file upload handlers
    setupFileUploads();
});

async function loadCourierRegistrationData() {
    const provinceSelect = document.getElementById('sc_province');
    if (!provinceSelect) return;
    
    try {
        // Load provinces for service coverage area
        const response = await fetch(`${COURIER_PSGC}/provinces/NCR`); // Default to NCR
        const provinces = await response.json();
        
        provinceSelect.innerHTML = '<option value="">— Select Province —</option>';
        provinces.forEach(province => {
            provinceSelect.innerHTML += `<option value="${province.name}" data-code="${province.code}">${province.name}</option>`;
        });
    } catch (error) {
        console.error('Failed to load courier registration data:', error);
    }
}

function setupFileUploads() {
    // Setup all file input handlers
    document.querySelectorAll('[data-file-input]').forEach(input => {
        input.addEventListener('change', function() {
            courierFileChosen(this);
        });
    });
    
    // Setup file upload buttons
    document.querySelectorAll('button[onclick*="click"]').forEach(button => {
        if (button.onclick && button.onclick.toString().includes('data-file-input')) {
            button.addEventListener('click', function() {
                const container = this.closest('[data-upload]');
                const fileInput = container.querySelector('[data-file-input]');
                if (fileInput) {
                    fileInput.click();
                }
            });
        }
    });
}