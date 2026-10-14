{{--
    Add / edit an address. Region → Province → City → Barangay come from the PSGC lists:
    each list only shows the places under the one above, and changing one clears the ones below.
--}}
<div id="address-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(34,24,18,.55);backdrop-filter:blur(2px);">
    <div class="pf-card w-full max-w-lg overflow-hidden" style="max-height:92vh;overflow-y:auto;">
        <div class="flex items-center justify-between gap-3 border-b px-6 py-4" style="border-color:#F0E4DC;">
            <h3 class="pf-card-title" id="address-modal-title">Add address</h3>
            <button type="button" onclick="closeAddressModal()" class="text-2xl leading-none" style="color:#8a7a70;" aria-label="Close">&times;</button>
        </div>
        <form id="address-form" action="{{ route('addresses.store') }}" method="POST" class="grid gap-4 p-6 sm:grid-cols-2"
              data-store-url="{{ route('addresses.store') }}" data-update-url="{{ url('profile/addresses') }}">
            @csrf
            <input type="hidden" name="_method" value="POST" id="addr-method">
            <input type="hidden" name="return" value="{{ request('return') }}">
            <input type="hidden" name="province_code" id="addr-province-code" value="{{ old('province_code') }}">
            <input type="hidden" name="municipality_code" id="addr-municipality-code" value="{{ old('municipality_code') }}">
            <input type="hidden" name="barangay_code" id="addr-barangay-code" value="{{ old('barangay_code') }}">
            <input type="hidden" name="region_code" id="addr-region-code" value="{{ old('region_code') }}">

            <div>
                <label class="pf-label" for="addr-label">Label</label>
                <select name="label" id="addr-label" class="pf-input">
                    @foreach (['Home', 'Work', 'Office', 'Province', 'Other'] as $l)<option @selected(old('label') === $l)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="pf-label" for="addr-name">Recipient name</label>
                <input type="text" name="full_name" id="addr-name" value="{{ old('full_name', auth()->user()->name) }}" class="pf-input" required maxlength="255">
            </div>
            <div class="sm:col-span-2">
                <label class="pf-label" for="addr-phone">Mobile number</label>
                <input type="tel" name="phone" id="addr-phone" value="{{ old('phone', auth()->user()->phone) }}" class="pf-input" required placeholder="09XXXXXXXXX or +639XXXXXXXXX">
            </div>

            <div class="sm:col-span-2">
                <label class="pf-label" for="addr-region">Region</label>
                <select name="region" id="addr-region" class="pf-input" required><option value="">Loading…</option></select>
            </div>
            <div>
                <label class="pf-label" for="addr-province">Province</label>
                <select name="province" id="addr-province" class="pf-input" required disabled><option value="">Choose a region first</option></select>
            </div>
            <div>
                <label class="pf-label" for="addr-city">City / Municipality</label>
                <select name="city" id="addr-city" class="pf-input" required disabled><option value="">Choose a province first</option></select>
            </div>
            <div>
                <label class="pf-label" for="addr-barangay">Barangay</label>
                <select name="barangay" id="addr-barangay" class="pf-input" required disabled><option value="">Choose a city first</option></select>
            </div>
            <div>
                <label class="pf-label" for="addr-zip">ZIP code</label>
                <input type="text" name="zip" id="addr-zip" value="{{ old('zip') }}" class="pf-input" maxlength="10" placeholder="e.g. 1100">
            </div>
            <div class="sm:col-span-2">
                <label class="pf-label" for="addr-line">House no., building, street</label>
                <input type="text" name="address_line" id="addr-line" value="{{ old('address_line') }}" class="pf-input" required maxlength="255" placeholder="e.g. 45 Kalayaan Ave.">
            </div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input type="checkbox" name="is_default" value="1" id="addr-default" class="h-4 w-4 accent-orange-500" @checked(old('is_default'))>
                Set as my default address
            </label>

            <div class="flex justify-end gap-3 border-t pt-5 sm:col-span-2" style="border-color:#F0E4DC;">
                <button type="button" onclick="closeAddressModal()" class="pf-btn-ghost">Cancel</button>
                <button type="submit" class="pf-btn px-6">Save address</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var API = '/api/psgc';
    var modal = document.getElementById('address-modal');
    var form = document.getElementById('address-form');
    var $ = function (id) { return document.getElementById(id); };
    var sel = { region: $('addr-region'), province: $('addr-province'), city: $('addr-city'), barangay: $('addr-barangay') };
    var codes = { region: $('addr-region-code'), province: $('addr-province-code'), city: $('addr-municipality-code'), barangay: $('addr-barangay-code') };

    function fill(select, items, placeholder, wanted) {
        select.innerHTML = '';
        select.add(new Option(placeholder, ''));
        items.forEach(function (i) {
            var o = new Option(i.name, i.name);
            o.dataset.code = i.code;
            if (wanted && (i.code === wanted.code || i.name === wanted.name)) o.selected = true;
            select.add(o);
        });
        select.disabled = false;
    }
    function reset(select, placeholder) {
        select.innerHTML = '';
        select.add(new Option(placeholder, ''));
        select.disabled = true;
    }
    function codeOf(select) {
        var o = select.options[select.selectedIndex];
        return o && o.value ? (o.dataset.code || '') : '';
    }
    function get(url) { return fetch(url).then(function (r) { return r.json(); }); }

    // Each level only lists what is under the level above; changing a level clears the ones below.
    function loadProvinces(want) {
        reset(sel.province, 'Loading…'); reset(sel.city, 'Choose a province first'); reset(sel.barangay, 'Choose a city first');
        sync();
        var region = codeOf(sel.region);
        if (!region) { reset(sel.province, 'Choose a region first'); return Promise.resolve(); }
        return get(API + '/regions/' + region + '/provinces').then(function (list) {
            if (!list.length) {   // NCR has no provinces: its cities hang off the region
                sel.province.innerHTML = '';
                var o = new Option('Metro Manila', 'Metro Manila'); o.dataset.code = ''; o.dataset.region = region; o.selected = true;
                sel.province.add(o);
                sel.province.disabled = false;
                return loadCities(want, region);
            }
            fill(sel.province, list, 'Choose a province', want && want.province);
            if (want && want.province) return loadCities(want);
        });
    }
    function loadCities(want, regionCode) {
        reset(sel.city, 'Loading…'); reset(sel.barangay, 'Choose a city first');
        sync();
        var parent = regionCode || codeOf(sel.province) || (sel.province.options[sel.province.selectedIndex] || {}).dataset?.region;
        if (!parent) { reset(sel.city, 'Choose a province first'); return Promise.resolve(); }
        return get(API + '/provinces/' + parent + '/municipalities').then(function (list) {
            fill(sel.city, list, 'Choose a city / municipality', want && want.city);
            if (want && want.city) return loadBarangays(want);
        });
    }
    function loadBarangays(want) {
        reset(sel.barangay, 'Loading…');
        sync();
        var city = codeOf(sel.city);
        if (!city) { reset(sel.barangay, 'Choose a city first'); return Promise.resolve(); }
        return get(API + '/municipalities/' + city + '/barangays').then(function (list) {
            fill(sel.barangay, list, 'Choose a barangay', want && want.barangay);
            sync();
        });
    }
    function sync() {
        codes.region.value = codeOf(sel.region);
        codes.province.value = codeOf(sel.province);
        codes.city.value = codeOf(sel.city);
        codes.barangay.value = codeOf(sel.barangay);
    }

    sel.region.addEventListener('change', function () { loadProvinces(); });
    sel.province.addEventListener('change', function () { loadCities(); });
    sel.city.addEventListener('change', function () { loadBarangays(); });
    sel.barangay.addEventListener('change', sync);
    form.addEventListener('submit', sync);

    function loadAll(want) {
        reset(sel.region, 'Loading…');
        return get(API + '/regions').then(function (list) {
            fill(sel.region, list, 'Choose a region', want && want.region);
            if (want && sel.region.value) return loadProvinces(want);
        });
    }

    window.openAddressModal = function (a) {
        var editing = !!(a && a.id);
        $('address-modal-title').textContent = editing ? 'Edit address' : 'Add address';
        form.action = editing ? form.dataset.updateUrl + '/' + a.id : form.dataset.storeUrl;
        $('addr-method').value = editing ? 'PATCH' : 'POST';
        if (a) {
            $('addr-label').value = a.label || 'Home';
            $('addr-name').value = a.full_name || '';
            $('addr-phone').value = a.phone || '';
            $('addr-zip').value = a.zip || '';
            $('addr-line').value = a.address_line || '';
            $('addr-default').checked = !!a.is_default;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadAll(a ? {
            region:   { name: a.region, code: a.region_code },
            province: { name: a.province, code: a.province_code },
            city:     { name: a.city, code: a.municipality_code },
            barangay: { name: a.barangay, code: a.barangay_code },
        } : null);
    };
    window.closeAddressModal = function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    @if ($errors->any() && old('address_line') !== null)
        // A failed save: reopen with what was typed.
        openAddressModal(@json(old() + ['id' => null]));
    @endif
    @if (request('return') === 'checkout' && auth()->user()->addresses()->doesntExist())
        openAddressModal();
    @endif
})();
</script>
