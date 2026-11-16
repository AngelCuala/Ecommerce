# Return pickup routing: plan

Status: **proposal, waiting for approval.** Nothing in this plan is built yet.

The goal: when a return is booked, ALVY decides by itself
- which branch picks the item up from the buyer;
- which sorting center or centers it passes through;
- whether it needs a linehaul trip;
- which branch hands it back to the seller.

The plan builds on the [returns plan](returns-and-refunds-plan.md) (return_shipments) and the
[linehaul driver plan](linehaul-driver-plan.md) (trips, sacks, lanes).

Stack: Laravel 12, SQLite on your computer and in tests, MySQL on the live site.

---

## 1. What ALVY has today (reviewed)

| Area | Today | Gap for this spec |
|---|---|---|
| **The logistics network** | A "sorting center" is a **logistics company account** (`users.role = sorting_center`) tied to **one town** (`assigned_municipality` + code): Luisiana Highland Logistics, Pagsanjan Falls Freight, Santa Cruz Lakeshore Logistics… Each has its hub or hubs ([LogisticsHub](../../app/Models/LogisticsHub.php)), its riders, and its barangay coverage ([DeliveryArea](../../app/Models/DeliveryArea.php), with PSGC barangay codes). | There is **no branch → sorting center hierarchy**: each company is both. Any two different towns means two companies, so a trip. See **question 1**, the main decision. |
| **Addresses** | Buyer addresses (`user_addresses`) store PSGC `region`, `province_code`, `municipality_code` and `barangay_code` from dropdowns. Seller addresses (`seller_applications`) store `province_code` and `municipality_code`, but the **barangay as text only**. All addresses are checked against the service area ([ServiceArea](../../app/Support/ServiceArea.php), Laguna). | Seller addresses need `barangay_code`, with existing ones filled in from the PSA list. |
| **Choosing who handles a parcel** | Forward delivery: the company whose `assigned_municipality` matches the buyer's town (`ParcelService::coversDestination`, which compares **names**). Otherwise the hub nearest the buyer (`Linehaul::bestHubFor`). Barangay = the company's DeliveryArea with that code. | There is no service-area table, no barangay-over-town priority, no "unserviceable" flag and no admin page. Matching should use codes, not names. |
| **Returns** | [ReturnShipments](../../app/Services/ReturnShipments.php): the company that **delivered** the order collects the item, receives it at its hub, and **its own rider takes it to the seller**, even in another town. There is no linehaul and no route. | Everything in sections 2–5 of the spec. |
| **Linehaul** | Trips between two companies' hubs: the receiving company accepts, the Handover QR at loading and arrival, loose parcels. No lanes, no trip timetable, no sacks. | Lanes and "next trip on the lane" are new. Sacks come with the linehaul driver plan; until then routing uses parcels. |
| **Missorts** | Forward parcels: a scan at the wrong hub is refused with a clear message (`Linehaul::notOnTrip`). | There is no missort queue, alert or re-route. |

---

## 2. How the network maps (the decision behind everything)

The spec's network has three levels:
**branch** (first and last mile, riders) → **sorting center** (sorting, linehaul) → **linehaul between sorting centers**.

**Proposed mapping, option B in question 1:**
- **Branches:** every existing logistics company's hub becomes a **branch** (`logistics_hubs.type = branch`). Its riders and barangay coverage stay as they are.
- **Sorting centers:** an admin marks some hubs as **sorting centers** (`type = sorting_center`), for example HUB-SCZ as the Laguna sorting center. Each branch gets one parent (`parent_sorting_center_id`).
- **The legs:**
  - branch → its sorting center is a **hub_transfer** leg, using the existing trip mechanism, normally the branch's own truck;
  - sorting center → sorting center is a **linehaul** leg;
  - branch → seller is **last_mile**.
- **The spec's example:** Luisiana (branch HUB-LUI) and Pagsanjan (branch HUB-PGS) under one Laguna sorting center gives **no linehaul**:
  HUB-LUI → (hub transfer) → Laguna sorting center → (hub transfer) → HUB-PGS → seller.
- **When a branch is its own sorting center** (a single-hub network, like today): the hub-transfer legs simply drop out.

---

## 3. Schema

| Change | Columns |
|---|---|
| **`service_areas`** (new) | `region_code`, `province_code`, `municipality_code`, `barangay_code` (**nullable** = the whole town), `postal_code` (optional), `branch_hub_id`, `sorting_center_hub_id` (filled from the branch's parent), `active`, timestamps. Unique on the codes. **Filled at first** from every company's DeliveryArea rows (barangay level) plus one town-wide row per company. |
| **`unmatched_addresses`** (new) | `kind` (buyer / seller), `address_id` or `seller_id`, the codes, `first_seen_at`, `resolved_at`. Feeds "addresses that need mapping". |
| **`logistics_hubs`** | add `type` (branch / sorting_center), `parent_sorting_center_id` (for a branch). A sorting center can also be a branch for its own town. |
| **`seller_applications`** | add `barangay_code` (and `region_code` where missing); a migration fills them from the PSA list by name. |
| **`return_shipments`** | add `origin_branch_id`, `origin_sc_id`, `dest_branch_id`, `dest_sc_id`, `route_status` (planned / unserviceable_origin / unserviceable_destination / overridden), `dropoff_branch_id` |
| **`return_route_legs`** (new) | `return_shipment_id`, `seq`, `type` (first_mile / hub_transfer / linehaul / last_mile), `from_hub_id`, `to_hub_id` (null for the buyer or seller ends), `status` (planned / in_progress / done / skipped / replaced), `linehaul_trip_id`, `started_at`, `finished_at`, `replaced_by_leg_id` |
| **`linehaul_lanes`** (new) | `from_hub_id`, `to_hub_id` (sorting center pairs, plus branch ↔ sorting center pairs for hub transfers), `departures` (JSON: weekdays and times), `max_wait_hours` (the alert threshold), `active` |
| **`route_exceptions`** (new) | `return_shipment_id`, `type` (unserviceable_origin / unserviceable_destination / missort / address_changed / no_rider / no_trip), `hub_id`, `details`, `status` (open / resolved), `resolved_by`, `resolution` |
| **`linehaul_trips`** | add `lane_id` (nullable) and `kind` (linehaul / hub_transfer) |

Route overrides are logged in `activity_logs` (who, the reason, the old route and the new route).

---

## 4. Logic

### 4.1 Matching an address (`RouteFinder::match`)

- **The order:** an active row for the **barangay** code first, then the **town-wide** row for the municipality code.
- **No match:** the address is **unserviceable**. An `unmatched_addresses` row is saved and the admins are told once per address.
- **Codes only, never names.** Addresses without codes are fixed by the backfill, and new ones are always picked from dropdowns, which they already are for buyers.

### 4.2 Building the route (when the return pickup is booked)

1. **Origin:**
   - **Pickup:** the buyer's address gives the **origin branch**, and the pickup task goes to that branch.
   - **Drop-off:** the buyer is shown the branch for their area. The real origin is whichever branch **scans it in**.
2. **Destination:** the seller's return address gives the **destination branch** and its sorting center.
3. **The legs:**
   - **Same sorting center:** first_mile (buyer → origin branch), hub_transfer (origin branch → sorting center), hub_transfer (sorting center → destination branch), last_mile (destination branch → seller). There is **no linehaul**. A hub transfer is skipped when the branch *is* the sorting center.
   - **Different sorting centers:** first_mile, hub_transfer, **linehaul** (origin sorting center → destination sorting center), hub_transfer, last_mile.
   - **Same branch, with the setting on (off by default):** first_mile, then last_mile from the same branch.
4. **Saving:** the legs are saved in order. The admin and logistics staff see the full route; the buyer and seller see a short one ("Picked up by Pagsanjan Falls Freight → Laguna sorting center → delivered by Luisiana Highland Logistics").

### 4.3 Rider pickup assignment (the origin branch)

- **Who sees it:** the task is visible **only to the origin branch's** dispatcher and riders.
- **Automatic assignment** (a setting; manual by default):
  - prefer a rider who covers the buyer's barangay;
  - otherwise the least busy online rider of the branch.
- **No rider within N minutes** (a setting): the branch is alerted, and a `no_rider` exception is opened.
- **The rider's task shows:** the buyer's name, address and contact, the RMA, the waybill, the items and the pickup window (from the returns plan).

### 4.4 Sorting and linehaul

- **Scanning in at a hub:** the route says where the item goes next:
  - **the destination branch** (same sorting center): a hub_transfer to that branch;
  - **another sorting center:** it waits for the **next scheduled trip on that lane**, and is added to it automatically when it's created, or by the staff.
- **Sacks:** with sacks (linehaul driver plan), this becomes "into the sack for …". Until then it is parcel by parcel, using the existing Handover QR flow.
- **No trip on the lane** within the lane's `max_wait_hours`: the sorting center is alerted, and a `no_trip` exception is opened.
- **Every scan updates its leg and the return status:** picked_up → at_origin_hub → at_sorting_center → in_transit → at_destination_hub → out_for_delivery → delivered_to_seller.
- **Arrival at the destination branch:** the last-mile task to the seller is created for **that branch's** riders.

### 4.5 Exceptions

| Case | What happens |
|---|---|
| **Unserviceable buyer area** | Pickup is not offered. The buyer is offered drop-off at the nearest serviceable branch (by map distance), or the admin routes it by hand. |
| **Unserviceable seller area** | It is held at the origin sorting center, and the admin decides: hold for the seller to collect, or send to the platform warehouse (warehouse = a hub marked `sorting_center` with `is_warehouse`; see the returns plan). |
| **The seller changes their return address** | The legs **not yet started** are worked out again. Started legs stay. Replaced legs are kept as `replaced` for the history. |
| **Scanned at a hub not on its route** | It is **missorted**: an exception, an alert to that hub, and a **re-route** from where it is now (new legs from this hub to the destination). |
| **The admin overrides the route** | A reason is required. It is audit-logged, and the old legs become `replaced`. |

---

## 5. Pages

| Who | Page |
|---|---|
| Admin | **Service areas:** list, search, filter, add, edit, active/inactive, **CSV import** (with a preview and per-row errors), unmatched addresses · **Hubs:** mark sorting centers, set each branch's parent · **Lanes and timetable** · **Route viewer** per return shipment, with the legs, scans and **override** · **Exceptions queue** (missort, unserviceable, no rider, no trip, address changed) |
| Branch (a logistics company's pages) | **Incoming return pickups** for its branch, with rider assignment (auto or manual) · **Outgoing to the sorting center** |
| Sorting center | **Inbound returns** · the next stop for each parcel (destination branch, or lane and next trip) · **Linehaul queue per lane** |
| Buyer / seller | The short route and live status on the return page (the return card that exists) |

## 6. Phases

I stop for your review after each phase, with its tests and the **full suite passing.**

1. **Service areas:** the table, the backfill from the companies' coverage, the admin page, CSV import, hub types and parents.
2. **Structured addresses:** seller barangay codes (with backfill), and code-based matching for buyers and sellers, plus the unmatched list.
3. **Route determination:** the route legs on return_shipments, the three route shapes, and showing the route.
4. **Rider pickup for the origin branch:** visibility, auto or manual assignment, the no-rider alert.
5. **Sorting center and lanes:** the next stop per scan, lanes and timetable, the next trip on a lane, leg and status updates, the last-mile task at the destination branch.
6. **Exceptions:** unserviceable, missort and re-route, address change, manual override.
7. **Route display:** the admin route viewer and exceptions queue; logistics pages; the buyer and seller views.

**Tests** (in the phase that builds each part):
- **Route shapes:** a same-sorting-center route (Luisiana → Pagsanjan) has **no** linehaul leg; a different-sorting-center route has one.
- **Matching:** a barangay row wins over a town row; an unserviceable address is flagged.
- **Origin:** the pickup task goes to the right origin branch, and only its riders see it; a drop-off at another branch moves the origin.
- **Exceptions:** a missorted scan alerts and re-routes; a seller address change re-plans the remaining legs; a manual override is audit-logged.

## 7. Risks

- **Question 1 changes the whole network.** If sorting centers become a level above the town companies, **forward** delivery should follow the same route too, or deliveries and returns will move differently. Applying it to forward delivery is a separate step (question 3).
- **It overlaps three plans that aren't approved yet:**
  - the returns plan (return_shipments, statuses);
  - the linehaul driver plan (sacks, lanes, drivers);
  - the refund plan.
  The order to build them in matters (question 4).
- **Backfilling seller barangay codes by name** may miss some spellings. Those show up in "unmatched addresses" for the admin to fix.
