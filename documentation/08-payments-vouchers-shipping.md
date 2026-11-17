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
| **Seller** | the item total after their own promotions and vouchers, minus ALVY's commission (10% by default; the rate is saved on each order). Held in the wallet during the return window, then withdrawable | Earnings (wallet), Commissions |
| **ALVY** | the commission (kept even when the order is refunded), and pays for its own vouchers | Admin dashboard and reports |
| **Logistics company** | 40% of the shipping fee, split evenly between every hub the parcel passed, minus ALVY's commission on it | Commission & Earnings |
| **Rider** | 30% of the shipping fee per pickup and 30% per delivery | Rider Pay, Wallet |
| **Linehaul driver** | nothing per trip since 16 Nov 2026 (paid by their company); older trips ₱400 car, ₱600 van, ₱1,000 truck, ₱1,500 6-wheeler | Trip pay |

Nobody else earns anything from cancelled, refunded or unpaid orders.

### How the money moves

- **Cash on delivery:** the rider collects it → remits it to the company (which confirms it) → the
  company sends ALVY the item price with a report (**ALVY Remittance**), keeping the shipping fee.
- **Paid at checkout** (GCash to ALVY, or online): ALVY has the money; it owes the company the shipping
  fee, taken off the company's next remittance.
- ALVY pays **sellers** when they withdraw, and **refunds** buyers.

### Refunds

An approved return's money is a **refund** sent only when the item is back with the seller. The
buyer chooses GCash, Maya or a bank (paid online: back the same way), and an admin sends it and
records the reference ([page 3](03-buyer-and-storefront.md#returns-and-refunds)). The order's
`refunded_amount` is written when it's sent, so ALVY's sales total goes down then. The seller's earning
for the returned items is reversed at approval, and ALVY's commission stays.

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
| Rider pay and cash | [RiderWallet](../app/Services/RiderWallet.php), [RiderDispatch](../app/Services/RiderDispatch.php) (`jobPay`) |
| Seller wallet and withdrawals | [SellerWallet](../app/Services/SellerWallet.php) |
| Hub remittances to ALVY | [HubRemittances](../app/Services/HubRemittances.php) |
| Refunds of returns | [Refunds](../app/Services/Refunds.php), [ReturnDecisions](../app/Services/ReturnDecisions.php) |
| Linehaul trip pay | `Linehaul::PAY` in [Linehaul](../app/Services/Linehaul.php) |
