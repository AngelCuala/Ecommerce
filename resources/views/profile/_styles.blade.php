{{-- Shared styles for the buyer account (profile) pages --}}
<style>
    .pf-page { background: linear-gradient(180deg, #fbeee8 0, #F5F5F5 320px); }

    /* Cards */
    .pf-card {
        background: #fff;
        border: 1px solid #F0E4DC;
        border-radius: 1.25rem;
        box-shadow: 0 1px 2px rgba(58, 47, 41, .04), 0 10px 30px -18px rgba(58, 47, 41, .18);
    }
    .pf-card-title {
        font-family: 'Nunito', ui-sans-serif, system-ui;
        font-weight: 800;
        font-size: 1.05rem;
        color: #222;
    }
    .pf-muted { color: #8a7a70; }
    .pf-text  { color: #3a2f29; }

    /* Form fields */
    .pf-label {
        display: block;
        margin-bottom: .375rem;
        font-size: .75rem;
        font-weight: 600;
        color: #3a2f29;
    }
    .pf-input {
        width: 100%;
        border-radius: .625rem;
        border: 1px solid #E6D9CF;
        background: #fff;
        padding: .65rem 1rem;
        font-size: .875rem;
        color: #222;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .pf-input::placeholder { color: #b5a79d; }
    .pf-input:focus { border-color: #fa4e1c; box-shadow: 0 0 0 3px rgba(250, 78, 28, .12); }
    .pf-input:disabled, .pf-input[readonly] { background: #f7f1ed; cursor: not-allowed; }

    /* Buttons */
    .pf-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .375rem;
        border-radius: .625rem;
        padding: .6rem 1.25rem;
        font-size: .875rem;
        font-weight: 700;
        color: #fff;
        background: #fa4e1c;
        box-shadow: 0 8px 18px -10px rgba(250, 78, 28, .7);
        transition: background .15s, transform .15s;
        cursor: pointer;
    }
    .pf-btn:hover { background: #E14F00; transform: translateY(-1px); }
    .pf-btn-ghost {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .375rem;
        border-radius: .625rem;
        border: 1px solid #E6D9CF;
        padding: .6rem 1.25rem;
        font-size: .875rem;
        font-weight: 600;
        color: #5a4d45;
        background: #fff;
        transition: all .15s;
        cursor: pointer;
    }
    .pf-btn-ghost:hover { border-color: #fa4e1c; color: #fa4e1c; background: #fff5f3; }
    .pf-btn-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .375rem;
        border-radius: .625rem;
        border: 1px solid rgba(220, 38, 38, .25);
        padding: .6rem 1.25rem;
        font-size: .875rem;
        font-weight: 600;
        color: #DC2626;
        background: #fff;
        transition: all .15s;
        cursor: pointer;
    }
    .pf-btn-danger:hover { background: #FEF2F2; border-color: #DC2626; }

    /* Badges */
    .pf-badge {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        border-radius: 999px;
        padding: .15rem .6rem;
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .02em;
        background: #fff1ee;
        color: #fa4e1c;
    }
    .pf-badge-green { background: #ECFDF5; color: #059669; }
    .pf-badge-blue  { background: #EFF6FF; color: #2563EB; }
    .pf-badge-gray  { background: #F3EEEA; color: #6b5d54; }
    .pf-badge-red   { background: #FEF2F2; color: #DC2626; }

    /* Icon tile */
    .pf-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: .75rem;
        background: #fff1ee;
        color: #fa4e1c;
    }

    /* Alerts */
    .pf-alert {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        border-radius: 1rem;
        border: 1px solid;
        padding: .875rem 1rem;
        font-size: .875rem;
    }
    .pf-alert-success { background: #ECFDF5; border-color: rgba(5, 150, 105, .25); color: #059669; }
    .pf-alert-error   { background: #FEF2F2; border-color: rgba(220, 38, 38, .2);  color: #DC2626; }

    /* Sidebar nav */
    .pf-nav a { color: #5a4d45; }
    .pf-nav a:hover:not(.is-active) { background: #fff5f3; color: #222; }
    .pf-nav a:hover:not(.is-active) .pf-nav-icon { background: #fff1ee; color: #fa4e1c; }
    .pf-nav a.is-active { background: #fff1ee; color: #fa4e1c; font-weight: 700; }
    .pf-nav a.is-active .pf-nav-icon { background: #fa4e1c; color: #fff; box-shadow: 0 6px 14px -6px rgba(250, 78, 28, .7); }
    .pf-nav-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        border-radius: .625rem;
        background: #F7F1ED;
        color: #8a7a70;
        transition: all .15s;
    }
    .pf-nav-scroll { scrollbar-width: none; }
    .pf-nav-scroll::-webkit-scrollbar { display: none; }
</style>
