{{-- Default Terms of Service, shown until an admin publishes their own in Settings → Policies. --}}
@php
    $returnDays   = \App\Models\Order::RETURN_DAYS;
    $respondDays  = \App\Models\ReturnRequest::RESPOND_DAYS;
    $escalateDays = \App\Models\ReturnRequest::ESCALATE_DAYS;
    $maxPhotos    = \App\Models\ReturnRequest::MAX_PHOTOS;
    $attempts     = \App\Models\ParcelDelivery::MAX_ATTEMPTS;
    $area         = \App\Support\ServiceArea::label();
    $idle         = (int) config('session.lifetime', 30);
    $maxHours     = intdiv((int) config('session.max_login_minutes', 480), 60);
    $policy       = fn (string $key) => route('policies.show', $key);
    $returnsOn    = \App\Models\Order::returnsEnabled();
@endphp
<style>
    .pp h2 { margin: 1.75rem 0 .5rem; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.1rem; font-weight: 800; color: #222; }
    .pp h2:first-of-type { margin-top: 0; }
    .pp h3 { margin: 1rem 0 .35rem; font-size: .95rem; font-weight: 700; color: #222; }
    .pp p { margin: .5rem 0; }
    .pp ul { margin: .4rem 0 .6rem 1.2rem; list-style: disc; }
    .pp li { margin: .2rem 0; }
    .pp a { color: #fa4e1c; font-weight: 600; }
    .pp .pp-note { border-radius: .6rem; background: #FFF7F3; border: 1px solid #FAD7C8; padding: .75rem 1rem; color: #57534E; }
</style>
<div class="pp">
    <p class="pp-note">
        <strong>Effective date:</strong> October 29, 2026. Please read these Terms before using ALVY. By creating an account,
        browsing, or placing an order, you agree to them. If you do not agree, please do not use ALVY.
    </p>

    <h2>1. About ALVY and these Terms</h2>
    <p>
        ALVY is an online marketplace. Independent sellers list and sell their products on ALVY, and orders are delivered
        through partner sorting centers and riders. In these Terms, "ALVY", "we" and "us" mean the ALVY platform and its team;
        "you" means anyone who uses ALVY.
    </p>
    <p>These Terms work together with our other policies, which form part of your agreement with us:</p>
    <ul>
        <li><a href="{{ $policy('privacy_policy') }}">Privacy Policy</a>: how we collect and use your personal information.</li>
        @if ($returnsOn)<li><a href="{{ $policy('return_refund_policy') }}">Return &amp; Refund Policy</a>: how returns and refunds work.</li>@endif
        <li><a href="{{ $policy('prohibited_items') }}">Prohibited Items Policy</a>: what may not be sold on ALVY.</li>
        <li><a href="{{ $policy('seller_policy') }}">Seller Policy</a>: extra rules for anyone who sells on ALVY.</li>
    </ul>
    <p>Sorting centers and riders also agree to the partner terms shown when they apply.</p>

    <h2>2. Your account</h2>
    <ul>
        <li>You must be at least 18 years old to create an account and place orders, or use ALVY with the consent and
            supervision of a parent or guardian.</li>
        <li>Give true, complete and current information: your name, email address, mobile number and address. We confirm your
            email with a one-time code before your account is active.</li>
        <li>One account is for one person. Keep your password secret and do not let others use your account. You are responsible
            for activity in your account, so tell us right away if you think someone else has accessed it.</li>
        <li>For your security, you are signed out after {{ $idle }} minutes without activity and after {{ $maxHours }} hours in
            any case. Changing your password needs a code sent to your email.</li>
        <li>You may sign in with Google. In that case Google's terms also apply to that sign-in.</li>
    </ul>

    <h2>3. Where ALVY delivers</h2>
    <p>
        ALVY currently operates in <strong>{{ $area }}</strong> only. Delivery addresses, and sellers' pickup addresses, must be
        inside this area. We may add or remove areas over time.
    </p>

    <h2>4. Buying on ALVY</h2>
    <ul>
        <li><strong>Who you buy from.</strong> Products are listed and sold by independent sellers. When you place an order, the
            sale is between you and that seller. ALVY provides the platform, the checkout, delivery through our partners, and
            help when something goes wrong.</li>
        <li><strong>Listings.</strong> Sellers are responsible for their listings, including photos, descriptions, prices and stock.
            We review new products and remove listings that break our policies, but we cannot guarantee that every listing is
            free of errors.</li>
        <li><strong>Prices.</strong> Prices are in Philippine pesos (₱). The shipping fee depends on the parcel's weight and the
            distance, and is shown at checkout. The total you see before you place the order is what you pay.</li>
        <li><strong>Placing an order.</strong> Placing an order is an offer to buy. The seller accepts it by processing the order.
            A seller may decline or cancel an order for a valid reason, such as an item going out of stock or a listing error.
            If that happens, you are not charged, or your payment is refunded.</li>
    </ul>

    <h2>5. Payment</h2>
    <ul>
        <li><strong>Cash on delivery (COD).</strong> Pay the rider the exact amount shown on your order when the parcel arrives.</li>
        <li><strong>GCash.</strong> Send the payment to the account shown at checkout, then upload your receipt and reference
            number. The seller checks the payment before shipping. An order whose payment is not received may be cancelled.</li>
        <li><strong>Other online payments</strong>, when offered at checkout, are processed by the payment provider under its own
            terms.</li>
        <li>Never pay a seller outside ALVY or by any method not shown at checkout. ALVY cannot protect payments made outside
            the platform.</li>
    </ul>

    <h2>6. Vouchers and promotions</h2>
    <ul>
        <li>Vouchers, discounts, flash sales and bundles come with conditions shown with each offer, such as a minimum spend,
            eligible products, usage limits and an end date.</li>
        <li>Vouchers have no cash value and cannot be sold, exchanged for cash or transferred. If you cancel an order, any
            voucher you used is returned to you if it is still valid.</li>
        <li>We may cancel a voucher or an order where a promotion is misused, for example with fake accounts or by exploiting
            an error.</li>
    </ul>

    <h2>7. Cancelling an order</h2>
    <ul>
        <li>You can cancel an order, with a reason, from your order page until the seller marks it ready for pickup. After
            that, please message the seller.</li>
        <li>When an order is cancelled, any payment you made is refunded and any voucher is returned.</li>
    </ul>

    <h2>8. Shipping and delivery</h2>
    <ul>
        <li>A rider collects your parcel from the seller and takes it through a partner sorting center to you. You can follow
            every step on your order's tracking page.</li>
        <li>Delivery dates shown are estimates. Delays can happen because of weather, traffic or other events outside our control.</li>
        <li>Please be available to receive your parcel, and prepare the exact amount for COD orders. When the parcel is handed
            over, the rider takes a <strong>proof-of-delivery photo</strong>, which completes the delivery.</li>
        <li>If a delivery fails (for example, nobody is home or the address is wrong), the rider tries again, up to
            {{ $attempts }} attempts. After the last failed attempt, or if you refuse the parcel, it is returned to the seller.</li>
        <li>Check your parcel when you receive it. If something is wrong, {{ $returnsOn ? 'request a return as described below' : 'message the seller from your order page' }}.</li>
    </ul>

    <h2>9. Returns and refunds</h2>
    @if (! $returnsOn)
    <ul>
        <li>Returns and refunds through ALVY are <strong>not available yet</strong>. If something is wrong with your order,
            message the seller from your order page, or contact us.</li>
        <li>Cancelled orders are still refunded as described in section 7.</li>
        <li>An order is completed when you confirm you received it, or automatically {{ $returnDays }} days after delivery.</li>
    </ul>
    @else
    <ul>
        <li>You can request a return or refund within <strong>{{ $returnDays }} days</strong> of delivery, from your order page.
            Choose a reason (for example damaged, wrong or missing items, an item not as described, or a change of mind) and add
            1 to {{ $maxPhotos }} photos.</li>
        <li>The seller has {{ $respondDays }} days to approve or reject your request. A request the seller does not answer in time
            is approved automatically.</li>
        <li>If the seller rejects your request, you can escalate it to ALVY within {{ $escalateDays }} days. ALVY reviews both
            sides, and its decision is final.</li>
        <li>An order is completed when you confirm you received it, or automatically when the return window ends.</li>
        <li>The full rules are in the <a href="{{ $policy('return_refund_policy') }}">Return &amp; Refund Policy</a>.</li>
    </ul>
    @endif

    <h2>10. Reviews, chat and content you post</h2>
    <ul>
        <li>Reviews must be honest and about your own purchase. Do not post fake reviews, or reviews in exchange for rewards.</li>
        <li>Be respectful in chat. Do not post content that is false, abusive, hateful, sexual, or that shares other people's
            personal information.</li>
        <li>You keep ownership of what you post, such as reviews and photos. You allow ALVY to show it on the platform for as long
            as it stays on ALVY.</li>
        <li>We may remove content that breaks these Terms.</li>
    </ul>

    <h2>11. What you may not do</h2>
    <ul>
        <li>Break any law, or buy or sell items listed in the <a href="{{ $policy('prohibited_items') }}">Prohibited Items Policy</a>.</li>
        <li>Commit fraud: fake orders, false return requests, payment chargebacks in bad faith, or using another person's account
            or payment details.</li>
        <li>Arrange sales or payments outside ALVY to avoid its fees or protections.</li>
        <li>Harass sellers, riders, sorting-center staff or other users.</li>
        <li>Interfere with ALVY: hacking, scraping, sending spam, uploading malware, or overloading the service.</li>
    </ul>

    <h2>12. Suspension and closing accounts</h2>
    <p>
        If you break these Terms or our policies, we may warn you, limit features, cancel orders, or suspend or close your
        account. Where possible, we tell you why. If you believe we made a mistake, contact us and we will review it.
    </p>
    <p>
        You may stop using ALVY at any time. To close your account, contact us. Finish or cancel your open orders first. We keep
        some records after an account is closed where the law requires it, as explained in the
        <a href="{{ $policy('privacy_policy') }}">Privacy Policy</a>.
    </p>

    <h2>13. ALVY's name and content</h2>
    <p>
        The ALVY name, logo, design and software belong to ALVY. You may use ALVY to shop and sell as these Terms allow, but you
        may not copy, change or reuse them without our written permission.
    </p>

    <h2>14. Responsibility</h2>
    <ul>
        <li>We work to keep ALVY available, safe and accurate, but the service is provided "as is". It may sometimes be
            unavailable, for example during maintenance.</li>
        <li>Sellers are responsible for the products they sell, including their quality, safety and legality. Your rights under
            the <strong>Consumer Act of the Philippines (Republic Act No. 7394)</strong> and the
            <strong>Internet Transactions Act of 2023 (Republic Act No. 11967)</strong> are not limited by these Terms.</li>
        <li>As far as the law allows, ALVY is not responsible for indirect losses, such as lost profits, that come from using
            ALVY. ALVY's total responsibility for any order is limited to the amount you paid for that order.</li>
    </ul>

    <h2>15. Disputes and governing law</h2>
    <p>
        If you have a problem with an order, first contact the seller through chat{{ $returnsOn ? ', or use the return process on your order page' : '' }}.
        If it is still not solved, contact us and we will help both sides reach a fair outcome. These Terms are governed by the
        laws of the Republic of the Philippines, including the <strong>Electronic Commerce Act of 2000 (Republic Act No. 8792)</strong>.
    </p>

    <h2>16. Changes to these Terms</h2>
    <p>
        We may update these Terms as ALVY grows. The effective date at the top shows when they last changed. We will tell you
        about important changes in advance, on ALVY or by email. Continuing to use ALVY after a change takes effect means you
        accept the updated Terms.
    </p>

    <h2>17. Contact us</h2>
    <p>
        Questions about these Terms? Email <a href="mailto:alvyshop27@gmail.com">alvyshop27@gmail.com</a> or send us a message from
        your account.
    </p>
</div>
