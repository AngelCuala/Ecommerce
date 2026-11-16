# 8 · Money: payments, vouchers and shipping fees

All amounts are worked out on the server, never in the browser. The checkout page only shows what
the server calculated, so nobody can change a price by editing the page.

## How a checkout total is worked out

Take one store's part of a checkout. ALVY starts from the items and goes down this list:

1. **Item prices:** each item at the price the buyer pays right now (flash price, sale price or
   regular price, see [page 3](03-buyer-and-storefront.md#how-the-price-is-decided)).
2. **Minus store promotions,** such as "buy 2, get 1 free". *The seller pays for these.*
3. **Minus the store voucher,** one per store. *The seller pays.*
4. **Minus the ALVY voucher,** one per checkout, shared across the stores. *ALVY pays.*
5. **Plus the shipping fee** for this store's parcel.
6. **Minus the shipping voucher,** one per checkout. *ALVY pays.*

The result is what the buyer pays for that store's order.

**Example:** a ₱500 bag, a ₱50 store voucher, a ₱40 shipping fee and a ₱40 free-shipping voucher.
The buyer pays 500 − 50 + 40 − 40 = **₱450**. The seller's sale counts as ₱450 for the items
(they paid the ₱50 voucher), and ALVY covers the ₱40 shipping.

## Shipping fees

Each store's parcel has its own shipping fee, based on **weight** and **distance**.

**Weight:** add up the weight of the store's items and round **up** to a whole kilo (at least 1 kg).
A product with no weight entered counts as 0.5 kg.

**Distance:** ALVY compares the seller's pickup address with the buyer's address using the official
address codes, and picks a rate:

| How far | First kilo | Each extra kilo |
|---|---|---|
| Same town | ₱40 | ₱10 |
| Same province | ₱50 | ₱15 |
| Same region | ₱55 | ₱18 |
| Further | by island group (NCR, Luzon, Visayas, Mindanao), e.g. NCR to Luzon ₱70 / ₱25 | |

**Formula:** fee = first kilo + (kilos − 1) × each extra kilo.

**Example:** a 2.3 kg parcel within the same town counts as 3 kg: ₱40 + 2 × ₱10 = **₱60**.

Logistics companies can change these rates on their **Zones & Rates** page. Their saved rates
replace the defaults. Some faraway provinces nobody covers (Batanes) can't be ordered to at all.

The **whole shipping fee goes to the logistics companies**. ALVY can take a cut, but by default it
takes nothing.

## How buyers can pay

**Cash on delivery.** The rider collects the cash at the door, keeps it, and later hands it to their
company, which confirms it.

**GCash to ALVY.** The buyer pays ALVY's GCash QR, then uploads the receipt and reference number.
An admin checks that the money arrived before the sellers see the orders ([page 7](07-admin-panel.md#checking-gcash-payments)).

**Online (GCash, Maya or card).** The buyer is sent to a payment page run by **PayMongo**. One
payment covers all the store orders of the checkout. ALVY checks the result when the buyer comes
back, when they press *Pay again*, and once more before cancelling, so a buyer is never charged
twice. An order not paid within an hour (`PAYMENT_UNPAID_MINUTES`) is cancelled automatically.

For testing there is a **sandbox** gateway: a fake payment page with Pay, Decline and Cancel buttons,
where no real money moves. The setting `PAYMENT_GATEWAY` chooses `paymongo`, `sandbox`, or nothing
(then only cash on delivery and GCash receipts are offered).

## Vouchers

There are two kinds:

- **ALVY vouchers** are made by admins. They work on any store's products, and ALVY pays the
  discount. A **shipping voucher** is an ALVY voucher on the shipping fee.
- **Store vouchers** are made by sellers. They work only on that seller's products (or some of
  them), and the seller pays.

Buyers can type a code at checkout, or **claim** vouchers into *My Vouchers* first to use later.
A buyer can use one store voucher per store, one ALVY voucher and one shipping voucher in each
checkout. If an order is cancelled, its vouchers become usable again.

## Where the money goes

| Who | Gets | Where they see it |
|---|---|---|
| **Seller** | the item total after their own promotions and vouchers, minus ALVY's commission (10% by default; the rate is saved on each order) | Earnings, Commissions |
| **ALVY** | the commission, and pays for its own vouchers | Admin reports |
| **Logistics company** | the shipping fee, split between the hubs that handled the parcel, minus what it pays its riders | Commission & Earnings |
| **Rider** | ₱30 per pickup, ₱50 per delivery; trucks per trip | Rider Pay, Wallet |

Nobody earns anything from cancelled, refunded or unpaid orders.

## Where to find it in the code

| What | File |
|---|---|
| Checkout totals, vouchers, promotions | [VoucherService](../app/Services/VoucherService.php), [CheckoutController](../app/Http/Controllers/Buyer/CheckoutController.php) |
| Shipping fee | [ShippingService](../app/Services/ShippingService.php), default rates in [config/shipping.php](../config/shipping.php) |
| Rates set by logistics companies | [ShippingRate](../app/Models/ShippingRate.php), [ShippingZone](../app/Models/ShippingZone.php) |
| Laguna-only addresses | [ServiceArea](../app/Support/ServiceArea.php), `config/service_area.php` |
| Online payments | [PaymentService](../app/Services/PaymentService.php), [PayMongoGateway](../app/Services/Payments/PayMongoGateway.php), [SandboxGateway](../app/Services/Payments/SandboxGateway.php), settings in `config/payments.php` |
| GCash receipts | [GcashPaymentController](../app/Http/Controllers/Admin/GcashPaymentController.php), [GcashChecks](../app/Services/GcashChecks.php), [AlvyGcash](../app/Support/AlvyGcash.php) |
| Vouchers | [Voucher](../app/Models/Voucher.php), [VoucherRules](../app/Support/VoucherRules.php), [VoucherWalletController](../app/Http/Controllers/Buyer/VoucherWalletController.php) |
| Seller commission | [SellerCommission](../app/Services/SellerCommission.php), rate in `config/marketplace.php` |
| Logistics earnings | [SortingCenterCommission](../app/Services/SortingCenterCommission.php) |
| Rider pay and cash | [RiderWallet](../app/Services/RiderWallet.php) |
