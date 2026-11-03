// Buyer profile — toggle between read view and edit form (the photo saves itself: photo-upload.js)
function toggleEdit(show) {
    document.getElementById('info-view').classList.toggle('hidden', show);
    document.getElementById('info-edit').classList.toggle('hidden', !show);
    if (show) document.getElementById('info-edit').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
