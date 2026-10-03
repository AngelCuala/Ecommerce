// Buyer profile — toggle between read view and edit form + avatar preview
function toggleEdit(show) {
    document.getElementById('info-view').classList.toggle('hidden', show);
    document.getElementById('info-edit').classList.toggle('hidden', !show);
    if (show) document.getElementById('info-edit').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function previewProfileAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    document.getElementById('avatar-filename').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (e) {
        var initial = document.getElementById('avatar-initial');
        if (initial) initial.style.display = 'none';
        var img = document.getElementById('avatar-preview');
        img.src = e.target.result;
        img.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}
