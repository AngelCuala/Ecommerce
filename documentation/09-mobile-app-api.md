# 9 · The mobile app's API

## What the API is

The ALVY Android app doesn't have its own server. It talks to this same Laravel website through
web addresses starting with `/api/v1/`. Instead of HTML pages, these addresses answer with
**JSON**, plain data the app turns into its own screens.

Every role has app screens: buyers, sellers, logistics companies, riders and truck riders. (Admins
use the website only.)

## The main idea: the app reuses the website's code

The most important thing to understand is that **most API code doesn't contain any rules of its
own**. When the app asks to, say, add something to the cart, the API controller runs the
*website's* cart code, then turns the result into JSON:

- if the website would show a page, the app gets the data that page would show;
- if the website would show a success message, the app gets that message;
- if the website would show form errors, the app gets those errors.

This is why the app and the website always behave the same: there is only one copy of each rule.
Fix a rule on the website and the app is fixed too.

A few helpers shape the JSON so that prices, labels and buttons look the same as on the website:
one each for buyers, sellers, logistics companies, riders and trips (the `*Json` files in
`app/Support/`).

## Signing in from the app

The app signs in with the same email and password rules as the website. Instead of keeping a
browser session, it gets a **token**, a long secret string it sends with every request to prove who
it is.

- A successful sign-in answers with the token and the user, including which part of the app to
  open (buyer, seller, rider, logistics…).
- If the account still has to confirm its email, the answer says so, and the app shows the code
  screen.
- Google sign-in is supported too.
- An app login lasts a week.

Some website features remember things between steps (like which cart lines are ticked for
checkout). To make those work for the app, each app login is given its own private session on the
server.

## How the API is grouped

| Address starts with | Used by | Examples |
|---|---|---|
| `/api/v1/auth` | anyone | sign in, sign up, email code, forgot password |
| `/api/v1/home`, `/shop`, `/products`, `/stores`, `/categories` | guests and buyers | browsing |
| `/api/v1/cart`, `/checkout`, `/orders`, `/profile`, `/addresses`, `/wishlist`, `/vouchers` | buyers | shopping and My Account |
| `/api/v1/assistant` | guests and buyers | ALLIE |
| `/api/v1/seller` | sellers | products, orders, vouchers, reports |
| `/api/v1/logistics` | sorting centers | the whole hub pipeline, riders, trips |
| `/api/v1/rider` | riders | jobs, pickups, deliveries, pay, cash |
| `/api/v1/linehaul` | truck riders | trips |
| `/api/v1/messages`, `/devices`, `/account/password` | everyone | chat, push notifications, password changes |

Each group checks the same "are you allowed here?" rules as the matching website portal. For
example, only approved sellers can use `/api/v1/seller`. Every address is listed in the
[routes reference](routes-reference.md#mobile-app-api-apiv1).

## Push notifications

After signing in, the app registers the phone. From then on every in-app notification is also
sent to the phone through Firebase, so it appears even when the app is closed.

## Releasing a new app version

Run `php artisan app:publish-apk`. The website's "Get the app" page then offers the new APK, and
phones with an older version are told to update the next time they open the app.

## Where to find it in the code

| What | File |
|---|---|
| All API addresses | [routes/api.php](../routes/api.php) |
| Running the website's code for the app | [CallsWeb](../app/Http/Controllers/Api/V1/Concerns/CallsWeb.php) |
| Signing in | [Api/V1/AuthController](../app/Http/Controllers/Api/V1/AuthController.php) |
| A private session per app login | [ApiWebSession](../app/Http/Middleware/ApiWebSession.php) |
| Buyer screens | [Api/V1/Buyer/](../app/Http/Controllers/Api/V1/Buyer), JSON in [ApiJson](../app/Support/ApiJson.php) |
| Seller screens | [Api/V1/Seller/](../app/Http/Controllers/Api/V1/Seller), [SellerJson](../app/Support/SellerJson.php) |
| Logistics screens | [Api/V1/Logistics/](../app/Http/Controllers/Api/V1/Logistics), [LogisticsJson](../app/Support/LogisticsJson.php) |
| Rider screens | [Api/V1/Rider/](../app/Http/Controllers/Api/V1/Rider), [RiderJson](../app/Support/RiderJson.php) |
| Truck rider screens | [Api/V1/Linehaul/](../app/Http/Controllers/Api/V1/Linehaul), [LinehaulJson](../app/Support/LinehaulJson.php) |
| Who may use which group | `Api*` files in [app/Http/Middleware/](../app/Http/Middleware) |
| Push notifications | [PushDeviceController](../app/Http/Controllers/Api/V1/PushDeviceController.php), [FcmPush](../app/Services/FcmPush.php) |
| Releasing the app | [PublishApk](../app/Console/Commands/PublishApk.php), [AppDownloadController](../app/Http/Controllers/Storefront/AppDownloadController.php) |
