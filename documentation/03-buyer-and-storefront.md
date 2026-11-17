# 3 · The shop, for buyers

## Browsing

Anyone can browse the shop without an account. The home page shows the admin's event banners,
the categories, running flash sales and products. From there a buyer can:

- **Search** with words and filters: price range, rating, category, store, the seller's town,
  and whether to show sold-out items. Searching for a colour works in English or Tagalog
  ("red" or "pula"), because an AI looks at each product picture once and notes its colours.
- **Open a product** to see its photos, options (like colour and size), price, reviews and the store.
- **Open a store page** to see all of a seller's products and vouchers, and follow the store.

Only products an admin has approved are shown.

### How the price is decided

A product can have up to three prices at once, and the buyer always pays the lowest one that
applies right now:

1. a **flash sale** price, while the flash sale is running and there are units left at that price;
2. otherwise the store's **sale price**, while the sale is running;
3. otherwise the **regular price**.

A seller can also limit how many of an item one buyer may buy. The same rules decide the price
on the product page, in the cart, at checkout and in the app.

### Trusted Seller badge

Stores that sell well get a "Trusted Seller" badge: enough completed orders, a good rating, and no
warning for sharing contact details in the last 90 days.

## The cart

Buyers add items to the cart, change quantities or remove them. The cart checks stock every time,
so a buyer can't order more than is left. **Buy now** skips the cart and goes straight to checkout
with just that one item.

If a guest presses *Add to cart*, *Buy now* or the heart (wishlist), ALVY remembers it, asks them
to log in or sign up, then does it for them and takes them back to the product.

## Checking out

This is what happens when a buyer checks out:

1. **Choose what to buy and where to send it.** The buyer ticks the cart lines and picks one of
   their saved addresses. The address must be in Laguna.
2. **One order per store.** If the buyer bought from two stores, ALVY makes two separate orders,
   one per store, because each store ships its own parcel. Each has its own shipping fee.
3. **Discounts.** First any store promotion applies (like "buy 2, get 1 free"), then vouchers:
   one store voucher per store, one ALVY voucher, and one free-shipping voucher. The totals are
   recalculated on the server every time the buyer changes something; the page only displays them.
   ([Page 8](08-payments-vouchers-shipping.md) explains the maths.)
4. **Pay.** The buyer chooses:
   - **Cash on delivery**: the order goes straight to the seller.
   - **GCash to ALVY**: the buyer scans ALVY's GCash QR, pays, and uploads the receipt with its
     reference number. The order waits until an admin confirms the money arrived. If the admin
     rejects it, the buyer can upload the correct receipt on the order page.
   - **Online payment** (GCash, Maya or card through PayMongo): the buyer is sent to the payment
     page. The order waits as "Awaiting Payment" and is cancelled automatically if it isn't paid
     within an hour.
5. **Place the order.** Stock is taken off, the voucher is used up, and the seller is notified.

## After ordering: My Purchases

An order moves through these steps, and the buyer sees it under a matching tab:

```
To Pay  →  To Ship  →  To Receive  →  Completed
(paying)   (seller is    (on its way /   (done)
           preparing)    delivered)
```

What the buyer can do along the way:

- **Cancel** an order until the seller has booked the pickup. Stock and vouchers go back.
- **Track** it. The timeline only shows steps that really happened (picked up, at the hub,
  out for delivery…), and the next expected step.
- **See the delivery photo** the rider took. Only the buyer, the seller and admins can see it.
- **Order Received**: confirm a delivered order. If the buyer doesn't, it completes by itself 7 days
  after delivery.
- **Return or refund**: within 7 days of delivery, with photos (see [Returns and refunds](#returns-and-refunds) below).
  (Returns can be switched off with `RETURNS_ENABLED=false`.)
- **Review** each item of a completed order: stars, text and photos, editable once. The seller can
  reply.

## Returns and refunds

1. **Asking:** on a delivered order the buyer presses *Return / Refund*, chooses the reason, the
   items (all or some of them), and adds 1 to 5 photos. A rider is booked at once to collect the
   item ([page 5](05-sorting-center.md#returns-from-buyers)).
2. **The seller answers** within 3 days: approve, or reject with a reason. No answer in 3 days means
   it is approved automatically. After a rejection the buyer can **escalate** it to ALVY within 3
   days, and an admin decides ([page 7](07-admin-panel.md#return-disputes)).
3. **Approved:** the buyer is asked **where to send the money**: GCash, Maya or a bank account (bank
   chosen from a list), the name on the account and the number (11 digits for GCash/Maya, 8 to 16
   for a bank). The number is stored encrypted, and once saved the details are locked. An order paid
   online (card, GCash online, Maya) skips this: the money goes back the way it was paid.
4. **The money is sent as soon as the item is back with the seller.** When the rider hands the item
   to the seller, the refund becomes urgent for ALVY's admins, who send it and type the transfer's
   reference number ([page 7](07-admin-panel.md#refunds)). If the item came back before the buyer
   gave the details, it is sent as soon as they do.
5. The buyer is told at each step (approved, item back, being sent, sent with the reference). If the
   details don't work, ALVY sends them back with the reason and the buyer enters them again.

The order page shows a **return card** at the top with the steps (Requested → Seller approved →
Your refund details → Item picked up → Back to the seller → Refund sent), everything that happened
with its time, the photos, and the details form when it's needed. The order shows *Return approved ·
refund pending* until the money is sent, and *Refunded* after.

## My Account

The buyer's account pages hold their profile, saved **addresses**, order list, **notifications**,
**wishlist**, **My Vouchers** (vouchers they've claimed for later), followed stores and security
settings. Addresses are picked from the official dropdowns or by dropping a pin on a map, which
ALVY turns into the matching barangay. One address is always the default.

## Chatting with sellers

Buyers can message a seller from a product, a store or an order. To keep deals on ALVY, phone
numbers, emails, links and social media names typed in these chats are hidden, and the seller gets
a warning (see [contact strikes](04-seller-centre.md#warnings-for-sharing-contact-details)).

## ALLIE, the shop assistant

ALLIE is the chat bubble that answers buyers' questions ("where is my order?", "do you have red
shoes?", "what's the return policy?"). It works like this:

1. The question goes to an AI model (Google Gemini by default).
2. The AI can only use a fixed list of safe lookups: real products, **this buyer's own** orders,
   and the policies. It can't see anyone else's data.
3. It can *prepare* three actions (add to cart, cancel an order, contact support), but nothing
   happens until the buyer presses **Confirm**. Then the site's normal code does it, with all the
   normal rules.
4. If there is no AI key or the AI is down, ALLIE still answers the common questions by itself.

## Where to find it in the code

| What | File |
|---|---|
| Home page | [Storefront/HomeController](../app/Http/Controllers/Storefront/HomeController.php), view `home/index` |
| Search and filters | [Storefront/ShopController](../app/Http/Controllers/Storefront/ShopController.php), [ProductSearch](../app/Support/ProductSearch.php) |
| Product page | [Storefront/ProductController](../app/Http/Controllers/Storefront/ProductController.php), view `products/show` |
| Store page, following | [Storefront/StoreController](../app/Http/Controllers/Storefront/StoreController.php) |
| Categories | [Storefront/CategoryPageController](../app/Http/Controllers/Storefront/CategoryPageController.php) |
| Price rules | [Product](../app/Models/Product.php), [FlashSale](../app/Models/FlashSale.php) |
| Colour search | [ProductColors](../app/Support/ProductColors.php), [ProductColorTagger](../app/Services/ProductColorTagger.php) |
| Trusted Seller | [TrustedSeller](../app/Support/TrustedSeller.php) |
| Cart | [Buyer/CartController](../app/Http/Controllers/Buyer/CartController.php), [CartItem](../app/Models/CartItem.php) |
| Remembering actions of guests | [PendingShopAction](../app/Support/PendingShopAction.php) |
| Checkout | [Buyer/CheckoutController](../app/Http/Controllers/Buyer/CheckoutController.php), `public/js/checkout.js` |
| Online payment | [Buyer/PaymentController](../app/Http/Controllers/Buyer/PaymentController.php), [PaymentService](../app/Services/PaymentService.php) |
| Re-upload a GCash receipt | [Buyer/GcashResubmitController](../app/Http/Controllers/Buyer/GcashResubmitController.php) |
| Cancel, Order Received, returns, refund details (`saveRefundDetails`) | [Buyer/OrderController](../app/Http/Controllers/Buyer/OrderController.php), [ReturnRequest](../app/Models/ReturnRequest.php) |
| Return card on the order page | [ReturnProgress](../app/Support/ReturnProgress.php), views `profile/_return-progress`, `profile/_return-modal` |
| Refunds: details, when the money is sent | [Refunds](../app/Services/Refunds.php), [Refund](../app/Models/Refund.php) |
| Order steps | [Order](../app/Models/Order.php) (the comment at the top) |
| Tracking timeline | [ShipmentProgress](../app/Support/ShipmentProgress.php) |
| Reviews | [Buyer/ReviewController](../app/Http/Controllers/Buyer/ReviewController.php), [Review](../app/Models/Review.php) |
| My Account | [Buyer/ProfileController](../app/Http/Controllers/Buyer/ProfileController.php), views in `resources/views/profile/` |
| Addresses, map pin | [Buyer/AddressController](../app/Http/Controllers/Buyer/AddressController.php), [Shared/GeoController](../app/Http/Controllers/Shared/GeoController.php), [Shared/PsgcController](../app/Http/Controllers/Shared/PsgcController.php) |
| Wishlist, vouchers, flash sale reminders | [WishlistController](../app/Http/Controllers/Buyer/WishlistController.php), [VoucherWalletController](../app/Http/Controllers/Buyer/VoucherWalletController.php), [FlashSaleReminderController](../app/Http/Controllers/Buyer/FlashSaleReminderController.php) |
| Chat | [Shared/MessageController](../app/Http/Controllers/Shared/MessageController.php), [ContactGuard](../app/Support/ContactGuard.php) |
| ALLIE | [Shared/AssistantController](../app/Http/Controllers/Shared/AssistantController.php), [ShopAssistant](../app/Services/Assistant/ShopAssistant.php), [AssistantTools](../app/Services/Assistant/AssistantTools.php) |
