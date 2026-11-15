# 10 · Things that run on their own

## Scheduled jobs

Some work isn't triggered by anyone pressing a button; it has to happen on a timer. For example,
an order should complete by itself 7 days after delivery even if nobody opens the site.

Laravel handles this with a **scheduler**. The list of timed jobs is in `routes/console.php`, and
each job is a command in `app/Console/Commands/`. On your computer, start the scheduler with:

```bash
php artisan schedule:work
```

(On a real server, a system timer runs `php artisan schedule:run` every minute.)

| Job | How often | What it does |
|---|---|---|
| Cancel unpaid orders | every minute | Cancels online orders nobody paid for in time, and returns the stock |
| Flash sale reminders | every minute | Tells buyers who pressed *Remind me* that a sale is starting |
| Check GCash receipts | every minute | Has the AI read new receipts for the admin; sends reminders after 24 hours |
| Expire old rider offers | every minute | Cleans up old job offers |
| Flag delayed transfers | every 15 minutes | Marks parcels between hubs that are taking too long |
| Read product colours | every 10 minutes | Has the AI note the colours in new product pictures, for colour search |
| Make small pictures | every 10 minutes | Makes small copies of product pictures, so pages load faster on phones |
| Complete delivered orders | hourly | Completes orders 7 days after delivery |
| Approve unanswered returns | hourly | Approves returns the seller ignored for 3 days |
| Put barangays on the map | hourly | Looks up where each barangay is, for "nearest rider" |
| Remove old app logins | daily | Deletes expired app tokens |
| Remove old strikes | daily | Deletes contact strikes older than a year |

There are also commands you run by hand, like `php artisan app:publish-apk` (release a new app
version) and `php artisan psgc:import` (load a newer government address list).

## Outside services

ALVY uses a few services from other companies. **None of them is required.** Without its key, a
feature is simply switched off or falls back to something simpler, so the site still works on a
fresh computer. The keys go in the `.env` file.

| Service | What ALVY uses it for | Without it |
|---|---|---|
| **Google Gemini** (AI) | ALLIE's answers, reading product colours, reading GCash receipts | ALLIE answers common questions by itself; no colour search; admins read receipts themselves |
| **Anthropic Claude** (AI) | Checking that uploaded IDs are real government IDs; can also power ALLIE | IDs are accepted and marked for an admin to check |
| **Google sign-in** | *Continue with Google* | The button doesn't work; email sign-in still does |
| **PayMongo** | Online payments (GCash, Maya, cards) | Only cash on delivery and GCash receipts |
| **Firebase** | Push notifications to phones | Notifications still show inside the app and website |
| **Semaphore** | Sending codes by text message | Codes go by email only |
| **OpenStreetMap** | Turning a map pin into an address, placing barangays on the map | Addresses are picked from dropdowns |
| **Email** | Codes, approvals, activation links | On your computer emails are written to a log file instead (`MAIL_MAILER=log`) |

## Notifications

When something happens to an order, a parcel or a rider's job, ALVY writes a notification (the bell
icon) for the people involved: the buyer, the seller and the rider. If Firebase is set up, the same
notification is pushed to their phones. Each job event notifies only once, even if a button is
double-clicked.

## Addresses: the government list

Every address dropdown (region, province, town, barangay) comes from the government's official
**PSGC** list. ALVY keeps a copy in `resources/data/psgc/`, so it never needs the internet for this.

Addresses are saved with their **codes**, not just their names. That matters because routing and
shipping compare codes: "Sta. Cruz" and "Santa Cruz" are the same code, so they can never be
mixed up by a typo.

## Where to find it in the code

| What | File |
|---|---|
| The schedule | [routes/console.php](../routes/console.php) |
| The jobs | [app/Console/Commands/](../app/Console/Commands) |
| ALLIE's AI | [GeminiAssistantModel](../app/Services/Assistant/GeminiAssistantModel.php), [ClaudeAssistantModel](../app/Services/Assistant/ClaudeAssistantModel.php) |
| ID checks | [IdDocumentChecker](../app/Services/IdCheck/IdDocumentChecker.php) |
| Colour reading | [ProductColorTagger](../app/Services/ProductColorTagger.php) |
| GCash receipt reading | [GcashChecks](../app/Services/GcashChecks.php) |
| Payments | [PayMongoGateway](../app/Services/Payments/PayMongoGateway.php) |
| Push notifications | [FcmPush](../app/Services/FcmPush.php) |
| Text messages | [Sms](../app/Services/Sms.php) |
| Map pin to address | [GeoController](../app/Http/Controllers/Shared/GeoController.php) |
| Nearest rider | [NearestRiders](../app/Services/NearestRiders.php) |
| Emails | [app/Mail/](../app/Mail), pages in `resources/views/emails/` |
| Notifications | [OrderNotifier](../app/Services/OrderNotifier.php), [ShipmentTracker](../app/Services/ShipmentTracker.php), [RiderNotifier](../app/Services/RiderNotifier.php), [UserNotification](../app/Models/UserNotification.php) |
| Government address list | [PsgcDirectory](../app/Services/PsgcDirectory.php), [PsgcController](../app/Http/Controllers/Shared/PsgcController.php) |
