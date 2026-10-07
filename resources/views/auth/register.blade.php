<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account — ALVY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui; }
        .font-display { font-family: 'Nunito', ui-sans-serif, system-ui; }
        
        .role-chip, .gender-chip, .wheel-chip {
            cursor: pointer;
            transition: all 0.18s ease;
        }
        
        .role-chip.active {
            background-color: #1B2A6B;
            border-color: #1B2A6B;
            color: white;
        }
        
        .gender-chip.active, .wheel-chip.active {
            background-color: #1B2A6B;
            border-color: #1B2A6B;
            color: white;
        }
        
        .role-chip:not(.active):hover,
        .gender-chip:not(.active):hover,
        .wheel-chip:not(.active):hover {
            background-color: #f3f4f6;
        }
        
        .form-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 0.875rem 1rem;
            font-size: 0.9rem;
            color: #222;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        
        .form-input:focus {
            border-color: #1B2A6B;
            box-shadow: 0 0 0 3px rgba(27, 42, 107, 0.1);
        }
        
        .section-divider {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0 1rem;
        }
        
        .section-bar {
            width: 4px;
            height: 18px;
            background-color: #F05A28;
            border-radius: 2px;
        }
        
        .hidden-section {
            display: none;
        }
        
        .file-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #f9fafb;
        }
        
        .file-upload-box:hover {
            border-color: #1B2A6B;
            background: #f0f9ff;
        }
        
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 0.5rem;
            margin-top: 1rem;
        }
        
        .provider-card {
            padding: 1rem;
            border-radius: 0.75rem;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.18s;
            margin-bottom: 0.5rem;
        }
        
        .provider-card:hover {
            background: #f3f4f6;
        }
        
        .provider-card.active {
            background: rgba(27, 42, 107, 0.07);
            border-color: #1B2A6B;
            border-width: 1.5px;
        }
    </style>
</head>
<body style="background: #fbeee8;">

<div class="min-h-screen flex items-center justify-center p-4 sm:p-8">
    <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden">
        
        <div class="p-6 sm:p-10">
            {{-- Header --}}
            <div class="text-center mb-8">
                <a href="{{ route('home') }}" class="inline-block mb-4">
                    <img src="{{ asset('images/logo.png') }}" alt="ALVY" class="h-20 w-20 object-cover mx-auto">
                </a>
                <h1 class="font-display text-3xl font-extrabold" style="color: #1B2A6B;">Join AlvyShop</h1>
                <p class="text-gray-500 text-sm mt-1">Create your account</p>
            </div>

            <form id="registrationForm" method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                @csrf

                {{-- Role Selector --}}
                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-3" style="color: #2D2D2D;">I am a:</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div class="role-chip active border rounded-xl p-3 text-center bg-gray-100" data-role="buyer">
                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span class="text-xs font-semibold">Buyer</span>
                        </div>
                        <div class="role-chip border rounded-xl p-3 text-center bg-gray-100" data-role="seller">
                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span class="text-xs font-semibold">Seller</span>
                        </div>
                        <div class="role-chip border rounded-xl p-3 text-center bg-gray-100" data-role="courier">
                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="text-xs font-semibold">Courier</span>
                        </div>
                        <div class="role-chip border rounded-xl p-3 text-center bg-gray-100" data-role="logistics">
                            <svg class="w-5 h-5 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span class="text-xs font-semibold">Logistics</span>
                        </div>
                    </div>
                    <input type="hidden" name="role" id="roleInput" value="buyer">
                </div>

                {{-- Error Display --}}
                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            @foreach ($errors->all() as $error)
                                <p class="text-red-600 text-sm">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Name Section --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2" style="color: #2D2D2D;">Name</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                        <input type="text" name="first_name" id="firstName" class="form-input" placeholder="First Name" value="{{ old('first_name') }}" required>
                        <input type="text" name="middle_initial" id="middleName" class="form-input" placeholder="Middle (Optional)" value="{{ old('middle_initial') }}">
                    </div>
                    <input type="text" name="last_name" id="lastName" class="form-input" placeholder="Last Name" value="{{ old('last_name') }}" required>
                </div>

                {{-- Email --}}
                <div class="mb-4">
                    <input type="email" name="email" id="email" class="form-input" placeholder="Email" value="{{ old('email') }}" required>
                </div>

                {{-- Gender --}}
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2" style="color: #2D2D2D;">Gender</label>
                    <div class="grid grid-cols-3 gap-2">
                        <div class="gender-chip active border rounded-xl p-3 text-center bg-gray-100" data-gender="male">
                            <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                            </svg>
                            <span class="text-xs font-semibold">Male</span>
                        </div>
                        <div class="gender-chip border rounded-xl p-3 text-center bg-gray-100" data-gender="female">
                            <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                            </svg>
                            <span class="text-xs font-semibold">Female</span>
                        </div>
                        <div class="gender-chip border rounded-xl p-3 text-center bg-gray-100" data-gender="other">
                            <svg class="w-6 h-6 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span class="text-xs font-semibold">Other</span>
                        </div>
                    </div>
                    <input type="hidden" name="sex" id="genderInput" value="male">
                </div>

                {{-- Password --}}
                <div class="mb-6">
                    <div class="relative">
                        <input type="password" name="password" id="password" class="form-input pr-10" placeholder="Password (min. 8 characters)" required>
                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <input type="hidden" name="password_confirmation" id="passwordConfirmation">
                </div>

                {{-- SELLER FIELDS --}}
                <div id="sellerSection" class="hidden-section">
                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="font-bold text-base" style="color: #F05A28;">Seller Documents</h3>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Valid Government ID</label>
                        <div class="file-upload-box" onclick="document.getElementById('sellerIdUpload').click()">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-gray-600">Click to upload Valid ID</p>
                        </div>
                        <input type="file" id="sellerIdUpload" name="id_image" accept="image/*,.pdf" class="hidden">
                        <img id="sellerIdPreview" class="file-preview hidden">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Business / DTI Permit</label>
                        <div class="file-upload-box" onclick="document.getElementById('sellerPermitUpload').click()">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-gray-600">Click to upload Business Permit</p>
                        </div>
                        <input type="file" id="sellerPermitUpload" name="business_permit" accept="image/*,.pdf" class="hidden">
                        <img id="sellerPermitPreview" class="file-preview hidden">
                    </div>

                    <div class="p-3 bg-orange-50 border border-orange-200 rounded-lg flex gap-2 text-sm">
                        <svg class="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-orange-700">Both your valid ID and business permit are required for verification.</p>
                    </div>
                </div>

                {{-- COURIER FIELDS --}}
                <div id="courierSection" class="hidden-section">
                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="font-bold text-base" style="color: #F05A28;">Vehicle Information</h3>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Vehicle Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="wheel-chip active border rounded-xl p-4 text-center bg-gray-100" data-wheel="motorcycle">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <p class="text-xs font-semibold">2 Wheels<br/>(Motorcycle)</p>
                            </div>
                            <div class="wheel-chip border rounded-xl p-4 text-center bg-gray-100" data-wheel="tricycle">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                                <p class="text-xs font-semibold">3 Wheels<br/>(Tricycle)</p>
                            </div>
                            <div class="wheel-chip border rounded-xl p-4 text-center bg-gray-100" data-wheel="car">
                                <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                                <p class="text-xs font-semibold">4 Wheels<br/>(Car/Van)</p>
                            </div>
                        </div>
                        <input type="hidden" name="vehicle_type" id="vehicleTypeInput" value="motorcycle">
                    </div>

                    <div class="mb-4">
                        <input type="text" name="plate_number" id="plateNumber" class="form-input" placeholder="Plate Number" style="text-transform: uppercase;">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Driver's License</label>
                        <div class="file-upload-box" onclick="document.getElementById('driverLicenseUpload').click()">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-gray-600">Click to upload Driver's License</p>
                        </div>
                        <input type="file" id="driverLicenseUpload" name="or_cr_image" accept="image/*,.pdf" class="hidden">
                        <img id="driverLicensePreview" class="file-preview hidden">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Vehicle Registration (OR/CR)</label>
                        <div class="file-upload-box" onclick="document.getElementById('vehicleRegUpload').click()">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-gray-600">Click to upload Vehicle Registration</p>
                        </div>
                        <input type="file" id="vehicleRegUpload" name="vehicle_reg_image" accept="image/*,.pdf" class="hidden">
                        <img id="vehicleRegPreview" class="file-preview hidden">
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="font-bold text-base" style="color: #F05A28;">Logistics Provider</h3>
                    </div>

                    <label class="block text-sm font-semibold mb-2">Select your Sorting Center / Logistics Hub</label>
                    <div id="logisticsProvidersList" class="mb-4">
                        <div class="text-center py-4">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 mx-auto"></div>
                            <p class="text-sm text-gray-500 mt-2">Loading providers...</p>
                        </div>
                    </div>
                    <input type="hidden" name="logistics_id" id="logisticsIdInput">
                </div>

                {{-- LOGISTICS FIELDS --}}
                <div id="logisticsSection" class="hidden-section">
                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="font-bold text-base" style="color: #F05A28;">Sorting Center Information</h3>
                    </div>

                    <div class="mb-4">
                        <input type="text" name="business_name" id="logisticsBusinessName" class="form-input" placeholder="Business / Center Name">
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="font-bold text-base" style="color: #F05A28;">Hub Address</h3>
                    </div>

                    <div class="space-y-3 mb-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1">Province</label>
                            <select name="province" id="logisticsProvince" class="form-input">
                                <option value="">Select Province</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Municipality / City</label>
                            <select name="municipality" id="logisticsMunicipality" class="form-input" disabled>
                                <option value="">Select province first</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Barangay</label>
                            <select name="barangay" id="logisticsBarangay" class="form-input" disabled>
                                <option value="">Select municipality first</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <input type="text" name="street" id="logisticsStreet" class="form-input" placeholder="Street">
                            <input type="text" name="house_number" id="logisticsHouseNumber" class="form-input" placeholder="Bldg / Unit No.">
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="font-bold text-base" style="color: #F05A28;">Logistics Documents</h3>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Business / DTI Permit</label>
                        <div class="file-upload-box" onclick="document.getElementById('logisticsPermitUpload').click()">
                            <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                            <p class="text-sm text-gray-600">Click to upload DTI Permit</p>
                        </div>
                        <input type="file" id="logisticsPermitUpload" name="dti_permit" accept="image/*,.pdf" class="hidden">
                        <img id="logisticsPermitPreview" class="file-preview hidden">
                    </div>

                    <div class="p-3 bg-orange-50 border border-orange-200 rounded-lg flex gap-2 text-sm">
                        <svg class="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-orange-700">Your permit and hub address will be reviewed by the admin before your account is activated.</p>
                    </div>
                </div>

                {{-- Submit Button --}}
                <button type="submit" id="submitBtn" class="w-full py-3.5 rounded-xl text-white font-bold text-base transition mt-6" style="background: #1B2A6B;">
                    Create Account
                </button>

                {{-- Sign In Link --}}
                <p class="text-center text-sm mt-6">
                    <span class="text-gray-600">Already have an account?</span>
                    <a href="{{ route('login') }}" class="font-bold ml-1" style="color: #1B2A6B;">Sign In</a>
                </p>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('js/prevent-back.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleChips = document.querySelectorAll('.role-chip');
    const genderChips = document.querySelectorAll('.gender-chip');
    const wheelChips = document.querySelectorAll('.wheel-chip');
    const roleInput = document.getElementById('roleInput');
    const genderInput = document.getElementById('genderInput');
    const vehicleTypeInput = document.getElementById('vehicleTypeInput');
    
    const sellerSection = document.getElementById('sellerSection');
    const courierSection = document.getElementById('courierSection');
    const logisticsSection = document.getElementById('logisticsSection');
    
    // Role Selection
    roleChips.forEach(chip => {
        chip.addEventListener('click', function() {
            roleChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const role = this.dataset.role;
            roleInput.value = role;
            
            // Hide all sections
            sellerSection.classList.add('hidden-section');
            courierSection.classList.add('hidden-section');
            logisticsSection.classList.add('hidden-section');
            
            // Show relevant section
            if (role === 'seller') {
                sellerSection.classList.remove('hidden-section');
            } else if (role === 'courier') {
                courierSection.classList.remove('hidden-section');
                loadLogisticsProviders();
            } else if (role === 'logistics') {
                logisticsSection.classList.remove('hidden-section');
                loadProvinces();
            }
        });
    });
    
    // Gender Selection
    genderChips.forEach(chip => {
        chip.addEventListener('click', function() {
            genderChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            genderInput.value = this.dataset.gender;
        });
    });
    
    // Vehicle Type Selection
    wheelChips.forEach(chip => {
        chip.addEventListener('click', function() {
            wheelChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            vehicleTypeInput.value = this.dataset.wheel;
        });
    });
    
    // Password Toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
    });
    
    // File Upload Previews
    setupFilePreview('sellerIdUpload', 'sellerIdPreview');
    setupFilePreview('sellerPermitUpload', 'sellerPermitPreview');
    setupFilePreview('driverLicenseUpload', 'driverLicensePreview');
    setupFilePreview('vehicleRegUpload', 'vehicleRegPreview');
    setupFilePreview('logisticsPermitUpload', 'logisticsPermitPreview');
    
    // Form submission - copy password to confirmation
    document.getElementById('registrationForm').addEventListener('submit', function() {
        document.getElementById('passwordConfirmation').value = document.getElementById('password').value;
        document.getElementById('submitBtn').innerHTML = '<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mx-auto"></div>';
        document.getElementById('submitBtn').disabled = true;
    });
});

function setupFilePreview(inputId, previewId) {
    document.getElementById(inputId).addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById(previewId);
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
}

// Load Logistics Providers for Courier
function loadLogisticsProviders() {
    fetch('/api/logistics-providers')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('logisticsProvidersList');
            if (data.length === 0) {
                container.innerHTML = '<p class="text-center text-sm text-gray-500 py-4">No logistics providers available yet.</p>';
                return;
            }
            
            container.innerHTML = data.map(provider => `
                <div class="provider-card" onclick="selectProvider(${provider.id}, this)">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-sm">${provider.name || ''}</p>
                            ${provider.hub_address ? `<p class="text-xs text-gray-500">${provider.hub_address}</p>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        })
        .catch(err => {
            document.getElementById('logisticsProvidersList').innerHTML = '<p class="text-center text-sm text-red-500 py-4">Failed to load providers.</p>';
        });
}

function selectProvider(id, element) {
    document.querySelectorAll('.provider-card').forEach(card => card.classList.remove('active'));
    element.classList.add('active');
    document.getElementById('logisticsIdInput').value = id;
}

// Load Provinces for Logistics
function loadProvinces() {
    fetch('https://psgc.gitlab.io/api/provinces/')
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('logisticsProvince');
            select.innerHTML = '<option value="">Select Province</option>' +
                data.sort((a, b) => a.name.localeCompare(b.name))
                    .map(p => `<option value="${p.name}" data-code="${p.code}">${p.name}</option>`)
                    .join('');
        });
}

// Load Municipalities when province changes
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('logisticsProvince')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const code = selectedOption.dataset.code;
        
        if (!code) return;
        
        const munSelect = document.getElementById('logisticsMunicipality');
        munSelect.disabled = true;
        munSelect.innerHTML = '<option value="">Loading...</option>';
        
        Promise.all([
            fetch(`https://psgc.gitlab.io/api/provinces/${code}/municipalities/`).then(r => r.json()),
            fetch(`https://psgc.gitlab.io/api/provinces/${code}/cities/`).then(r => r.json())
        ]).then(([muns, cities]) => {
            const combined = [...muns, ...cities].sort((a, b) => a.name.localeCompare(b.name));
            munSelect.innerHTML = '<option value="">Select Municipality / City</option>' +
                combined.map(m => `<option value="${m.name}" data-code="${m.code}">${m.name}</option>`).join('');
            munSelect.disabled = false;
        });
    });
    
    document.getElementById('logisticsMunicipality')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const code = selectedOption.dataset.code;
        
        if (!code) return;
        
        const brgySelect = document.getElementById('logisticsBarangay');
        brgySelect.disabled = true;
        brgySelect.innerHTML = '<option value="">Loading...</option>';
        
        fetch(`https://psgc.gitlab.io/api/municipalities/${code}/barangays/`)
            .then(res => res.json())
            .then(data => {
                brgySelect.innerHTML = '<option value="">Select Barangay</option>' +
                    data.sort((a, b) => a.name.localeCompare(b.name))
                        .map(b => `<option value="${b.name}">${b.name}</option>`)
                        .join('');
                brgySelect.disabled = false;
            });
    });
});
</script>
</body>
</html>