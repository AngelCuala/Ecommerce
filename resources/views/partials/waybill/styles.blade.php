{{-- Styles for the 4 × 6 in ALVY waybill label (partials.waybill.label) and the print page around it. --}}
<style>
    @page { size: 4in 6in; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #eceae7; font: 11px/1.3 Arial, Helvetica, sans-serif; color: #000; }

    /* Print bar (screen only) */
    .bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 10px 16px; background: #002b4d; color: #fff; font-size: 13px; }
    .bar strong { margin-right: auto; }
    .bar button, .bar a { border: 0; border-radius: 6px; padding: 7px 14px; font: 600 13px Arial, sans-serif; cursor: pointer; text-decoration: none; }
    .bar button { background: #fa4e1c; color: #fff; }
    .bar a { background: rgba(255,255,255,.15); color: #fff; }
    .sheet { display: flex; flex-wrap: wrap; justify-content: center; gap: 18px; padding: 18px; }

    /* One label */
    .wb { width: 4in; height: 6in; display: flex; flex-direction: column; overflow: hidden; background: #fff; border: 1.5px solid #000; page-break-after: always; break-after: page; }
    .wb-sec { border-bottom: 1.5px solid #000; padding: 6px 9px; }
    .wb-lbl { font-size: 7.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #333; }

    /* 1. Brand + service */
    .wb-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; padding: 4px 8px; }
    .wb-brand { display: flex; align-items: center; gap: 5px; font-size: 17px; font-weight: 900; letter-spacing: -.02em; }
    .wb-brand img { width: 20px; height: 20px; object-fit: contain; }
    .wb-brand small { font-size: 8.5px; font-weight: 700; letter-spacing: .08em; }
    .wb-tags { display: flex; gap: 4px; }
    .wb-tag { border: 1.5px solid #000; border-radius: 3px; padding: 1px 6px; font-size: 10px; font-weight: 900; letter-spacing: .04em; }
    .wb-tag.inv { background: #000; color: #fff; }

    /* 2. Barcode */
    .wb-code { display: flex; align-items: center; gap: 8px; padding: 5px 9px; }
    .wb-qr { flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; }
    .wb-qr svg { display: block; width: 1.05in; height: 1.05in; }
    .wb-qr span { margin-top: 1px; font-size: 7.5px; font-weight: 900; letter-spacing: .14em; }
    .wb-bar { flex: 1; min-width: 0; text-align: center; }
    .wb-bar svg { display: block; width: 100%; height: 60px; }
    .wb-no { margin-top: 3px; font-size: 14px; font-weight: 900; letter-spacing: .1em; white-space: nowrap; }

    /* 3. Sort code + hub */
    .wb-sort { display: flex; align-items: stretch; padding: 0; }
    .wb-sort-code { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px 4px; background: #000; color: #fff; font-size: 27px; font-weight: 900; letter-spacing: .05em; white-space: nowrap; }
    .wb-sort-code.pending { background: #fff; color: #666; font-size: 10px; font-weight: 700; letter-spacing: .02em; text-align: center; border-right: 1.5px dashed #999; }
    .wb-hub { width: 38%; padding: 4px 8px; border-left: 1.5px solid #000; display: grid; gap: 2px; align-content: center; }
    .wb-hub b { display: block; font-size: 12px; font-weight: 800; line-height: 1.15; }

    /* 4–5. Recipient and sender: a side tag, then the details */
    .wb-party { display: flex; gap: 7px; }
    .wb-side { flex: 0 0 auto; align-self: flex-start; min-width: 38px; padding: 3px 4px; text-align: center; font-size: 9px; font-weight: 900; letter-spacing: .08em; border: 1.5px solid #000; border-radius: 3px; }
    .wb-side.to { background: #000; color: #fff; }
    .wb-party-body { min-width: 0; flex: 1; }
    .wb-name { font-size: 16px; font-weight: 900; line-height: 1.15; }
    .wb-phone { font-size: 12px; font-weight: 700; white-space: nowrap; }
    .wb-addr { margin-top: 2px; font-size: 12.5px; line-height: 1.3; }
    .wb-party.from .wb-name { font-size: 12px; }
    .wb-party.from .wb-addr { font-size: 10.5px; color: #222; }

    /* 6. Payment */
    .wb-pay { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 9px; }
    .wb-pay.cod { background: #000; color: #fff; }
    .wb-pay.cod .wb-lbl { color: #fff; }
    .wb-pay-what { font-size: 13px; font-weight: 900; letter-spacing: .04em; }
    .wb-pay-amount { font-size: 26px; font-weight: 900; letter-spacing: -.01em; white-space: nowrap; }

    /* 7. Details */
    .wb-meta { display: grid; grid-template-columns: 1.1fr 1.2fr .9fr .6fr; gap: 0; padding: 0; }
    .wb-meta > div { padding: 5px 7px; border-left: 1px solid #000; }
    .wb-meta > div:first-child { border-left: 0; padding-left: 8px; }
    .wb-meta b { display: block; margin-top: 1px; font-size: 12px; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wb-track { display: flex; justify-content: space-between; gap: 6px; padding: 3px 8px; font-size: 9px; }
    .wb-track b { font-family: "Courier New", monospace; font-size: 11.5px; letter-spacing: .04em; }

    /* 8. Contents (takes the remaining height) */
    .wb-items { flex: 1; min-height: 0; overflow: hidden; }
    .wb-items ul { margin: 2px 0 0; padding: 0; list-style: none; }
    .wb-items li { display: flex; justify-content: space-between; gap: 8px; padding: 2.5px 0; font-size: 11px; border-bottom: 1px dotted #bbb; }
    .wb-items li:last-child { border-bottom: 0; }
    .wb-items li span:first-child { min-width: 0; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .wb-items li span:last-child { flex: 0 0 auto; font-weight: 800; }
    .wb-note { margin-top: 3px; padding: 2px 5px; font-size: 9px; border: 1px dashed #000; border-radius: 3px; }

    /* 9. Proof of delivery */
    .wb-sign { display: flex; align-items: flex-end; gap: 6px; border-bottom: 0; padding: 6px 8px 7px; }
    .wb-sign .line { flex: 1; border-bottom: 1px solid #000; height: 20px; }
    .wb-sign .line.short { flex: .45; }

    @media print {
        body { background: #fff; }
        .bar { display: none; }
        .sheet { display: block; padding: 0; }
        .wb { border: 0; margin: 0; }
    }
</style>
