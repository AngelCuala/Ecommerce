{{-- Reusable show/hide password toggle.
     Works on any password input marked with `data-password` that has a
     sibling button marked with `data-toggle-password` inside a relative wrapper.
     The button's inner SVG is swapped between an "eye" (show) and
     "eye-off" (hide) icon on each click. --}}
<style>
    [data-toggle-password] {
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        color: #6b90aa;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: color .15s;
    }
    [data-toggle-password]:hover { color: #fa4e1c; }
    [data-toggle-password] svg { width: 20px; height: 20px; display: block; }
</style>
<script src="{{ asset('js/password-toggle.js') }}"></script>
