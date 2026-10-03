// Courier account — avatar photo picker + live preview.
// Binds to an <input type="file" data-avatar-input> and shows the chosen
// filename + an instant image preview inside the avatar circle.
(function () {
    var input = document.querySelector('[data-avatar-input]');
    if (!input) return;

    input.addEventListener('change', function () {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];

        var nameEl = document.querySelector('[data-avatar-filename]');
        if (nameEl) nameEl.textContent = file.name;

        var reader = new FileReader();
        reader.onload = function (e) {
            var initial = document.querySelector('[data-avatar-initial]');
            if (initial) initial.remove();

            var img = document.querySelector('[data-avatar-preview]');
            if (!img) {
                var ring = input.closest('.cx-avatar-ring') || input.closest('.group');
                var circle = ring ? ring.querySelector('.cx-avatar-circle') : null;
                if (!circle) return;
                img = document.createElement('img');
                img.setAttribute('data-avatar-preview', '');
                img.className = 'cx-avatar-img';
                img.alt = 'Profile photo';
                circle.appendChild(img);
            }
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
})();
