{{-- Plain panel styles shared by seller pages: neutral borders, one orange accent. --}}
@once
<style>
    .pl-card { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; }
    .pl-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; padding: .9rem 1.25rem; border-bottom: 1px solid #F2EEEA; }
    .pl-head h2 { font-size: .9375rem; font-weight: 600; color: #222; }
    .pl-body { padding: 1rem 1.25rem; }
    .pl-muted { color: #8a7f78; }
    .pl-label { display: block; margin-bottom: .3rem; font-size: .8125rem; font-weight: 500; color: #44403C; }
    .pl-note { margin-top: .3rem; font-size: .75rem; color: #8a7f78; }
    .pl-link { font-size: .8125rem; font-weight: 600; color: #fa4e1c; }
    .pl-link:hover { text-decoration: underline; }
    .pl-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border-radius: .375rem; padding: .5rem 1rem; font-size: .875rem; font-weight: 600; color: #fff; background: #fa4e1c; transition: opacity .15s; }
    .pl-btn:hover { opacity: .9; }
    .pl-btn-line { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; border: 1px solid #E7E5E4; border-radius: .375rem; padding: .45rem 1rem; font-size: .875rem; font-weight: 500; color: #44403C; background: #fff; transition: background .15s; }
    .pl-btn-line:hover { background: #FAFAF9; }
    .pl-tag { display: inline-block; border-radius: .375rem; padding: .1rem .5rem; font-size: .75rem; font-weight: 600; white-space: nowrap; }
    .pl-tag.pending    { background: #FFF7ED; color: #C2410C; }
    .pl-tag.processing { background: #FFF1EE; color: #D93D0E; }
    .pl-tag.shipped    { background: #EFF6FF; color: #1D4ED8; }
    .pl-tag.delivered  { background: #ECFDF5; color: #047857; }
    .pl-tag.cancelled  { background: #F5F5F4; color: #78716C; }
    .pl-tag.neutral    { background: #F5F5F4; color: #57534E; }
    .pl-table { width: 100%; font-size: .875rem; }
    .pl-table th { padding: .6rem 1.25rem; text-align: left; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; background: #FCFBFA; border-bottom: 1px solid #ECE7E3; }
    .pl-table td { padding: .7rem 1.25rem; border-bottom: 1px solid #F2EEEA; color: #44403C; }
    .pl-table tbody tr:last-child td { border-bottom: 0; }
    .pl-table tbody tr:hover { background: #FCFBFA; }
    .pl-dl > div { display: flex; justify-content: space-between; gap: 1rem; padding: .35rem 0; font-size: .875rem; }
    .pl-dl dt { color: #8a7f78; }
    .pl-dl dd { color: #222; text-align: right; }
</style>
@endonce
