{{--
    The Export PDF button of a page header (seller Commission, Sales Reports…), so every page has the same button
    in the same place: top right of the header, beside the title. @include('partials.export-pdf', ['href' => …])
--}}
@once
<style>
    .xp-btn { display: inline-flex; flex: none; align-items: center; gap: .4rem; border: 1px solid #E7E5E4; border-radius: .5rem; padding: .5rem .875rem;
              background: #fff; font-size: .8125rem; font-weight: 600; line-height: 1.2; color: #44403C; white-space: nowrap; transition: border-color .15s, color .15s; }
    .xp-btn:hover { border-color: #fa4e1c; color: #fa4e1c; text-decoration: none; }
    /* The header the button sits in: title block on the left (it wraps), the button on the right (it doesn't). */
    .xp-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .xp-head > :first-child { min-width: 0; flex: 1 1 auto; }
    @media (max-width: 560px) { .xp-head { flex-direction: column; } }
</style>
@endonce
<a href="{{ $href }}" class="xp-btn" data-export-pdf>
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>
    Export PDF
</a>
