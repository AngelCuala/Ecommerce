{{-- Shared look for the seller and admin dashboards --}}
<style>
    .db-hero {
        position: relative; overflow: hidden; border-radius: 1.25rem; padding: 1.5rem 1.75rem; color: #fff;
        background: linear-gradient(120deg, #fa4e1c 0%, #ff7a45 45%, #002b4d 140%);
        box-shadow: 0 18px 40px -24px rgba(250, 78, 28, .8);
    }
    .db-hero::after {
        content: ''; position: absolute; right: -60px; top: -60px; width: 220px; height: 220px; border-radius: 9999px;
        background: rgba(255, 255, 255, .12);
    }
    .db-hero-btn {
        display: inline-flex; align-items: center; gap: .4rem; border-radius: .7rem; padding: .55rem 1rem;
        font-size: .8125rem; font-weight: 700; transition: transform .15s, background .15s;
    }
    .db-hero-btn:hover { transform: translateY(-1px); }
    .db-hero-btn.solid { background: #fff; color: #fa4e1c; }
    .db-hero-btn.ghost { background: rgba(255, 255, 255, .16); color: #fff; }
    .db-hero-btn.ghost:hover { background: rgba(255, 255, 255, .26); }

    .db-card {
        background: #fff; border: 1px solid #F0E8E2; border-radius: 1.1rem;
        box-shadow: 0 1px 2px rgba(58, 47, 41, .04), 0 10px 30px -22px rgba(58, 47, 41, .25);
    }
    .db-card-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1.1rem 1.25rem .5rem; }
    .db-card-title { font-family: 'Nunito', ui-sans-serif, system-ui; font-weight: 800; font-size: 1rem; color: #222; }
    .db-card-sub { font-size: .75rem; color: #8a7a70; }
    .db-link { font-size: .75rem; font-weight: 700; color: #fa4e1c; white-space: nowrap; }
    .db-link:hover { text-decoration: underline; }

    .db-stat { padding: 1.1rem 1.25rem; }
    .db-stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #8a7a70; }
    .db-stat-value { margin-top: .35rem; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.75rem; font-weight: 800; line-height: 1.1; color: #222; }
    .db-stat-foot { margin-top: .5rem; font-size: .75rem; color: #8a7a70; }
    .db-icon {
        display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
        width: 2.6rem; height: 2.6rem; border-radius: .8rem;
    }
    .tone-orange { background: #fff1ee; color: #fa4e1c; }
    .tone-navy   { background: #e8f0f6; color: #002b4d; }
    .tone-green  { background: #ECFDF5; color: #059669; }
    .tone-amber  { background: #FFFBEB; color: #B45309; }
    .tone-red    { background: #FEF2F2; color: #DC2626; }
    .tone-violet { background: #F3F0FF; color: #6D28D9; }

    .db-delta { display: inline-flex; align-items: center; gap: .2rem; border-radius: 9999px; padding: .1rem .5rem; font-size: .7rem; font-weight: 700; }
    .db-delta.up   { background: #ECFDF5; color: #059669; }
    .db-delta.down { background: #FEF2F2; color: #DC2626; }
    .db-delta.flat { background: #F3EEEA; color: #6b5d54; }

    .db-badge { display: inline-flex; align-items: center; gap: .3rem; border-radius: 9999px; padding: .15rem .6rem; font-size: .7rem; font-weight: 700; white-space: nowrap; }

    .db-table { width: 100%; font-size: .85rem; }
    .db-table th { padding: .65rem 1.25rem; text-align: left; font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #a3948a; background: #FDFAF8; border-top: 1px solid #F3ECE6; border-bottom: 1px solid #F3ECE6; }
    .db-table td { padding: .75rem 1.25rem; border-bottom: 1px solid #F6F0EB; color: #3a2f29; }
    .db-table tr:last-child td { border-bottom: 0; }
    .db-table tbody tr { transition: background .12s; }
    .db-table tbody tr:hover { background: #FFFAF7; }

    .db-row-link { display: flex; align-items: center; gap: .75rem; border-radius: .8rem; padding: .6rem .75rem; transition: background .12s; }
    .db-row-link:hover { background: #FFF6F2; }

    .db-progress { height: .45rem; border-radius: 9999px; background: #F3EEEA; overflow: hidden; }
    .db-progress > span { display: block; height: 100%; border-radius: 9999px; }

    .db-empty { padding: 2rem 1rem; text-align: center; font-size: .85rem; color: #a3948a; }
</style>
