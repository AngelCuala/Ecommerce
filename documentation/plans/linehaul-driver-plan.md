# Linehaul Driver account: plan (revision 2)

Status: **proposal, waiting for approval.** Nothing in this plan is built yet.

Revision 2 follows the updated spec, which centres on the driver's own account and pages. It adds:
- sign-in with a mobile number and a code;
- the application status page;
- a guided active-trip screen;
- offline scans;
- settings.

Stack: Laravel 12 (Blade pages, plain JavaScript), SQLite on your computer and in tests, MySQL on the live site. Every migration
must work on both.

---

## 1. What ALVY has today (reviewed)

| Area | Today | What it means for this plan |
|---|---|---|
| Users and roles | One `users` table with a `role` column (buyer, seller, admin, sorting_center, courier, courier_pending…). Sign-in is **by email** and password. The sign-in code is sent **by email** ([LoginOtp](../../app/Services/LoginOtp.php)). | Add the role `linehaul_driver` (and `linehaul_driver_pending` before activation). Add **sign-in by mobile number.** |
| SMS | [Sms](../../app/Services/Sms.php) sends through Semaphore, but **no key is set in `.env`**, so nothing is sent today. | Phone codes need a Semaphore account (it costs money per text). Until it is set up, codes go by email and are written to the log (question 8). |
| Logistics companies | Each sorting center is **one login.** Hubs belong to it ([LogisticsHub](../../app/Models/LogisticsHub.php)). | "Hub staff confirms handover" and "receiving hub sign-off" are done by the company account, or with a code shown to the driver (question 2). |
| Truck drivers | A rider whose vehicle is a truck gets a shadow [Courier](../../app/Models/Courier.php) record, which is **both** the driver and the truck. New courier sign-ups can no longer pick a truck (changed today). | The driver and the truck become separate records (section 2). |
| Trips | [LinehaulTrip](../../app/Models/LinehaulTrip.php) and [Linehaul](../../app/Services/Linehaul.php) (955 lines): one hub → one hub, loose parcels, GPS pings, delay, chat between companies, an audit log. Seal removed by Lawrence. | Extended: stops, sacks and cages, inspections, seal, incidents, refuels. |
| Parcel tracking | `shipment_events`, `parcels.current_hub_id`. Each trip scan already writes parcel tracking. | Sack scans write one event per parcel inside the sack. |
| Scanner | [waybill-scanner.js](../../public/js/waybill-scanner.js): camera, photo or typed code; QR and Code 128; the browser's BarcodeDetector or ZXing. | **Reused** for sack and cage labels. |
| Private files | Private disk with signed links (`ProtectedFile`). | Used for every driver and trip upload. |
| Offline support | **None** (no service worker). | Built new (section 6). |
| Languages | **No translation files**; everything is English. | Question 7. |

---

## 2. Two separate roles: rider and linehaul driver

| | Rider | Linehaul driver |
|---|---|---|
| Role | `courier` (the code's name for riders today) | **`linehaul_driver`** (new) |
| Work | Picks up from sellers and delivers to buyers (and the return pickups) | Moves sacks and cages hub to hub on company trucks |
| Vehicle | Their own: tricycle or car/van | **None of their own**; the company assigns a truck per trip |
| Sign-up | The public sign-up page ("Courier", tricycle / car) | **Separate**: an invite link from a sorting center, or the company creates them. Not on the public sign-up page. |
| Pages | Rider portal | Driver portal (`/driver`) |
| Records | `riders` | `linehaul_drivers` |

A person is one or the other. A rider is never turned into a linehaul driver by picking a vehicle. The current behaviour, where a
rider with a truck gets a hidden driver record ([TruckRiders](../../app/Services/TruckRiders.php)), is removed.

## 2b. Driver and truck become separate

| | Today | New |
|---|---|---|
| Driver | A rider with a truck plus a shadow `couriers` row | **`linehaul_drivers`**, role `linehaul_driver` |
| Truck | The same shadow `couriers` row | **`trucks`**, owned by the sorting center |
| Trip | `courier_id` | `driver_id` + `truck_id`, checked when assigned |

Existing truck riders are moved over (question 1): each becomes a driver marked "documents to re-verify", plus a company truck made
from their plate and OR/CR. `couriers` stays read-only until the move is checked.

---

## 3. Schema

### Drivers and registration

- **`users`** (existing)
  - add: `mobile_verified_at`.
  - sign-in by mobile uses the existing `phone` column (made unique for drivers).
  - roles: `linehaul_driver_pending` (draft through road test) and `linehaul_driver` (active).
- **`linehaul_drivers`**
  - Account and home: `user_id`, `sorting_center_id`, `home_hub_id`, `invite_id`.
  - Personal: names, `birthdate`, `sex`, `civil_status`, `email`, current and permanent address (PSGC codes plus street), `photo_path`.
  - Emergency contact: `emergency_name`, `emergency_relationship`, `emergency_mobile`.
  - License: `license_number`, `license_type` (must be `professional`), `dl_codes` (JSON), `license_expiry`, `years_driving`, `years_truck`.
  - Government IDs, **encrypted**: `tin`, `sss`, `philhealth`, `pagibig`.
  - Payout: `payout_method` (gcash / bank), `payout_name`, `payout_number` (**encrypted**), `bank_name`.
  - Status: draft / pending_review / needs_resubmission / for_road_test / active / rejected / suspended / deactivated.
  - Flags: `draft_step` (where to resume), `assignment_blocked` and `blocked_reason` (expired documents), `needs_reverification` (name or birthdate changed).
  - Language: `locale`.
  - Timestamps: `submitted_at`, `activated_at`.
- **`driver_documents`**: one row per file. Old versions are kept when a renewal is uploaded.
  - `driver_id`, `type` (selfie_id, license_front, license_back, nbi, police, drug_test, medical, barangay), `path`.
  - `issued_on`, `expires_on`, `clinic`, `version`.
  - `status` (pending / approved / rejected), `reason`, `reviewed_by`, `reviewed_at`.
- **`driver_agreements`**
  - `driver_id`, `agreement`, `version`, `accepted_at`, `ip`.
  - The texts are kept in the existing `platform_policies` table.
- **`driver_invites`**
  - `sorting_center_id`, `home_hub_id`, `token_hash`, `mobile`, `email`, `expires_at`, `used_at`, `created_by`.
- **`driver_road_tests`**
  - `driver_id`, `scheduled_at`, `place`, `truck_id`, `result` (passed / failed / no_show), `notes`, `recorded_by`.
- **`phone_otps`**
  - `phone`, `purpose` (signup / login / reset / handover), `code_hash`, `expires_at`, `attempts`, `sent_at`.
  - Limits: 5 tries, 60 s between sends, codes last 10 minutes.
- **`driver_audit`** (append-only)
  - `driver_id`, `user_id`, `action`, `document_id`, `old`, `new`, `reason`, `created_at`.

### Fleet (owned by the sorting center)

- **`trucks`**
  - `sorting_center_id`, `home_hub_id`, `plate_number` (unique), `vehicle_class`, `make_model`, `capacity_kg`, `capacity_m3`.
  - `status` (available / on_trip / maintenance / inactive), `assignment_blocked`.
- **`truck_documents`**
  - `truck_id`, `type` (or_cr / insurance), `path`, `expires_on`, `status`.
- **`truck_maintenance`**
  - `truck_id`, `reason`, `opened_at`, `closed_at`, `source_inspection_id`, `notes`.

### Trips

**`linehaul_trips`** (existing)
- add: `driver_id`, `truck_id`, `scheduled_arrival_at`, `odometer_start`, `odometer_end`, `fuel_level_end`, `exception`.
- Statuses: scheduled → pre_trip_inspection → loading → sealed → in_transit → arrived → unloading → (more stops: loading again) → post_trip → completed (+ cancelled).

**New trip tables**

| Table | Columns |
|---|---|
| `linehaul_trip_stops` | `trip_id`, `seq`, `hub_id`, planned and actual arrival and departure, `seal_checked`, `seal_intact`, `seal_photo_path`, `signed_off_by`, `signed_off_at` |
| `trip_inspections` | `trip_id`, `kind` (pre / post), `items` (JSON: item, ok, note), `odometer`, `odometer_photo_path`, `fuel_level`, photos, `passed` |
| `trip_seals` | `trip_id`, `stop_id`, `seal_number`, `photo_path`, `applied_at` |
| `trip_incidents` | `trip_id`, `type` (breakdown / accident / delay / seal_broken / missing_load / other), `lat`, `lng`, `notes`, photos, `new_eta_at`, `status` (open / acknowledged / resolved), `resolved_by` |
| `trip_refuels` | `trip_id`, `liters`, `amount`, `receipt_path`, `odometer` |
| `trip_discrepancies` | `trip_id`, `stop_id`, `container_id`, `kind` (missing / extra / damaged), photos, `status` |
| `trip_actions` | The audit log **and** the offline queue's receipt: `trip_id`, `driver_id`, `action`, `payload` (JSON), `client_uuid` (**unique**: the same queued action is never applied twice), `recorded_at` (time on the phone), `received_at` (time on the server) |

`linehaul_trip_events` stays as the readable history shown on the pages.

### Sacks and cages

- **`containers`**
  - `code` (printed label: QR and Code 128), `type` (sack / cage), `sorting_center_id`.
  - `status` (open / closed / on_trip / received / missing), `current_hub_id`.
- **`container_items`**
  - `container_id`, `parcel_id` or `return_shipment_id`, `added_at`, `removed_at`.
- **`trip_manifest`**
  - `trip_id`, `load_stop_id`, `unload_stop_id`, `container_id`.
  - `status` (expected / loaded / unloaded / missing / extra / damaged), `loaded_at`, `unloaded_at`.

### Settings

`config/linehaul.php`, editable on an admin settings page:
- **Documents:** the DL code list and the DL code → vehicle class mapping (starting from LTO A, A1, B, B1, B2, C, D, BE, CE, for you to check); required documents; whether the road test is required; upload limits (images and PDF, 5 MB each).
- **Reminders and trips:** reminder days (30/14/7), the GPS interval (default 60 s), the minimum time between scans.
- **Codes:** how long phone codes last.

---

## 4. Routes

Web pages under `/driver`, mobile-first. The mobile app gets the same actions under `/api/v1/driver` (question 5).

**Middleware:**
- `driver.any`: signed in as a driver, any status. Used by registration and the status page.
- `driver.active`: status active. **Every main page uses it.** An inactive driver is sent to the status page.

### Account and registration (before approval)

| Method | URL | What |
|---|---|---|
| GET/POST | `/driver/join/{token}` | Invite link: shows the company and hub, then sign-up |
| GET/POST | `/driver/register` | Mobile number + password |
| POST | `/driver/otp/send`, `/driver/otp/verify` | Phone code (signup / login / reset) |
| GET/POST | `/driver/login` | Mobile (or email) + password, then a code on a new device |
| GET/POST | `/driver/forgot`, `/driver/reset` | Forgot password with a phone code |
| GET/POST | `/driver/apply/{step}` | Steps 1–5. Each POST saves a draft; `step` = personal / license / clearances / ids / agreements |
| GET/POST | `/driver/apply/review` | Step 6: review and submit |
| GET | `/driver/status` | Application status, per-document status, road test |
| POST | `/driver/documents/{document}/reupload` | Re-upload a rejected document (also from the status page) |
| GET | `/driver/files/{document}` | Signed link to the driver's own file |

### Main pages (active only)

| Method | URL | What |
|---|---|---|
| GET | `/driver` | Dashboard |
| GET | `/driver/trips?tab=upcoming\|active` | My Trips |
| GET | `/driver/trips/{trip}` | Trip details |
| GET | `/driver/trips/{trip}/run` | The guided active-trip screen, opened at the current step |
| POST | `/driver/trips/{trip}/inspection` | Pre-trip or post-trip inspection (`kind`) |
| POST | `/driver/trips/{trip}/scan` | Scan a sack or cage (load or unload, at the current stop) |
| POST | `/driver/trips/{trip}/seal` | Seal number and photo |
| POST | `/driver/trips/{trip}/handover` | Hub handover: a code from the hub, or the hub signs off on its own page |
| POST | `/driver/trips/{trip}/depart` | Start trip, or leave a stop |
| POST | `/driver/trips/{trip}/ping` | GPS location |
| POST | `/driver/trips/{trip}/stops/{stop}/arrive` | Arrival at a stop |
| POST | `/driver/trips/{trip}/stops/{stop}/seal-check` | Seal matches and is intact, with a photo |
| POST | `/driver/trips/{trip}/discrepancies` | Missing, extra or damaged, with photos |
| POST | `/driver/trips/{trip}/delay` | Reason and new ETA |
| POST | `/driver/trips/{trip}/refuels` | Liters, amount, receipt photo |
| POST | `/driver/trips/{trip}/complete` | Complete the trip |
| POST | `/driver/trips/{trip}/incidents` | Incident report (from any step) |
| POST | `/driver/sync` | Offline queue: a list of actions, each with `client_uuid` and `recorded_at` |
| GET | `/driver/history`, `/driver/history/{trip}` | Trip history and the read-only detail |
| GET/POST | `/driver/profile` | Profile |
| GET/POST | `/driver/documents` | My Documents and renewals |
| GET | `/driver/notifications` | Notifications (the existing `user_notifications`) |
| GET/POST | `/driver/settings` | Password, language, logout |

### Sorting center side

These are needed so the driver pages have data. They go under the existing `/sorting-center`:
- driver invites;
- application review (per document) and the road test;
- the driver list, with suspend and reactivate;
- trucks, their documents and maintenance;
- sacks and cages (make, fill by scanning, print the label);
- trip scheduling with stops, driver and truck, and the manifest;
- hub handover and sign-off on the receiving side;
- the incidents queue.

---

## 5. Page-by-page

All pages are mobile-first:
- one column on phones;
- buttons at least 48 px tall;
- a fixed bottom bar for the main action;
- photos taken with the phone's camera (`capture="environment"`), resized on the phone to about 1600 px before upload, so slow connections cope.

### A. Before approval

1. **Sign up / Login.** Mobile number, then a 6-digit code, then a password.
   - Coming from an invite link, the company and hub are shown and fixed.
   - Forgot password: mobile → code → new password.
   - Five wrong codes lock the number for 15 minutes.
2. **Registration** (six steps, with a progress bar and Back / Save & continue).
   - **Saving and resuming:** each step saves as a draft, and signing in again resumes at `draft_step`.
   - **Checks:** dates can't be in the past for expiries; the license must be Professional; at least one of NBI or police clearance; the drug test and medical within the allowed age (setting).
   - **Uploads:** each file has a preview and a "replace" button.
   - **Step 6:** shows everything, with "Edit" next to each section. Submitting moves the driver to `pending_review`.
3. **Application status.**
   - A big status badge with what happens next.
   - A document list with each status, the rejection reason and a **Re-upload** button.
   - The road-test date, place and result.
   - Main pages stay locked until `active`.

### B. Main pages

4. **Dashboard.**
   - Today's trips.
   - The assigned truck: plate and class.
   - The current trip's status, with a big **Resume trip** button.
   - Alerts: documents expiring soon, schedule changes, open incidents.
5. **My Trips.** Upcoming and Active tabs. Each card shows origin → destination, departure, truck and status.
6. **Trip details.**
   - The stops in order, with times.
   - The truck.
   - The manifest by stop, with counts.
   - **Start trip**, enabled from a set time before departure (setting).

### The guided active-trip screen (steps 7–11)

- **Layout:** one page with a step bar: Inspection → Loading → In transit → Arrival → Post-trip. The step is decided by the **server** from the trip status, so steps can't be skipped. A route opened out of order shows the current step.
- **On every step:**
  - a red **Report incident** button;
  - an online/offline badge, with the number of actions waiting to sync.

7. **Pre-trip inspection.**
   - The checklist (tires, brakes, lights, fluids, fuel level, mirrors, OR/CR and insurance on board), each OK or Problem.
   - Odometer with a photo, and truck photos.
   - **Any Problem:** the trip is blocked, the truck goes to maintenance and the company is alerted. The screen says "Waiting for the sorting center to fix or replace the truck."
8. **Loading.**
   - The camera scanner opens, with the count of scanned vs expected for this stop.
   - Wrong or duplicate scans buzz and show a warning.
   - Then the seal number and photo.
   - Then the **handover**: the hub's company account confirms on its trip page, or gives the driver a 6-digit code to type in (question 2).
9. **In transit.**
   - **Start trip** records the time.
   - GPS is sent every N seconds while the page is open; the screen asks to keep it open.
   - Shown: the next stop and its ETA, Arrived at stop, and Report delay (reason and new ETA).
   - On a route with several stops, steps 10 → 8 repeat at each stop.
10. **Arrival and unloading.**
    - Arrived.
    - The seal check: the number is entered and compared, intact yes/no, a photo. A mismatch or broken seal opens an **incident automatically**.
    - The receiving hub scans the sacks off; the driver sees the count update.
    - Missing, extra or damaged items are logged with photos.
    - The hub signs off.
11. **Post-trip.**
    - Odometer with a photo, and the fuel level.
    - Refuels: liters, amount and receipt photo, any number of them.
    - The post-trip checklist; a new problem sends the truck to maintenance for the next trip.
    - **Complete trip:** the driver and the truck become available again.

### The incident report and history

12. **Incident report** (a sheet that slides up).
    - The type, photos, notes, and GPS captured automatically.
    - It is sent straight away, or queued while offline. The company is alerted, and the trip shows its exception (breakdown, delay…).
13. **Trip history.** Completed and cancelled trips: date, route, truck, duration, distance (from the odometers), and the number of incidents and discrepancies. The detail page is read-only, with the whole log.

### C. Account pages

14. **Profile.** Contacts and addresses can be edited. Changing the name or birthdate sends the profile back for checking (`needs_reverification`) without stopping current trips.
15. **My Documents.**
    - Each document shows its status and expiry date, and expired ones are shown in red.
    - A renewal is uploaded as a new version and goes to pending.
    - If a required document is expired, a banner says "You can't be assigned trips until it's renewed."
16. **Notifications.** Trip assigned, changed or cancelled; reminders at 30/14/7 days before a document expires; re-upload requests; incident updates. These are the existing in-app notices, plus SMS for the urgent ones once SMS is set up.
17. **Settings.** Change password (with a phone code), language, log out.

---

## 6. Offline scans and photos

- **Saving offline:** a small **service worker** keeps the trip screen and scanner usable without signal. Scans, seal checks, inspections, incidents and photos are saved on the phone (IndexedDB), each with a `client_uuid` and the **time on the phone**.
- **Sending later:** when the signal is back, they are sent in order to `/driver/sync`. The server:
  - applies each action at most once (`client_uuid` is unique);
  - keeps the phone's time as `recorded_at`, refusing times in the future or older than the trip;
  - runs the same checks as online;
  - returns a result per action.
- **What isn't queued:** actions that need someone else, such as a hub handover code, a hub sign-off or a sorting center decision. These say "Needs signal".
- **Photos** are compressed on the phone before they are saved, so the queue stays small.

---

## 7. Rules

- **Who sees what:** drivers see only their own profile, documents and trips (checked in each controller and tested). Sorting centers see only their own drivers, trucks and trips.
- **When a driver can be assigned a trip:**
  - the driver is active, not blocked, and has no required document expired;
  - their DL codes allow the truck's class;
  - they have no overlapping trip;
  - the truck is available, not blocked, and has no open maintenance.
- **Daily job:** reminders 30, 14 and 7 days before expiry (drivers and trucks). On the expiry day, the driver or truck is blocked and future trips not yet started are unassigned, with a notice. An approved renewal unblocks them.
- **Audit:** every trip action goes into `trip_actions` with both times, and every verification step into `driver_audit`.
- **Parcels and returns:** a sack scanned onto a trip, or off it at a hub, writes a tracking event for every parcel and return inside it, and moves them at_sorting_center → in_transit → at_destination_hub. A missing sack flags everything inside for investigation; after X days they become lost, and returns use the liability flow from the returns plan.

---

## 8. Phases

Each phase ends with its tests and the **full suite passing.** I stop for your review before starting the next.

1. **Schema, role, permissions and sign-in.** Tables; the roles and middleware; phone codes (email fallback); invite links; mobile sign-in; forgot password; moving truck riders to drivers and trucks (with a backup).
2. **Registration and status.** The six-step wizard with drafts, uploads, encryption and agreements; the status page with re-upload; the sorting center review queue (per document) and road test.
3. **Dashboard, My Trips, Trip details.** Plus the sorting center side these need: trucks, sacks and cages, trips with stops and manifest, and the assignment checks.
4. **Active trip flow.** Inspection → loading and seal and handover → in transit with GPS and stops → arrival with seal check and discrepancies → post-trip.
5. **Incidents and history.**
6. **Profile, documents, notifications, settings.** Plus the daily expiry and reminder job and the blocks.
7. **Parcel and return integration, and offline sync.** Sack scans write per-parcel events; missing sacks; the service worker and `/driver/sync`.

**Tests:**
- **Registration:** registration with a resubmission; an inactive driver sent away from the main pages.
- **Assignment:** an expired license blocks assignment; DL code vs truck class.
- **Trip flow:** a failed inspection blocks the start; a manifest count mismatch; a seal mismatch opens an incident.
- **Offline:** an offline sync with an old timestamp, sent twice and applied once.
- **Parcels:** a sack scan writes an event per parcel.
- **Full happy path:** two stops.
- **Permissions:** for each role.

## 9. Risks

- **It replaces most of the current linehaul code,** including Lawrence's recent trip rules (driver accept/decline; seal removed). Agree it with him first.
- **Overlap with the returns plan:** returns phase 4 and phase 7 here touch the same scan code (question 6).
- **Offline is the hardest part:** browsers stop GPS and JavaScript when the screen is off. GPS is only reliable while the page is open, or in the mobile app.
- **SMS costs money,** and nothing is sent until a Semaphore key is set.
