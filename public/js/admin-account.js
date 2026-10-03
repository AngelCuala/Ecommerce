// Admin account — toggle between read view and edit form + avatar preview
function toggleEdit(show) {
    document.getElementById('info-view').classList.toggle('hidden', show);
    document.getElementById('info-edit').classList.toggle('hidden', !show);
    if (show) document.getElementById('info-edit').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    document.getElementById('avatar-filename').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (e) {
        var initial = document.getElementById('avatar-initial');
        if (initial) initial.remove();
        var img = document.getElementById('avatar-preview');
        if (!img) {
            img = document.createElement('img');
            img.id = 'avatar-preview';
            img.className = 'h-20 w-20 rounded-full object-cover';
            img.alt = 'Profile photo';
            input.closest('form').querySelector('.rounded-full').appendChild(img);
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}
