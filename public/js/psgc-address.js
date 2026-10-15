/*
 * Shared Philippine address dropdowns (Region → Province → City/Municipality → Barangay)
 * backed by the app's local PSA PSGC dataset (/api/psgc/...). No external API.
 *
 * Usage:
 *   PsgcAddress.attach({
 *     region:   '#sel-region',     // optional — without it the province list is nationwide
 *     province: '#sel-province',
 *     city:     '#sel-city',
 *     barangay: '#sel-barangay',   // optional
 *     old:      { region, province, municipality, barangay },  // names or codes to preselect
 *   });
 *
 * Option values stay the readable names (what the forms already submit). Hidden inputs
 * are kept in sync inside the form (created if missing):
 *   region_code, province_code, municipality_code, barangay_code   — 9-digit Correspondence Codes
 *   region_psgc, province_psgc, municipality_psgc, barangay_psgc   — 10-digit PSGC codes
 * The server re-validates the whole chain; these values are never trusted on their own.
 */
(function () {
    'use strict';
    var BASE = '/api/psgc';

    function $(x) { return typeof x === 'string' ? document.querySelector(x) : x; }

    function getJson(url) {
        return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function attach(cfg) {
        var els = { region: $(cfg.region), province: $(cfg.province), city: $(cfg.city), barangay: $(cfg.barangay) };
        var form = (els.city || els.province).form;
        var old = cfg.old || {};
        var levels = ['region', 'province', 'city', 'barangay'].filter(function (l) { return els[l]; });

        // Keep each select's original placeholder text so the UI wording is unchanged.
        var placeholder = {};
        levels.forEach(function (l) {
            var first = els[l].options[0];
            placeholder[l] = (cfg.placeholders && cfg.placeholders[l]) || (first ? first.textContent : '');
        });
        var loadingText = { region: 'Loading regions…', province: 'Loading provinces…', city: 'Loading cities…', barangay: 'Loading barangays…' };

        // Hidden code inputs
        var hiddenNames = { region: 'region', province: 'province', city: 'municipality', barangay: 'barangay' };
        var hidden = {};
        levels.forEach(function (l) {
            ['code', 'psgc'].forEach(function (kind) {
                var name = hiddenNames[l] + '_' + kind;
                var input = form.querySelector('input[name="' + name + '"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    form.appendChild(input);
                }
                hidden[name] = input;
            });
        });

        function reset(level, text, disabled) {
            var el = els[level];
            if (!el) return;
            el.innerHTML = '';
            var o = document.createElement('option');
            o.value = '';
            o.textContent = text != null ? text : placeholder[level];
            el.appendChild(o);
            el.disabled = disabled !== false;
        }

        function option(item) {
            var o = document.createElement('option');
            o.value = item.name;
            o.textContent = item.name;
            o.dataset.key = item.key || item.psgc || '';
            o.dataset.psgc = item.psgc || '';
            o.dataset.code = item.code || '';
            o.dataset.level = item.level || '';
            return o;
        }

        function populate(level, items) {
            var el = els[level];
            reset(level, placeholder[level], false);
            var groups = {};
            items.forEach(function (item) {
                if (level === 'barangay' && item.district) {
                    if (!groups[item.district]) {
                        groups[item.district] = document.createElement('optgroup');
                        groups[item.district].label = item.district;
                        el.appendChild(groups[item.district]);
                    }
                    groups[item.district].appendChild(option(item));
                } else {
                    el.appendChild(option(item));
                }
            });
            el.disabled = false;
        }

        function selected(level) {
            var el = els[level];
            if (!el || el.disabled) return null;
            var o = el.options[el.selectedIndex];
            return o && o.value ? o : null;
        }

        function sync() {
            levels.forEach(function (l) {
                var o = selected(l);
                // A "Highly Urbanized Cities" group is not a province: it has no codes.
                hidden[hiddenNames[l] + '_code'].value = o ? (o.dataset.code || '') : '';
                hidden[hiddenNames[l] + '_psgc'].value = o ? (o.dataset.psgc || '') : '';
            });
        }

        // Select an option matching a remembered name or code; returns true if found.
        function choose(level, want) {
            if (!want || !els[level]) return false;
            var w = String(want).trim().toLowerCase();
            var opts = els[level].querySelectorAll('option');
            for (var i = 0; i < opts.length; i++) {
                var o = opts[i];
                if (!o.value) continue;
                if (o.dataset.psgc === w || o.dataset.code === w || o.value.toLowerCase() === w) {
                    o.selected = true;
                    return true;
                }
            }
            return false;
        }

        function fail(level) {
            reset(level, 'Failed to load — try refreshing', false);
        }

        function loadBarangays(cityKey, want) {
            if (!els.barangay) { sync(); return Promise.resolve(); }
            reset('barangay', loadingText.barangay);
            if (!cityKey) { reset('barangay'); sync(); return Promise.resolve(); }
            return getJson(BASE + '/municipalities/' + encodeURIComponent(cityKey) + '/barangays')
                .then(function (list) { populate('barangay', list); choose('barangay', want); sync(); })
                .catch(function () { fail('barangay'); sync(); });
        }

        function loadCities(parentKey, want) {
            reset('city', loadingText.city);
            reset('barangay');
            if (!parentKey) { reset('city'); sync(); return Promise.resolve(); }
            return getJson(BASE + '/provinces/' + encodeURIComponent(parentKey) + '/municipalities')
                .then(function (list) {
                    populate('city', list);
                    if (choose('city', want)) return loadBarangays(selected('city').dataset.key, old.barangay);
                    sync();
                })
                .catch(function () { fail('city'); sync(); });
        }

        function loadProvincesOfRegion(regionKey, want) {
            reset('province', loadingText.province);
            reset('city');
            reset('barangay');
            if (!regionKey) { reset('province'); sync(); return Promise.resolve(); }
            return getJson(BASE + '/regions/' + encodeURIComponent(regionKey) + '/provinces')
                .then(function (list) {
                    if (!list.length) {
                        // NCR: no province level — cities are listed directly under the region.
                        reset('province', '— Not applicable (no province) —', true);
                        return loadCities(regionKey, old.municipality);
                    }
                    populate('province', list);
                    if (choose('province', want)) return loadCities(selected('province').dataset.key, old.municipality);
                    sync();
                })
                .catch(function () { fail('province'); sync(); });
        }

        function loadAllProvinces(want) {
            reset('province', loadingText.province);
            reset('city');
            reset('barangay');
            return getJson(BASE + '/provinces')
                .then(function (list) {
                    populate('province', list);
                    if (choose('province', want)) return loadCities(selected('province').dataset.key, old.municipality);
                    sync();
                })
                .catch(function () { fail('province'); sync(); });
        }

        // ── Change handlers ───────────────────────────────────
        if (els.region) {
            els.region.addEventListener('change', function () {
                old = {};
                var o = selected('region');
                loadProvincesOfRegion(o ? o.dataset.key : '');
            });
        }
        els.province.addEventListener('change', function () {
            old = {};
            var o = selected('province');
            loadCities(o ? o.dataset.key : '');
        });
        els.city.addEventListener('change', function () {
            old = {};
            var o = selected('city');
            loadBarangays(o ? o.dataset.key : '');
        });
        if (els.barangay) els.barangay.addEventListener('change', sync);
        form.addEventListener('submit', sync, true);

        // ── Initial load ──────────────────────────────────────
        function start(remembered) {
            old = remembered || {};
            if (els.region) {
                reset('province'); reset('city'); reset('barangay');
                reset('region', loadingText.region);
                return getJson(BASE + '/regions')
                    .then(function (list) {
                        populate('region', list);
                        if (choose('region', old.region)) return loadProvincesOfRegion(selected('region').dataset.key, old.province);
                        sync();
                    })
                    .catch(function () { fail('region'); sync(); });
            }
            return loadAllProvinces(old.province);
        }

        start(old);
        return { reload: start, sync: sync };
    }

    window.PsgcAddress = { attach: attach };
})();
