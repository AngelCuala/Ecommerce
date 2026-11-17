# ALVY documentation

## What ALVY is

ALVY is an online marketplace for the province of Laguna. Think of it as three businesses working
together on one website:

1. **Sellers** open a store and list their products.
2. **Buyers** browse, add things to their cart and place orders.
3. **Logistics companies** (we call them *sorting centers*) collect each parcel from the seller,
   bring it to their hub, and send a rider to deliver it to the buyer. If the buyer lives in another
   town, a truck first carries the parcel to the sorting center of that town.

ALVY's own staff (the **admins**) approve new sellers and logistics companies, check payments and
keep everyone following the rules.

The same system also runs the **mobile app**. The app doesn't have its own separate backend; it
talks to this website through an API, so the website and the app always follow the same rules.

## How to read these pages

Each page explains one part of the system in plain words: what it is for, how it works step by
step, an example, and at the end **where to find it in the code**. Read them in any order, but if
you're new, read them in this order:

1. [How the code is organized](01-project-structure.md): the folders and what each one is for
2. [Accounts and signing in](02-accounts-and-sign-in.md): who can use ALVY and how they log in
3. [The shop, for buyers](03-buyer-and-storefront.md): browsing, cart, checkout, orders
4. [The Seller Centre](04-seller-centre.md): how sellers run their store
5. [The sorting center](05-sorting-center.md): how a parcel travels through a hub
6. [Riders and trucks](06-riders-and-hub-to-hub-trips.md): pickups, deliveries and hub-to-hub trips
7. [The admin panel](07-admin-panel.md): what ALVY staff do
8. [Money: payments, vouchers and shipping fees](08-payments-vouchers-shipping.md)
9. [The mobile app's API](09-mobile-app-api.md)
10. [Things that run on their own](10-background-jobs-and-integrations.md): scheduled jobs and outside services
11. [The database](11-database-models.md): what is stored, and the steps an order and a parcel go through
12. [Testing and demo accounts](12-testing-and-demo-data.md)

For looking things up there is also the [routes reference](routes-reference.md): every web address
in the system and the code that answers it.

## The life of one order

This is the whole system in one story. Every step links to the page that explains it.

1. **Maria (a buyer in Santa Cruz)** finds a bag from a seller in Calamba, adds it to her cart and
   checks out, paying cash on delivery. → [The shop](03-buyer-and-storefront.md#checking-out)
2. **The seller** sees the new order, accepts it, packs the bag, prints the waybill (the label with
   a QR code) and presses *Ready for Pickup*, choosing the Calamba logistics company.
   → [Seller Centre](04-seller-centre.md#handling-an-order)
3. **The Calamba sorting center** accepts the pickup request. Its rider for the seller's barangay
   goes to the seller, takes a photo of the parcel, scans the waybill and brings it to the hub.
   → [Sorting center](05-sorting-center.md#step-1-the-seller-asks-for-a-pickup)
4. At the hub, staff **scan and receive** the parcel, then **sort** it. Maria lives in Santa Cruz,
   which this company doesn't deliver to, so the parcel is sorted to the Santa Cruz sorting center.
5. A **linehaul driver** shows the Handover QR, the hub scans the parcel onto the truck with others
   going the same way, and the driver takes them to the Santa Cruz hub.
   → [Linehaul drivers](06-riders-and-hub-to-hub-trips.md#linehaul-drivers-hub-to-hub-trips)
6. **The Santa Cruz sorting center** receives the parcels and sorts Maria's to her
   barangay. Its rider delivers it, takes a proof-of-delivery photo and collects the cash.
   → [Riders](06-riders-and-hub-to-hub-trips.md#a-delivery-step-by-step)
7. **Maria** taps *Order Received* (or the order completes by itself after 7 days). Now the
   seller's earnings and the sorting centers' shares are final. → [Money](08-payments-vouchers-shipping.md)
8. *If Maria returns it instead:* a rider collects it, the hubs send it back to the seller, and once
   the seller has it ALVY sends Maria's refund to the GCash, Maya or bank account she gave.
   → [Returns and refunds](03-buyer-and-storefront.md#returns-and-refunds)

## A few words you'll see everywhere

These are Laravel words (the PHP framework ALVY is built with) and ALVY words.

| Word | What it means |
|---|---|
| **Route** | A web address and which code answers it, e.g. `/cart` is answered by `CartController`. All routes are listed in `routes/web.php` (website) and `routes/api.php` (app). |
| **Controller** | The code that runs when someone opens a page or presses a button. It checks the request, asks the services to do the work, and picks the page to show. |
| **Service** | Where the real business rules live, e.g. "how much is shipping" or "which rider gets this job". Kept separate so the website and the app share them. |
| **Model** | One kind of record in the database (an Order, a Parcel, a Rider) and what you can ask about it. |
| **View** (Blade page) | The HTML page the user sees, in `resources/views/`. |
| **Middleware** | A check that runs before a controller, e.g. "only sellers may open this". |
| **Migration** | A file that creates or changes a database table. |
| **Seeder** | A file that fills the database with sample data. |
| **Sorting center** | A logistics company's account. It has hubs, riders, and the barangays it delivers to. |
| **Hub** | A building a logistics company works from, with a code like `HUB-SCZ`. |
| **Rider / linehaul driver** | A rider picks up from sellers and delivers to buyers. A linehaul driver drives a truck between hubs. Two separate jobs. |
| **Handover QR** | The changing QR code a linehaul driver shows so a hub can load or unload the truck. |
| **Refund** | The money of an approved return, sent to the buyer once the item is back with the seller. |
| **Waybill** | The printed label on a parcel, with a barcode and a QR code holding the waybill number. |
| **PSGC** | The government's official list of every region, province, town and barangay. ALVY keeps a copy and saves addresses with its codes, so "Sta. Cruz" and "Santa Cruz" can never be confused. |
| **COD** | Cash on delivery. |
| **A12, B4, L3 …** | Requirement codes from the project workbook (A = shared rule, B = buyer, S = seller, L = logistics, R = rider). You'll see them in code comments and test names. |

## Starting the project on your computer

```bash
composer run setup                 # installs everything, creates .env, sets up the database
php artisan db:seed --class=DemoAccountsAndProductsSeeder   # adds demo accounts and products
php artisan serve                  # opens the site at http://127.0.0.1:8000
```

The demo logins are in [Testing and demo accounts](12-testing-and-demo-data.md#demo-accounts).

## Keeping these pages up to date

- Most PHP files start with a comment explaining what they do. These pages were written from
  those comments, so when you change how something works, update the comment and the page.
- After adding routes, regenerate the routes reference from `php artisan route:list --json`.
- After adding a table, run `php artisan migrate` and add the model to [The database](11-database-models.md).
- Plans for work not built yet (or only partly) are in [plans/](plans), e.g. the refund plan
  (revision 3) and the return-routing plan.
