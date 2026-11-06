{{--
    Tabs over pages that share one sidebar button (e.g. Marketing → Vouchers | Flash sales | Bundles & Promos),
    so the sidebar stays short and every page is still one tap away.
    @include('partials.page-tabs', ['tabs' => [['Vouchers', 'seller.vouchers.index', 'seller.vouchers.*'], …]])
    Each tab: [label, route name, the routes that count as this tab (default: the route itself)].
--}}
<nav class="page-tabs" aria-label="Section pages" data-page-tabs
     style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 16px;padding:4px;border-radius:12px;background:rgba(120,113,108,.08);width:max-content;max-width:100%;">
    @foreach ($tabs as $tab)
        @php $on = request()->routeIs($tab[2] ?? $tab[1]); @endphp
        <a href="{{ route($tab[1]) }}" @if ($on) aria-current="page" @endif
           style="padding:7px 14px;border-radius:9px;font-size:13px;font-weight:{{ $on ? '700' : '600' }};text-decoration:none;white-space:nowrap;{{ $on ? 'background:#fff;color:#fa4e1c;box-shadow:0 1px 3px rgba(0,0,0,.08);' : 'color:#57534E;' }}">{{ $tab[0] }}</a>
    @endforeach
</nav>
