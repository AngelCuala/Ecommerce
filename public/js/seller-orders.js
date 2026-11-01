// Seller orders — status filter tabs
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.status-tab');
    const cards = document.querySelectorAll('.order-card[data-status]');
    const empty = document.getElementById('noTabResults');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const status = this.dataset.status;

            tabs.forEach(t => t.classList.toggle('is-active', t === this));

            let shown = 0;
            cards.forEach(card => {
                const match = status === 'All' || card.dataset.status === status;
                card.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            if (empty) empty.classList.toggle('hidden', shown > 0);
        });
    });
});
