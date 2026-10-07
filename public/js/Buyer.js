// Buyer/Customer JavaScript Functions

// Product Page Functions
function selectVariation(button) {
    // Reset all variation buttons
    document.querySelectorAll('.variation-btn').forEach(btn => {
        btn.style.borderColor = '#cfdce8';
        btn.style.color = '#1a4d6e';
        btn.style.background = 'white';
    });
    
    // Highlight selected variation
    button.style.borderColor = '#fa4e1c';
    button.style.color = '#fa4e1c';
    button.style.background = '#FFF5F2';
    
    // Update hidden input or other logic as needed
    const variationName = button.getAttribute('data-variation');
    const hiddenInput = document.querySelector('input[name="variation"]');
    if (hiddenInput) {
        hiddenInput.value = variationName;
    }
}

// Image gallery functions for product page
function changeMainImage(src, button) {
    document.getElementById('main-img').src = src;
    document.querySelectorAll('.thumb-btn').forEach(b => b.style.borderColor = '#cfdce8');
    button.style.borderColor = '#fa4e1c';
}

// Quantity controls for product and cart pages
function decreaseQuantity(button, minValue = 1) {
    const input = button.nextElementSibling;
    if (+input.value > minValue) {
        input.value = +input.value - 1;
        // Trigger form submission if this is in cart
        if (button.closest('form')) {
            button.closest('form').submit();
        }
    }
}

function increaseQuantity(button, maxValue = null) {
    const input = button.previousElementSibling;
    if (!maxValue || +input.value < maxValue) {
        input.value = +input.value + 1;
        // Trigger form submission if this is in cart
        if (button.closest('form')) {
            button.closest('form').submit();
        }
    }
}

// Address/Location functions for checkout and registration
const BASE = '/api/psgc';

async function loadProvinces(regionCode, regionName) {
    const provinceSelect = document.getElementById('sel-province');
    const citySelect = document.getElementById('sel-city');
    const barangaySelect = document.getElementById('sel-barangay');
    
    // Reset dependent dropdowns
    provinceSelect.innerHTML = '<option value="">Loading provinces...</option>';
    citySelect.innerHTML = '<option value="">— Select Province first —</option>';
    if (barangaySelect) barangaySelect.innerHTML = '<option value="">— Select City first —</option>';
    
    provinceSelect.disabled = true;
    citySelect.disabled = true;
    if (barangaySelect) barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${BASE}/provinces/${regionCode}`);
        const provinces = await response.json();
        
        provinceSelect.innerHTML = '<option value="">— Select Province —</option>';
        provinces.forEach(province => {
            provinceSelect.innerHTML += `<option value="${province.code}" data-name="${province.name}">${province.name}</option>`;
        });
        
        provinceSelect.disabled = false;
    } catch (error) {
        console.error('Failed to load provinces:', error);
        provinceSelect.innerHTML = '<option value="">Failed to load provinces</option>';
    }
}

async function loadMunicipalities(provinceCode, provinceName) {
    const citySelect = document.getElementById('sel-city');
    const barangaySelect = document.getElementById('sel-barangay');
    
    citySelect.innerHTML = '<option value="">Loading cities...</option>';
    if (barangaySelect) barangaySelect.innerHTML = '<option value="">— Select City first —</option>';
    
    citySelect.disabled = true;
    if (barangaySelect) barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${BASE}/municipalities/${provinceCode}`);
        const cities = await response.json();
        
        citySelect.innerHTML = '<option value="">— Select City / Municipality —</option>';
        cities.forEach(city => {
            citySelect.innerHTML += `<option value="${city.code}" data-name="${city.name}">${city.name}</option>`;
        });
        
        citySelect.disabled = false;
    } catch (error) {
        console.error('Failed to load cities:', error);
        citySelect.innerHTML = '<option value="">Failed to load cities</option>';
    }
}

async function loadBarangays(cityCode, cityName) {
    const barangaySelect = document.getElementById('sel-barangay');
    if (!barangaySelect) return;
    
    barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
    barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${BASE}/barangays/${cityCode}`);
        const barangays = await response.json();
        
        barangaySelect.innerHTML = '<option value="">— Select Barangay —</option>';
        barangays.forEach(barangay => {
            barangaySelect.innerHTML += `<option value="${barangay.code}" data-name="${barangay.name}">${barangay.name}</option>`;
        });
        
        barangaySelect.disabled = false;
    } catch (error) {
        console.error('Failed to load barangays:', error);
        barangaySelect.innerHTML = '<option value="">Failed to load barangays</option>';
    }
}

// Registration specific address functions
async function loadProvincesByRegion(regionCode, regionName) {
    const provinceSelect = document.getElementById('province_select');
    const municipalitySelect = document.getElementById('municipality_select');
    const barangaySelect = document.getElementById('barangay_select');
    
    // Reset dependent dropdowns
    provinceSelect.innerHTML = '<option value="">Loading provinces...</option>';
    municipalitySelect.innerHTML = '<option value="">— Select Province first —</option>';
    if (barangaySelect) barangaySelect.innerHTML = '<option value="">— Select Municipality first —</option>';
    
    provinceSelect.disabled = true;
    municipalitySelect.disabled = true;
    if (barangaySelect) barangaySelect.disabled = true;
    
    try {
        const response = await fetch(`${BASE}/provinces/${regionCode}`);
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

// Category drawer for mobile
function toggleCategoryDrawer() {
    const drawer = document.getElementById('mob-cat-drawer');
    if (drawer) {
        drawer.classList.toggle('translate-x-full');
    }
}

function closeCategoryDrawer() {
    const drawer = document.getElementById('mob-cat-drawer');
    if (drawer) {
        drawer.classList.add('translate-x-full');
    }
}

// Initialize functions when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Load initial regions for address forms
    loadInitialRegions();
    
    // Setup quantity button event listeners
    setupQuantityButtons();
});

async function loadInitialRegions() {
    const regionSelects = document.querySelectorAll('#region_select, #sel-region');
    
    for (const select of regionSelects) {
        try {
            const response = await fetch(`${BASE}/regions`);
            const regions = await response.json();
            
            select.innerHTML = '<option value="">— Select Region —</option>';
            regions.forEach(region => {
                select.innerHTML += `<option value="${region.code}" data-name="${region.name}">${region.name}</option>`;
            });
        } catch (error) {
            console.error('Failed to load regions:', error);
            select.innerHTML = '<option value="">Failed to load regions</option>';
        }
    }
}

function setupQuantityButtons() {
    // Product page quantity controls
    document.querySelectorAll('[onclick*="decrease"]').forEach(button => {
        button.onclick = function() {
            const input = this.nextElementSibling;
            if (+input.value > 1) {
                input.value = +input.value - 1;
            }
        };
    });
    
    document.querySelectorAll('[onclick*="increase"]').forEach(button => {
        button.onclick = function() {
            const input = this.previousElementSibling;
            const maxStock = this.getAttribute('data-max') || input.getAttribute('max');
            if (!maxStock || +input.value < +maxStock) {
                input.value = +input.value + 1;
            }
        };
    });
}

// Functions to replace remaining inline onclick events

// Wishlist toggle functionality
function toggleWishlist(button) {
    button.classList.toggle('is-wished');
    
    // Add visual feedback
    if (button.classList.contains('is-wished')) {
        button.style.color = '#fa4e1c';
    } else {
        button.style.color = '';
    }
}

// Toast dismissal
function dismissToast(button) {
    const toast = button.closest('.toast');
    if (toast) {
        toast.remove();
    }
}

// Profile account deletion warning
function deleteAccountWarning() {
    alert('Please contact support to delete your account.');
}

// Chat functions
function openChatThread(orderId) {
    console.log('Opening chat thread for order:', orderId);
    // Add actual chat opening logic here
}

// Order management
function openCancelModal(orderId) {
    console.log('Opening cancel modal for order:', orderId);
    const modal = document.getElementById('cancel-order-modal');
    if (modal) {
        modal.style.display = 'block';
        // Set order ID in modal if needed
        const orderIdInput = modal.querySelector('[name="order_id"]');
        if (orderIdInput) {
            orderIdInput.value = orderId;
        }
    }
}

// Initialize event listeners for click handlers
document.addEventListener('DOMContentLoaded', function() {
    // Replace onclick with event listeners
    
    // Mobile category drawer
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-mobile-cat-toggle]')) {
            toggleCategoryDrawder();
        }
        
        if (e.target.matches('[data-mobile-cat-close]')) {
            closeCategoryDrawer();
        }
        
        // Wishlist buttons
        if (e.target.matches('.wishlist-btn') || e.target.closest('.wishlist-btn')) {
            const btn = e.target.matches('.wishlist-btn') ? e.target : e.target.closest('.wishlist-btn');
            toggleWishlist(btn);
        }
        
        // Toast close buttons
        if (e.target.matches('[data-toast-close]')) {
            dismissToast(e.target);
        }
        
        // Delete account buttons
        if (e.target.matches('[data-delete-account]')) {
            deleteAccountWarning();
        }
        
        // Cart quantity controls
        if (e.target.matches('[data-quantity-decrease]')) {
            const input = e.target.nextElementSibling;
            if (input && +input.value > 1) {
                input.value = +input.value - 1;
                if (e.target.closest('form')) {
                    e.target.closest('form').submit();
                }
            }
        }
        
        if (e.target.matches('[data-quantity-increase]')) {
            const input = e.target.previousElementSibling;
            if (input) {
                input.value = +input.value + 1;
                if (e.target.closest('form')) {
                    e.target.closest('form').submit();
                }
            }
        }
    });
});

// Global functions for backwards compatibility
window.toggleWishlist = toggleWishlist;
window.dismissToast = dismissToast;
window.deleteAccountWarning = deleteAccountWarning;
window.openChatThread = openChatThread;
window.openCancelModal = openCancelModal;
window.toggleCategoryDrawer = toggleCategoryDrawer;
window.closeCategoryDrawer = closeCategoryDrawer;