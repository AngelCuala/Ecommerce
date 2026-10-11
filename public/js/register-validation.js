// Registration form — live field checks that mirror the server-side rules
// in RegisteredUserController. The server stays the source of truth.
(function () {
    'use strict';

    var NAME_RE   = /^\p{L}+( \p{L}+)*$/u;
    var MI_RE     = /^\p{L}{1,2}\.?$/u;
    var EMAIL_RE  = /^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/;
    var MOBILE_RE = /^09\d{9}$/;

    function nameCheck(label) {
        return function (v) {
            if (!v) return label + ' is required.';
            if (!NAME_RE.test(v)) return label + ' may only contain letters and spaces — no numbers or symbols.';
            if (v.length < 2) return label + ' must be at least 2 characters.';
            if (v.length > 50) return label + ' must not be longer than 50 characters.';
            return '';
        };
    }

    var rules = {
        firstName: nameCheck('First name'),
        lastName:  nameCheck('Last name'),
        middleName: function (v) {
            if (!v) return '';
            return MI_RE.test(v) ? '' : 'Middle initial may only contain letters (e.g. "M" or "M.").';
        },
        email: function (v) {
            if (!v) return 'Email address is required.';
            return EMAIL_RE.test(v) ? '' : 'Please enter a valid email address (e.g. juan@gmail.com).';
        },
        contactNo: function (v) {
            if (!v) return 'Mobile number is required.';
            return MOBILE_RE.test(v) ? '' : 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).';
        },
        password: function (v) {
            if (!v) return 'Password is required.';
            return v.length >= 8 ? '' : 'Password must be at least 8 characters.';
        },
    };

    function errorEl(id) {
        return document.querySelector('[data-error-for="' + id + '"]');
    }

    function check(id) {
        var input = document.getElementById(id);
        if (!input) return true;
        var value = id === 'password' ? input.value : input.value.trim();
        var msg = rules[id](value);
        var el = errorEl(id);
        if (el) el.textContent = msg;
        input.classList.toggle('is-invalid', !!msg);
        return !msg;
    }

    // Used by register-wizard.js to check one step's fields before "Next".
    window.RegisterValidation = { check: check, ids: Object.keys(rules) };

    document.addEventListener('DOMContentLoaded', function () {
        Object.keys(rules).forEach(function (id) {
            var input = document.getElementById(id);
            if (!input) return;
            // Server errors rendered on load → mark the field red.
            var el = errorEl(id);
            if (el && el.textContent.trim()) input.classList.add('is-invalid');

            input.addEventListener('blur', function () { if (input.value !== '') check(id); });
            input.addEventListener('input', function () {
                if (input.classList.contains('is-invalid')) check(id);
            });
        });
    });

    // Capture phase on document runs before the form's own submit handler
    // (which shows the loading spinner), so an invalid form never shows it.
    document.addEventListener('submit', function (e) {
        if (!e.target || e.target.id !== 'registrationForm') return;
        var firstBad = null;
        Object.keys(rules).forEach(function (id) {
            if (!check(id) && !firstBad) firstBad = document.getElementById(id);
        });
        if (firstBad) {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (window.RegisterWizard) window.RegisterWizard.showStepOf(firstBad);
            firstBad.focus();
            firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);
})();
