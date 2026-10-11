// Region → Province → Municipality/City → Barangay cascade for the registration
// form, used only when the full PSGC script (psgc-address.js) isn't installed.
// Reads the /api/psgc endpoints served by PsgcController.
window.RegisterAddressFallback = (function () {
    'use strict';

    var DIRECT = 'https://psgc.gitlab.io/api';

    function getJson(url) {
        return fetch(url, { headers: { Accept: 'application/json' } }).then(function (r) {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        });
    }

    function fill(select, items, placeholder, selectedName) {
        select.innerHTML = '<option value="">' + placeholder + '</option>' + items
            .slice()
            .sort(function (a, b) { return a.name.localeCompare(b.name); })
            .map(function (i) {
                var sel = selectedName && i.name === selectedName ? ' selected' : '';
                return '<option value="' + i.name.replace(/"/g, '&quot;') + '" data-code="' + i.code + '"' + sel + '>' + i.name + '</option>';
            }).join('');
        select.disabled = false;
    }

    function reset(select, placeholder) {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        select.disabled = true;
    }

    function code(select) {
        var opt = select.options[select.selectedIndex];
        return opt ? opt.getAttribute('data-code') : null;
    }

    function attach(opts) {
        var region   = document.querySelector(opts.region);
        var province = document.querySelector(opts.province);
        var city     = document.querySelector(opts.city);
        var barangay = document.querySelector(opts.barangay);
        var provCode = document.querySelector(opts.provinceCode);
        var cityCode = document.querySelector(opts.cityCode);
        var old      = opts.old || {};
        if (!region || !province || !city || !barangay) return;

        function loadBarangays(selected) {
            var c = code(city);
            if (cityCode) cityCode.value = c || '';
            reset(barangay, 'Loading…');
            if (!c) return reset(barangay, '— Select Municipality first —');
            getJson('/api/psgc/municipalities/' + c + '/barangays')
                .then(function (list) { fill(barangay, list, '— Select Barangay —', selected); })
                .catch(function () { reset(barangay, 'Could not load barangays'); });
        }

        function loadCities(selected, selectedBrgy) {
            var c = code(province);
            if (provCode) provCode.value = c || '';
            reset(barangay, '— Select Municipality first —');
            reset(city, 'Loading…');
            if (!c) return reset(city, '— Select Province first —');

            var req = province.getAttribute('data-no-province') === '1'
                ? getJson(DIRECT + '/regions/' + c + '/cities-municipalities/')
                : getJson('/api/psgc/provinces/' + c + '/municipalities');
            req.then(function (list) {
                fill(city, list, '— Select Municipality / City —', selected);
                if (selected) loadBarangays(selectedBrgy);
            }).catch(function () { reset(city, 'Could not load municipalities'); });
        }

        function loadProvinces(selProv, selCity, selBrgy) {
            var c = code(region);
            reset(city, '— Select Province first —');
            reset(barangay, '— Select Municipality first —');
            province.removeAttribute('data-no-province');
            reset(province, 'Loading…');
            if (!c) return reset(province, '— Select Province —');

            getJson('/api/psgc/regions/' + c + '/provinces').then(function (list) {
                if (!list.length) {
                    // NCR has no provinces: the region itself stands in for the province.
                    var name = region.options[region.selectedIndex].text;
                    fill(province, [{ code: c, name: name }], '— Select Province —', name);
                    province.setAttribute('data-no-province', '1');
                    loadCities(selCity, selBrgy);
                    return;
                }
                fill(province, list, '— Select Province —', selProv);
                if (selProv) loadCities(selCity, selBrgy);
            }).catch(function () { reset(province, 'Could not load provinces'); });
        }

        region.addEventListener('change', function () { loadProvinces(); });
        province.addEventListener('change', function () { loadCities(); });
        city.addEventListener('change', function () { loadBarangays(); });

        getJson('/api/psgc/regions').then(function (list) {
            fill(region, list, '— Select Region —', old.region);
            if (old.region) loadProvinces(old.province, old.municipality, old.barangay);
        }).catch(function () { reset(region, 'Could not load regions'); region.disabled = false; });
    }

    return { attach: attach };
})();
