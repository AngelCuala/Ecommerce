// Seller orders — status filter tabs
document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.status-tab');
    const cards = document.querySelectorAll('.order-card');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const status = this.dataset.status;

            tabs.forEach(t => {
                t.style.borderColor = 'transparent';
                t.style.color = '#8A8A8A';
            });
            this.style.borderColor = '#fa4e1c';
            this.style.color = '#fa4e1c';

            cards.forEach(card => {
                card.style.display = (status === 'All' || card.dataset.status === status) ? '' : 'none';
            });
        });
    });
});
