{{-- Default Seller Policy, shown until an admin publishes their own in Settings → Policies.
     Numbers come from the live settings, so the policy always matches what the site enforces. --}}
@php
    $rate = rtrim(rtrim(number_format((float) config('marketplace.commission_rate', 10), 2), '0'), '.');
    $payoutMin = number_format(\App\Models\SellerPayout::MINIMUM, 2);
    $returnDays = \App\Models\Order::RETURN_DAYS;
    $respondDays = \App\Models\ReturnRequest::RESPOND_DAYS;
    $maxPhotos = \App\Models\Product::MAX_IMAGES;
    $area = \App\Support\ServiceArea::label();
    $policy = fn (string $key) => route('policies.show', $key);
@endphp
<style>
    .pp h2 { margin: 1.75rem 0 .5rem; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.1rem; font-weight: 800; color: #222; }
    .pp h2:first-of-type { margin-top: 0; }
    .pp h3 { margin: 1rem 0 .35rem; font-size: .95rem; font-weight: 700; color: #222; }
    .pp p { margin: .5rem 0; }
    .pp ul, .pp ol { margin: .4rem 0 .6rem 1.2rem; }
    .pp ul { list-style: disc; }
    .pp ol { list-style: decimal; }
    .pp li { margin: .2rem 0; }
    .pp a { color: #fa4e1c; font-weight: 600; }
    .pp .pp-note { border-radius: .6rem; background: #FFF7F3; border: 1px solid #FAD7C8; padding: .75rem 1rem; color: #57534E; }
    .pp table { width: 100%; margin: .5rem 0; border-collapse: collapse; font-size: .85rem; }
    .pp th, .pp td { border: 1px solid #ECE7E3; padding: .5rem .6rem; text-align: left; vertical-align: top; }
    .pp th { background: #FAFAF9; font-weight: 700; color: #222; }
</style>
<div class="pp">
    <p class="pp-note">
        <strong>Effective date:</strong> October 22, 2026. This policy sets out the rules for selling on ALVY: who can sell,
        how listings, orders, shipping, returns and payments work, and what happens when the rules are broken.
        By opening a store on ALVY you agree to this policy, our <a href="{{ $policy('terms_of_service') }}">Terms of Service</a>,
        <a href="{{ $policy('prohibited_items') }}">Prohibited Items Policy</a> and
        <a href="{{ $policy('return_refund_policy') }}">Return &amp; Refund Policy</a>.
    </p>

    <h2>1. Who can sell on ALVY</h2>
    <ul>
        <li>You must be at least 18 years old and able to enter into a binding agreement under Philippine law.</li>
        <li>Your business or pickup address must be in <strong>{{ $area }}</strong>, where ALVY currently operates.
            We can't approve a store, or keep a store active, with an address outside {{ $area }}.</li>
        <li>You need one ALVY account per store, with a working email address and Philippine mobile number.</li>
    </ul>

    <h2>2. Applying and verification</h2>
    <p>To open a store you submit a seller application with:</p>
    <ul>
        <li>a valid government-issued ID;</li>
        <li>your Tax Identification Number (TIN) and <strong>BIR Form 2303</strong> (Certificate of Registration);</li>
        <li>a <strong>DTI Business Name Registration</strong>, or for corporations, an <strong>SEC Certificate of Registration</strong>;</li>
        <li>your store name, line of business and full business address.</li>
    </ul>
    <p>
        You first confirm your email with a one-time code. Your application then goes to the ALVY team for review. You can
        prepare products as drafts while you wait, but nothing is shown to buyers until your store is approved. If we
        reject an application we tell you why, and you can correct it and resubmit. Documents must be genuine and current;
        submitting false documents is grounds for permanent removal.
    </p>

    <h2>3. Your store</h2>
    <ul>
        <li><strong>Store name:</strong> 3 to 30 characters. It must not copy another store, brand or person, or be offensive.
            You can change it up to 3 times per calendar month.</li>
        <li><strong>Store profile:</strong> a description of up to 500 characters, and a logo and banner (JPG or PNG, up to 5 MB each).</li>
        <li><strong>Pickup address:</strong> keep it accurate and inside {{ $area }}. Riders collect your parcels there.</li>
        <li><strong>Vacation mode:</strong> turn it on when you can't fulfil orders. Buyers then can't order from your store until you turn it off.</li>
    </ul>

    <h2>4. Listing products</h2>
    <h3>Every listing must</h3>
    <ul>
        <li>be for an item you have in stock and can legally sell, and that is <strong>not</strong> on the
            <a href="{{ $policy('prohibited_items') }}">Prohibited Items Policy</a>;</li>
        <li>use the correct category, an honest title, and a description of at least 20 characters that matches the item,
            including its condition (new or used) and any defects;</li>
        <li>use your own clear photos of the actual item (up to {{ $maxPhotos }} photos, JPG or PNG, up to 5 MB each);</li>
        <li>show the real price in Philippine pesos (₱1.00 to ₱1,000,000.00) and the correct weight and dimensions,
            which are used to calculate shipping;</li>
        <li>keep stock accurate (0 to 99,999). For products with options (size, color…), each option has its own price and stock.</li>
    </ul>
    <h3>Review by ALVY</h3>
    <p>
        New listings are reviewed by ALVY before they appear in the shop. A rejected listing comes with a reason; fix it and
        resubmit. We may also remove a live listing that breaks this policy.
    </p>
    <h3>Not allowed</h3>
    <ul>
        <li>Misleading prices, fake discounts (raising a price only to show a "sale"), or keyword stuffing.</li>
        <li>Listing the same item many times, or listing items you don't have.</li>
        <li>Contact details, links or other ways to buy outside ALVY, in listings, photos or chat.</li>
    </ul>

    <h2>5. Prices, sales and vouchers</h2>
    <ul>
        <li>You set your own prices. Sale prices and percentage discounts must be real reductions from your normal price,
            and scheduled sales start and end at the times you set.</li>
        <li><strong>Store vouchers, discounts, flash sales and bundle promotions you create are paid by you</strong>: they
            reduce what the buyer pays you and your earnings.</li>
        <li><strong>ALVY vouchers and free-shipping vouchers are paid by ALVY</strong> and don't reduce your earnings.</li>
        <li>You may set a purchase limit per buyer for a product.</li>
    </ul>

    <h2>6. Handling orders</h2>
    <p>Each new order goes through these steps in your Seller Centre:</p>
    <ol>
        <li><strong>Accept</strong> the order (it moves to "To Ship").</li>
        <li><strong>Pack</strong> it securely, with the printed waybill attached.</li>
        <li>Mark it <strong>Ready for Pickup</strong> and choose the sorting center whose rider will collect it.</li>
    </ol>
    <ul>
        <li>Process orders promptly and keep the parcel ready at your pickup address for the rider.</li>
        <li>You may cancel an order only <strong>before it is picked up</strong>, and only for a listed reason (for example,
            out of stock, item damaged before shipping, or a wrong price). The buyer is told why, stock is put back, and
            any online payment is refunded in full.</li>
        <li>Frequent cancellations, late shipping, or sending the wrong or a damaged item count against your store (see section 11).</li>
    </ul>

    <h2>7. Shipping and delivery</h2>
    <ul>
        <li>ALVY delivers through partner sorting centers and riders inside {{ $area }}. A rider collects the parcel from you,
            it is scanned and confirmed at a sorting center, and a delivery rider brings it to the buyer.</li>
        <li>Shipping fees are paid by the buyer and depend on the parcel's weight and the distance between your address
            and the buyer's. Accurate weights matter: undeclared weight may be charged to you.</li>
        <li>You and the buyer can follow every step on the order's tracking timeline. Once the rider has collected the
            parcel, ALVY and its logistics partners are responsible for it until it is delivered or returned to you.</li>
        <li>If a delivery fails (for example, the buyer is not home or refuses the parcel) the parcel may be returned to you.
            Please confirm in the Seller Centre when you receive a returned parcel.</li>
    </ul>

    <h2>8. Returns and refunds</h2>
    <ul>
        <li>Buyers can request a return or refund within <strong>{{ $returnDays }} days</strong> of delivery, with photos and a
            reason, as described in the <a href="{{ $policy('return_refund_policy') }}">Return &amp; Refund Policy</a>.</li>
        <li>You must <strong>approve or reject a request within {{ $respondDays }} days</strong>. A request you don't answer in
            time is approved automatically.</li>
        <li>Rejecting a request requires a clear reason. The buyer can escalate a rejected request to ALVY, and ALVY's
            decision is final.</li>
        <li>For approved returns, the refunded amount (and its commission) is taken out of your earnings for that order.</li>
    </ul>

    <h2>9. Fees, earnings and payouts</h2>
    <table>
        <tr><th style="width:34%;">Commission</th><td>ALVY keeps <strong>{{ $rate }}%</strong> of the amount the buyer pays for each item (after your own discounts and vouchers). The rate in force when an order is placed is recorded with that order, so later rate changes don't affect it. Any change to the rate is announced before it applies.</td></tr>
        <tr><th>Your earnings</th><td>The item amount minus the commission. Shipping fees go to logistics and are not part of your earnings.</td></tr>
        <tr><th>Pending</th><td>Earnings stay <em>pending</em> until the order is Completed (the buyer confirms receipt, or the return window ends).</td></tr>
        <tr><th>Not earned</th><td>Cancelled, refunded and unpaid orders earn nothing, and no commission is charged on them.</td></tr>
        <tr><th>Payouts</th><td>Request a payout of your available balance from <em>Earnings &amp; Payouts</em> (minimum ₱{{ $payoutMin }}). You can see every order's rate, commission and status on the <em>Commission</em> page.</td></tr>
    </table>
    <p>You are responsible for your own taxes and for issuing receipts or invoices as required by the BIR.</p>

    <h2>10. Conduct, reviews and buyer information</h2>
    <ul>
        <li>Be honest and polite in chat. Don't pressure buyers, ask them to cancel, or move the sale outside ALVY.</li>
        <li>Don't write, buy or arrange reviews of your own products, or offer rewards for good reviews. Reply to reviews
            professionally.</li>
        <li>Buyer names, addresses and phone numbers are given to you only to fulfil their orders. Don't use them for
            anything else, share them, or contact buyers outside ALVY. Personal data must be handled according to the
            <a href="{{ $policy('privacy_policy') }}">Privacy Policy</a> and the Data Privacy Act of 2012 (RA 10173).</li>
        <li>Products must meet Philippine consumer-protection, product-safety and intellectual-property laws. Counterfeit or
            infringing goods are not allowed.</li>
    </ul>

    <h2>11. Violations and enforcement</h2>
    <p>
        Breaking this policy can lead to a <strong>warning</strong>, <strong>removal of a product</strong>, <strong>suspension</strong>
        of your store, or <strong>deactivation</strong> of your account, depending on how serious the problem is and whether it is
        repeated. Examples include prohibited or counterfeit items, the wrong category, misleading information, inappropriate
        content, fake reviews, selling outside ALVY, and repeated cancellations or late shipments.
    </p>
    <p>
        Each action is recorded in your account with the reason. If you believe an action was a mistake, contact us with the
        details and we will review it. Earnings from orders already completed are paid out unless they are needed to refund
        buyers or are linked to fraud.
    </p>

    <h2>12. Closing your store</h2>
    <p>
        You can stop selling at any time. Finish or cancel your open orders, settle any returns, and request a payout of your
        available balance first. Records of past orders are kept as required by law.
    </p>

    <h2>13. Changes to this policy</h2>
    <p>
        We may update this policy as ALVY grows. We will tell sellers about important changes in advance through the Seller Centre
        or by email. Continuing to sell after a change takes effect means you accept the updated policy.
    </p>

    <h2>14. Contact</h2>
    <p>
        Questions about selling on ALVY? Email <a href="mailto:alvyshop27@gmail.com">alvyshop27@gmail.com</a> or message us
        from your Seller Centre.
    </p>
</div>
