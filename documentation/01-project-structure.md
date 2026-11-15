# 1 · How the code is organized

## The big picture

ALVY is a Laravel app. Laravel splits the work of answering a web request into layers, and
each layer has its own folder. When someone opens a page, the request passes through them in order:

```
Browser or app
   │  asks for /cart
   ▼
routes/web.php ............ "which code answers /cart?"  → CartController
   ▼
Middleware ................ "is this person allowed here?"  (e.g. only buyers)
   ▼
Controller ................ reads the request, asks the services/models for what it needs
   ▼
Services and models ....... the business rules and the database
   ▼
View (Blade page) ......... builds the HTML that is sent back
```

Once you know this, finding code is easy: look up the address in the
[routes reference](routes-reference.md), open the controller it names, and follow it from there.

## The main folders

**`app/`** holds all the PHP code. Inside it:

- **`app/Http/Controllers/`**: one controller per screen or group of screens. They are grouped
  by **who uses them**, so the folder name tells you the part of the system:
  - `Storefront/` is the shop anyone can browse: home, search, product pages, store pages.
  - `Buyer/` is what a signed-in buyer does: cart, checkout, payments, orders, reviews, My Account.
  - `Shared/` is used by everyone: chat, changing passwords, profile photos, address dropdowns,
    the ALLIE assistant.
  - `Auth/` is logging in, signing up and resetting passwords.
  - `Seller/` is the Seller Centre.
  - `SortingCenter/` is the logistics company's portal.
  - `Courier/` is the rider portal (the name is older than the word "rider").
  - `Admin/` is the admin panel. `Admin/Logistics/` is the admin's view of all deliveries.
  - `Api/V1/` is the mobile app's version of all of the above ([page 9](09-mobile-app-api.md)).
- **`app/Services/`** holds the business rules: how a checkout is priced, which rider gets a job,
  how a truck trip works, how earnings are worked out. Controllers stay short and call these.
  Both the website and the app use the same services, which is why they always agree.
- **`app/Models/`**: one file per database table (Order, Parcel, Rider…). See [page 11](11-database-models.md).
- **`app/Support/`**: small helpers, such as shared form rules, QR codes and barcodes, the account
  menus, and the shapes of the app's JSON answers.
- **`app/Http/Middleware/`**: the "are you allowed here?" checks for each portal.
- **`app/Rules/`**: custom form checks, e.g. "this upload must be a government ID" or "this address
  must be in Laguna".
- **`app/Mail/`**: the emails ALVY sends (codes, approvals, activation links).
- **`app/Console/Commands/`**: jobs that run on a timer or by hand ([page 10](10-background-jobs-and-integrations.md)).

**`resources/views/`** holds the pages, again one folder per part: `seller/`, `sc/` (sorting
center), `courier/` (riders), `admin/`, `profile/` (buyer's My Account), and so on.
`components/` has the page frames each portal is drawn inside (its sidebar and header), and
`partials/` has pieces used on many pages, like the navigation bar, the chat bubble and the waybill.

**`routes/`** lists every address: `web.php` for the website, `api.php` for the app, and
`console.php` for the timer schedule.

**`database/`**: `migrations/` builds the tables, `seeders/` fills them with demo data.

**`config/`**: settings. ALVY's own are `marketplace.php` (commission, returns on or off),
`shipping.php` (shipping rates), `payments.php`, `service_area.php` (Laguna) and `logistics.php`.

**`public/js/` and `public/css/`**: the JavaScript and styles the pages use, such as the checkout
calculator, the QR scanner and the address dropdowns.

**`tests/`**: automatic tests, grouped like the controllers ([page 12](12-testing-and-demo-data.md)).

## Where to put new code

- A new page for sellers? Its controller goes in `app/Http/Controllers/Seller/`, its page in
  `resources/views/seller/`, and its route in the seller section of `routes/web.php`.
- A rule that both the website and the app need? Put it in a service in `app/Services/` and call
  it from both controllers.
- Start each new class with a short comment saying what it does. That comment is how the next
  person (and these docs) will understand it.

## Naming

- Route names start with the portal: `seller.orders`, `sc.riders`, `courier.trips`, `admin.users`.
  Buyer pages have no prefix (`cart.index`, `checkout.index`).
- Controller methods that show a page are named after it (`index`, `show`, `riders`); methods that
  change something are verbs (`approve`, `assign`, `depart`).
- Some names are older than the features: products are "books" in the seller code (`BookController`),
  and riders use the "courier" portal. They work fine; just know what they mean.
