// Reusable show/hide password toggle.
// Works on any password input marked with `data-password` that has a sibling
// button marked with `data-toggle-password` inside a relative wrapper.
(function () {
    var EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        // Set the initial icon (eye = password hidden)
        btn.innerHTML = EYE;
        btn.setAttribute('aria-label', 'Show password');

        btn.addEventListener('click', function () {
            var wrapper = btn.closest('.relative') || btn.parentElement;
            var input   = wrapper.querySelector('[data-password]');
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                btn.innerHTML = EYE_OFF;
                btn.setAttribute('aria-label', 'Hide password');
            } else {
                input.type = 'password';
                btn.innerHTML = EYE;
                btn.setAttribute('aria-label', 'Show password');
            }
        });
    });
})();
