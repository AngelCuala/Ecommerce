// Platform Settings — tab switching (Announcements / Policies)
document.addEventListener('DOMContentLoaded', function () {
    const tabs    = document.querySelectorAll('.settings-tab');
    const contents = document.querySelectorAll('.tab-content');

    function switchTab(name) {
        tabs.forEach(t => {
            const active = t.dataset.tab === name;
            t.style.borderColor = active ? '#fa4e1c' : 'transparent';
            t.style.color       = active ? '#fa4e1c' : '#6b90aa';
        });
        contents.forEach(c => c.classList.toggle('hidden', c.id !== 'tab-' + name));
    }

    tabs.forEach(tab => tab.addEventListener('click', () => switchTab(tab.dataset.tab)));

    // Open the correct tab on page load (e.g. after saving)
    const hash = window.location.hash.replace('#', '') || 'announcements';
    switchTab(hash);
    tabs.forEach(t => t.addEventListener('click', () => {
        history.replaceState(null, '', '#' + t.dataset.tab);
    }));
});
