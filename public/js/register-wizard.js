// Registration form as a step-by-step wizard:
// 1) pick the account type, then 2) Personal → Address → Security → the role's own step.
// Each "Next" checks only the current step; the server stays the source of truth.
(function () {
    'use strict';

    var BASE_STEPS = ['type', 'personal', 'address', 'security'];
    var ROLE_STEPS = { seller: 'seller', courier: 'courier', logistics: 'logistics' };

    var form, roleInput, sections = {}, steps = [], current = 0;

    function stepsFor(role) {
        return ROLE_STEPS[role] ? BASE_STEPS.concat(ROLE_STEPS[role]) : BASE_STEPS.slice();
    }

    function role() { return roleInput.value; }

    /* ── Rendering ─────────────────────────────────────────────── */

    function render() {
        steps = stepsFor(role());
        current = Math.max(0, Math.min(current, steps.length - 1));

        Object.keys(sections).forEach(function (key) {
            sections[key].classList.toggle('is-current', key === steps[current]);
        });
        steps.forEach(function (key, i) {
            var num = sections[key].querySelector('.card-num');
            if (num) num.textContent = i + 1;
        });

        var last = current === steps.length - 1;
        document.getElementById('wizStepText').textContent = 'Step ' + (current + 1) + ' of ' + steps.length;
        document.getElementById('wizStepName').textContent = sections[steps[current]].dataset.title;
        document.getElementById('wizBarFill').style.width = ((current + 1) / steps.length * 100) + '%';
        document.getElementById('wizDots').innerHTML = steps.map(function (key, i) {
            var cls = i < current ? 'is-done' : (i === current ? 'is-current' : '');
            return '<li class="' + cls + '"><span class="dot">' + (i < current ? '✓' : i + 1) + '</span>'
                 + '<span class="lbl">' + sections[key].dataset.title + '</span></li>';
        }).join('');

        document.getElementById('wizBack').hidden = current === 0;
        document.getElementById('wizNext').hidden = last;
        document.getElementById('submitBtn').hidden = !last;
        document.getElementById('wizTerms').hidden = !last;
        document.querySelectorAll('[data-wiz-terms-error]').forEach(function (el) { el.hidden = !last; });
    }

    function go(index, scroll) {
        current = index;
        render();
        if (scroll !== false) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /* ── Step checks ───────────────────────────────────────────── */

    function labelOf(el) {
        var box = el.closest('div');
        var lbl = box && box.querySelector('.fld-label');
        return lbl ? lbl.textContent.replace(/\*|\(.*\)/g, '').trim() : 'This field';
    }

    function errorSlot(el) {
        var named = el.id && document.querySelector('[data-error-for="' + el.id + '"]');
        if (named) return named;
        var box = el.closest('div');
        var slot = box.querySelector(':scope > .field-error');
        if (!slot) {
            slot = document.createElement('p');
            slot.className = 'field-error';
            box.appendChild(slot);
        }
        return slot;
    }

    function markTarget(el) {
        // File inputs are hidden; mark the visible upload box instead.
        return el.type === 'file' ? (el.closest('div').querySelector('.file-upload-box') || el) : el;
    }

    function setError(el, msg) {
        errorSlot(el).textContent = msg;
        markTarget(el).classList.toggle('is-invalid', !!msg);
        if (msg && !el.dataset.wizWatch) {
            el.dataset.wizWatch = '1';
            ['input', 'change'].forEach(function (ev) {
                el.addEventListener(ev, function () { setError(el, ''); });
            });
        }
        return !msg;
    }

    // Hidden by an inline style between the field and its step (e.g. DTI vs SEC, buyer-only ID)?
    function isSkipped(el, step) {
        for (var n = el.parentElement; n && n !== step; n = n.parentElement) {
            if (n.style.display === 'none') return true;
        }
        return false;
    }

    function isEmpty(el) {
        if (el.type === 'file') return !(el.files && el.files.length);
        return !String(el.value || '').trim();
    }

    // Seller-only rules that mirror SellerApplicationRules.
    var SELLER_CHECKS = {
        zip_code: function (v) { return /^\d{4}$/.test(v) ? '' : 'Please enter a 4-digit postal code.'; },
        password: function (v) {
            return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/.test(v) ? ''
                : 'Sellers need at least 8 characters with an uppercase letter, a lowercase letter, a number and a symbol (e.g. Tindahan@2026).';
        },
        shop_name: function (v) { return v.trim().length >= 3 && v.trim().length <= 30 ? '' : 'Shop name must be 3–30 characters.'; },
        tin: function (v) { return /^\d{3}-?\d{3}-?\d{3}-?\d{3}$/.test(v.trim()) ? '' : 'Please enter a valid 12-digit TIN (000-000-000-000).'; },
    };

    function validateStep(index) {
        var key = stepsFor(role())[index];
        var step = sections[key];
        var ok = true, first = null;

        function fail(el) { ok = false; if (!first) first = el; }

        if (key === 'type' && !role()) {
            document.querySelector('[data-error-for="roleInput"]').textContent = 'Please choose an account type to continue.';
            return false;
        }

        // Live rules shared with register-validation.js (names, email, mobile, password length).
        if (window.RegisterValidation) {
            window.RegisterValidation.ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el && step.contains(el) && !window.RegisterValidation.check(id)) fail(el);
            });
        }

        step.querySelectorAll('[required]').forEach(function (el) {
            if (isSkipped(el, step) || el.type === 'checkbox') return;
            if (window.RegisterValidation && window.RegisterValidation.ids.indexOf(el.id) !== -1) return;

            var msg = '';
            if (isEmpty(el)) {
                var label = labelOf(el);
                msg = el.type === 'file' ? 'Please upload your ' + label + '.'
                    : el.tagName === 'SELECT' ? 'Please select a ' + label + '.'
                    : label + ' is required.';
            } else if (role() === 'seller' && SELLER_CHECKS[el.name]) {
                msg = SELLER_CHECKS[el.name](el.value);
            }
            if (!setError(el, msg)) fail(el);
        });

        // Seller checks on fields that aren't "required" for everyone (ZIP, strong password).
        if (role() === 'seller') {
            ['zip_code', 'password'].forEach(function (name) {
                var el = step.querySelector('[name="' + name + '"]');
                if (!el || el.required && isEmpty(el)) return;
                if (name === 'password' && isEmpty(el)) return; // already reported above
                if (!setError(el, SELLER_CHECKS[name](el.value))) fail(el);
            });
        }

        if (first) {
            (markTarget(first).focus ? markTarget(first) : first).focus({ preventScroll: true });
            markTarget(first).scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return ok;
    }

    /* ── Public helpers ────────────────────────────────────────── */

    function stepIndexOf(el) {
        var sec = el.closest('.wiz-step');
        return sec ? stepsFor(role()).indexOf(sec.dataset.step) : -1;
    }

    window.RegisterWizard = {
        showStepOf: function (el) {
            var i = stepIndexOf(el);
            if (i !== -1 && i !== current) go(i, false);
        },
    };

    /* ── Wiring ────────────────────────────────────────────────── */

    document.addEventListener('DOMContentLoaded', function () {
        form = document.getElementById('registrationForm');
        roleInput = document.getElementById('roleInput');
        if (!form || !roleInput) return;

        document.querySelectorAll('.wiz-step').forEach(function (sec) { sections[sec.dataset.step] = sec; });

        // Choosing an account type (a real click) moves straight on to the next step.
        document.querySelectorAll('.role-chip').forEach(function (chip) {
            chip.addEventListener('click', function (e) {
                document.querySelector('[data-error-for="roleInput"]').textContent = '';
                if (e.isTrusted) go(1);
                else render();
            });
        });

        document.getElementById('wizNext').addEventListener('click', function () {
            if (validateStep(current)) go(current + 1);
        });
        document.getElementById('wizBack').addEventListener('click', function () { go(current - 1); });

        // Enter in a text field means "Next" until the last step.
        form.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.type === 'submit') return;
            if (current < steps.length - 1) {
                e.preventDefault();
                document.getElementById('wizNext').click();
            }
        });

        // Final check of every step; jump to the first one that still has a problem.
        // (Capture phase, so it runs before the spinner handler on the form.)
        document.addEventListener('submit', function (e) {
            if (e.target !== form) return;
            for (var i = 0; i < stepsFor(role()).length; i++) {
                if (!validateStep(i)) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    go(i, false);
                    validateStep(i); // re-run so focus lands on the now-visible field
                    return;
                }
            }
        }, true);

        // Where to start: the step with a server error, else Personal if a role came
        // in the link (/register?role=seller), else the account-type step.
        var start = role() ? 1 : 0;
        var bad = Array.prototype.find.call(
            form.querySelectorAll('.wiz-step .field-error, .wiz-step .is-invalid'),
            function (el) { return el.classList.contains('is-invalid') || el.textContent.trim(); }
        );
        if (bad) {
            start = Math.max(0, stepIndexOf(bad));
        } else if (document.querySelector('[data-wiz-terms-error]')) {
            start = stepsFor(role()).length - 1;
        }
        go(start, false);
    });
})();
