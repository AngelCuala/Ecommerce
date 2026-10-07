// Seller JavaScript Functions

// Product Management Functions
function confirmArchiveProduct() {
    return confirm('Archive this product?');
}

function confirmDeleteProduct() {
    return confirm('Permanently delete this product?');
}

function confirmRemoveBook() {
    return confirm('Remove this book?');
}

// Image Management for Product Forms
function removeExisting(button, imageId) {
    if (confirm('Remove this image?')) {
        const container = button.closest('.image-item');
        container.style.display = 'none';
        
        // Add hidden input to mark for deletion
        const form = button.closest('form');
        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_images[]';
        deleteInput.value = imageId;
        form.appendChild(deleteInput);
    }
}

// Image reordering functions
function initReorder() {
    const container = document.getElementById('existing-images');
    if (!container) return;
    
    let draggedElement = null;
    
    container.addEventListener('dragstart', function(e) {
        draggedElement = e.target.closest('.image-item');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', draggedElement.outerHTML);
    });
    
    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    });
    
    container.addEventListener('drop', function(e) {
        e.preventDefault();
        if (draggedElement) {
            const target = e.target.closest('.image-item');
            if (target && target !== draggedElement) {
                const rect = target.getBoundingClientRect();
                const midpoint = rect.left + rect.width / 2;
                
                if (e.clientX < midpoint) {
                    container.insertBefore(draggedElement, target);
                } else {
                    container.insertBefore(draggedElement, target.nextSibling);
                }
                
                updateImageOrder();
                updateMainBadge();
            }
        }
    });
    
    // Make images draggable
    container.querySelectorAll('.image-item').forEach(item => {
        item.draggable = true;
    });
}

function updateImageOrder() {
    const container = document.getElementById('existing-images');
    if (!container) return;
    
    container.querySelectorAll('.image-item').forEach((item, index) => {
        const orderInput = item.querySelector('input[name="image_order[]"]');
        if (orderInput) {
            orderInput.value = item.querySelector('input[name="image_order[]"]').value;
        }
    });
}

function updateMainBadge() {
    const container = document.getElementById('existing-images');
    if (!container) return;
    
    // Remove all main badges
    container.querySelectorAll('.main-badge').forEach(badge => {
        badge.classList.add('hidden');
    });
    
    // Add main badge to first image
    const firstImage = container.querySelector('.image-item');
    if (firstImage) {
        const mainBadge = firstImage.querySelector('.main-badge');
        if (mainBadge) {
            mainBadge.classList.remove('hidden');
        }
    }
}

// Address functions for seller application
const PSGC_BASE = '/api/psgc';

async function saLoadProvincesByRegion(regionCode) {
    const provinceSelect = document.getElementById('sa_province');
    const municipalitySelect = document.getElementById('sa_municipality');
    const barangaySelect = document.getElementById('sa_barangay');
    
    provinceSelect.innerHTML = '<option value="">Loading provinces...</option>';
    municipalitySelect.innerHTML = '<option value="">— Select Province first —</option>';
    if (barangaySelect) barangaySelect.innerHTML = '<option value="">— Select Municipality first —</option>';
    
    provinceSelect.disabled = true;
    municipalitySelect.disabled = true;
    if (barangaySelect) barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${PSGC_BASE}/provinces/${regionCode}`);
        const provinces = await response.json();
        
        provinceSelect.innerHTML = '<option value="">— Select Province —</option>';
        provinces.forEach(province => {
            provinceSelect.innerHTML += `<option value="${province.name}" data-code="${province.code}">${province.name}</option>`;
        });
        
        provinceSelect.disabled = false;
    } catch (error) {
        console.error('Failed to load provinces:', error);
        provinceSelect.innerHTML = '<option value="">Failed to load provinces</option>';
    }
}

async function saLoadMunicipalities(provinceCode, provinceName) {
    const municipalitySelect = document.getElementById('sa_municipality');
    const barangaySelect = document.getElementById('sa_barangay');
    
    municipalitySelect.innerHTML = '<option value="">Loading municipalities...</option>';
    if (barangaySelect) barangaySelect.innerHTML = '<option value="">— Select Municipality first —</option>';
    
    municipalitySelect.disabled = true;
    if (barangaySelect) barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${PSGC_BASE}/municipalities/${provinceCode}`);
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

async function saLoadBarangays(municipalityCode) {
    const barangaySelect = document.getElementById('sa_barangay');
    if (!barangaySelect) return;
    
    barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
    barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${PSGC_BASE}/barangays/${municipalityCode}`);
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

// Age calculation for seller application
function setupAgeCalculation() {
    const birthdayInput = document.getElementById('birthday_apply');
    if (birthdayInput) {
        birthdayInput.addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            const ageDisplay = document.getElementById('age_display');
            if (ageDisplay) {
                ageDisplay.textContent = age + ' years old';
            }
        });
    }
}

// Avatar preview for account page
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatar-preview');
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Order management functions
function confirmHandoverToCourier() {
    return confirm('Confirm that you have handed this order to the courier?');
}

// Initialize seller functions
document.addEventListener('DOMContentLoaded', function() {
    // Initialize image reordering if on product form page
    if (document.getElementById('existing-images')) {
        initReorder();
    }
    
    // Setup age calculation if on seller application page
    setupAgeCalculation();
    
    // Load initial regions for seller application
    loadSellerApplicationRegions();
});

async function loadSellerApplicationRegions() {
    const regionSelect = document.getElementById('sa_region');
    if (!regionSelect) return;
    
    try {
        const response = await fetch(`${PSGC_BASE}/regions`);
        const regions = await response.json();
        
        regionSelect.innerHTML = '<option value="">— Select Region —</option>';
        regions.forEach(region => {
            regionSelect.innerHTML += `<option value="${region.code}">${region.name}</option>`;
        });
    } catch (error) {
        console.error('Failed to load regions:', error);
        regionSelect.innerHTML = '<option value="">Failed to load regions</option>';
    }
}