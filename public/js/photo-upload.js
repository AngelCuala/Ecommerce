// Profile photo: saved as soon as it is chosen (POST /account/photo), on every account page.
// Markup: <input type="file" data-photo-upload data-photo-url="…">; the page's <img data-photo-preview>
// show the new photo, [data-photo-initial] hide, and [data-photo-status] tells how it went.
(function () {
    'use strict';

    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content
        || (document.querySelector('input[name="_token"]') || {}).value;

    function status(text, ok) {
        document.querySelectorAll('[data-photo-status]').forEach(function (el) {
            el.textContent = text;
            el.style.color = ok === false ? '#B91C1C' : (ok ? '#059669' : '');
        });
    }

    document.querySelectorAll('input[type="file"][data-photo-upload]').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) return;
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { status('The photo must be a JPG, PNG or WebP image.', false); input.value = ''; return; }
            if (file.size > 5 * 1024 * 1024) { status('The photo can be at most 5 MB.', false); input.value = ''; return; }

            // Show it right away; it is saved in the background.
            var local = URL.createObjectURL(file);
            document.querySelectorAll('[data-photo-initial]').forEach(function (el) { el.style.display = 'none'; });
            document.querySelectorAll('img[data-photo-preview]').forEach(function (img) { img.src = local; img.classList.remove('hidden'); img.style.display = ''; });
            status('Saving photo…');

            var data = new FormData();
            data.append('avatar', file);
            fetch(input.dataset.photoUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: data,
                credentials: 'same-origin',
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; });
            }).then(function (res) {
                if (res.ok) {
                    status(res.d.message || 'Profile photo updated.', true);
                } else {
                    var err = res.d.errors && res.d.errors.avatar ? res.d.errors.avatar[0] : (res.d.message || 'The photo could not be saved. Please try again.');
                    status(err, false);
                }
                input.value = '';
            }).catch(function () {
                status('The photo could not be saved. Please check your connection and try again.', false);
                input.value = '';
            });
        });
    });
})();
