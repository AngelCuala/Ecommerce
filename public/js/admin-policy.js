// Admin policy editor — toggle rendered preview
function togglePreview() {
    const box    = document.getElementById('preview-box');
    const editor = document.getElementById('policy-editor');
    const btn    = document.getElementById('toggle-preview');
    if (box.classList.toggle('hidden')) {
        btn.textContent = 'Preview';
    } else {
        box.innerHTML = editor.value;
        btn.textContent = 'Hide preview';
    }
}
