// Seller account — avatar live preview
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Show filename
    document.getElementById('avatar-filename').textContent = file.name;

    // Live preview
    const reader = new FileReader();
    reader.onload = function (e) {
        // Remove initial letter span if present
        const initial = document.getElementById('avatar-initial');
        if (initial) initial.remove();

        // Update or create the img tag
        let img = document.getElementById('avatar-preview');
        if (!img) {
            img = document.createElement('img');
            img.id = 'avatar-preview';
            img.className = 'h-24 w-24 rounded-full object-cover';
            img.alt = 'Profile photo';
            input.closest('form').querySelector('.rounded-full').appendChild(img);
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}
