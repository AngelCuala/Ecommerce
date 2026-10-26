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
            <input type="hidden" name="latitude" id="addr-lat" value="{{ old('latitude') }}">
            <input type="hidden" name="longitude" id="addr-lon" value="{{ old('longitude') }}">

            {{-- Pin on the map: fills region → barangay, ZIP and street (not name, mobile or label). --}}
            <div class="sm:col-span-2">
                <div class="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                    <span class="pf-label !mb-0">Pin your location <span class="font-normal pf-muted">(optional)</span></span>
                    <button type="button" id="addr-locate" class="inline-flex items-center gap-1 text-xs font-semibold" style="color:#fa4e1c;">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                        Use my current location
                    </button>
                </div>
                <div class="relative">
                    <input type="search" id="addr-map-search" class="pf-input pr-20" placeholder="Search a place, e.g. SM Calamba" autocomplete="off" aria-label="Search for a place">
                    <button type="button" id="addr-map-search-btn" class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded-md px-3 py-1 text-xs font-semibold text-white" style="background:#fa4e1c;">Search</button>
                    <div id="addr-map-results" class="absolute left-0 right-0 top-full z-[1001] mt-1 hidden overflow-hidden rounded-lg border bg-white shadow-lg" style="border-color:#F0E4DC;"></div>
                </div>
                <div id="addr-map" class="mt-2 h-56 w-full overflow-hidden rounded-xl border" style="border-color:#E6D9CF;background:#F5F2EF;"></div>
                <p id="addr-map-status" class="mt-1.5 text-xs pf-muted">Tap the map or drag the pin to your house. We'll fill in the address below for you.</p>
            </div>
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
                <input type="tel" name="phone" id="addr-phone" value="{{ old('phone', auth()->user()->phone) }}" class="pf-input" required placeholder="09XXXXXXXXX or +639XXXXXXXXX" maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
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

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
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

    // ── Map pin → address ────────────────────────────────────────
    var PH_CENTER = [12.8797, 121.7740], map = null, pin = null, lookupSeq = 0;
    var status = $('addr-map-status');
    function say(text, tone) {
        status.textContent = text;
        status.style.color = tone === 'ok' ? '#047857' : tone === 'bad' ? '#B91C1C' : '';
    }
    function ensureMap() {
        if (map || !window.L) return map;
        map = L.map('addr-map', { zoomControl: true }).setView(PH_CENTER, 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        map.on('click', function (e) { placePin(e.latlng.lat, e.latlng.lng, true); });
        return map;
    }
    function placePin(lat, lon, lookup) {
        if (!ensureMap()) return;
        if (!pin) {
            pin = L.marker([lat, lon], { draggable: true }).addTo(map);
            pin.on('dragend', function () { var p = pin.getLatLng(); placePin(p.lat, p.lng, true); });
        } else {
            pin.setLatLng([lat, lon]);
        }
        $('addr-lat').value = lat.toFixed(7);
        $('addr-lon').value = lon.toFixed(7);
        if (map.getZoom() < 16) map.setView([lat, lon], 17); else map.panTo([lat, lon]);
        if (lookup) fillFromPin(lat, lon);
    }
    function fillFromPin(lat, lon) {
        var seq = ++lookupSeq;
        say('Finding the address…');
        fetch('/api/geo/reverse?lat=' + lat + '&lon=' + lon, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (seq !== lookupSeq) return;          // a newer pin replaced this one
                var d = res.d;
                if (!res.ok) { say(d.message || 'Couldn\'t look up that spot. Fill in the address below.', 'bad'); return; }
                if (d.zip) $('addr-zip').value = d.zip;
                if (d.street) $('addr-line').value = d.street;
                if (!d.region) { say('Pinned. We couldn\'t match the area to our lists, so please choose the region, city and barangay below.', 'bad'); return; }
                return loadAll({ region: d.region, province: d.province || {}, city: d.city || {}, barangay: d.barangay || {} }).then(function () {
                    var missing = !d.city ? 'city and barangay' : (!d.barangay ? 'barangay' : '');
                    say(missing
                        ? 'Filled what we could from the map. Please choose the ' + missing + ' below.'
                        : 'Address filled from the map. Check the house number and street.', missing ? 'bad' : 'ok');
                });
            })
            .catch(function () { if (seq === lookupSeq) say('Couldn\'t look up that spot. Fill in the address below.', 'bad'); });
    }

    $('addr-locate').addEventListener('click', function () {
        if (!navigator.geolocation) { say('Your browser can\'t share your location. Tap the map instead.', 'bad'); return; }
        say('Getting your location…');
        navigator.geolocation.getCurrentPosition(
            function (p) { placePin(p.coords.latitude, p.coords.longitude, true); },
            function () { say('We couldn\'t get your location. Allow location access, or tap the map instead.', 'bad'); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    var results = $('addr-map-results');
    function runSearch() {
        var q = $('addr-map-search').value.trim();
        if (q.length < 3) return;
        results.innerHTML = '<p class="px-3 py-2 text-xs pf-muted">Searching…</p>';
        results.classList.remove('hidden');
        fetch('/api/geo/search?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (list) {
                results.innerHTML = '';
                if (!Array.isArray(list) || !list.length) { results.innerHTML = '<p class="px-3 py-2 text-xs pf-muted">No places found. Try another name, or tap the map.</p>'; return; }
                list.forEach(function (place) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'block w-full border-b px-3 py-2 text-left text-xs hover:bg-[#fff5f3]';
                    b.style.borderColor = '#F5EBE4';
                    b.textContent = place.label;
                    b.addEventListener('click', function () {
                        results.classList.add('hidden');
                        placePin(place.lat, place.lon, true);
                    });
                    results.appendChild(b);
                });
            })
            .catch(function () { results.innerHTML = '<p class="px-3 py-2 text-xs pf-muted">Search is unavailable right now.</p>'; });
    }
    $('addr-map-search-btn').addEventListener('click', runSearch);
    $('addr-map-search').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); runSearch(); } });
    document.addEventListener('click', function (e) { if (!e.target.closest('#addr-map-results, #addr-map-search, #addr-map-search-btn')) results.classList.add('hidden'); });

    // Called when the modal opens: show the saved pin (editing), or the whole country.
    function openMap(a) {
        setTimeout(function () {
            if (!ensureMap()) { say('The map could not load. You can still fill in the address below.', 'bad'); return; }
            map.invalidateSize();
            lookupSeq++;
            var lat = a && parseFloat(a.latitude), lon = a && parseFloat(a.longitude);
            if (lat && lon) {
                placePin(lat, lon, false);
                say('This is the pin you saved. Drag it to change the address.');
            } else {
                if (pin) { map.removeLayer(pin); pin = null; }
                $('addr-lat').value = ''; $('addr-lon').value = '';
                map.setView(PH_CENTER, 5);
                say('Tap the map or drag the pin to your house. We\'ll fill in the address below for you.');
            }
        }, 60);
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
        openMap(a);
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
