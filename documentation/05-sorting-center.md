# 5 · The sorting center

## What a sorting center is

A sorting center is a logistics company's account. Each one has:

- an **assigned town** (set by the admin), for example Santa Cruz;
- one or more **hubs**, the buildings where parcels are received and sorted (e.g. `HUB-SCZ`);
- **coverage areas**: the barangays of its town that it delivers to;
- **riders**, each in charge of one or more of those barangays.

Staff use the sorting center portal at `/sc`. Every page there shows only that company's own
parcels, riders and money.

## How a parcel travels through the hub

Here is the journey of one parcel, step by step, and the page where each step happens.

### Step 1: the seller asks for a pickup

When a seller presses *Ready for Pickup* and chooses this company, a **parcel** is created and
appears in **Pickup Requests**. The company accepts it (or rejects it with a reason). If the buyer
cancelled in the meantime, the request closes by itself.

### Step 2: a rider collects it from the seller

The pickup job goes to **Deliveries** (the dispatch board). Pressing **Auto-assign** gives it to an
available rider who covers the **seller's** barangay. If several can, the least busy one gets it.
If nobody covers that barangay, staff choose a rider by hand.

At the seller, the rider takes a photo of the parcel and scans its waybill, then brings it in
([page 6](06-riders-and-hub-to-hub-trips.md#a-pickup-step-by-step)). The buyer's order now shows **Shipped**.

Heavy or large parcels (over 20 kg, or over 1 metre on a side) only go to car or van riders.

### Step 3: receiving at the hub

At the hub, staff open the **Shipment Board** and scan the parcel's QR code with the camera (or type
the number). Scanning only *finds* the parcel. Staff then press **Receive** to confirm they really
have it. This two-step design stops a careless scan from marking the wrong parcel as received.
Scanning the same parcel twice records nothing new, and another company's parcel is never shown.

### Step 4: sorting

In **Parcel Sorting**, ALVY reads the buyer's address and suggests where the parcel goes:

- **The buyer lives in a barangay this company covers:** the parcel is sorted to that barangay
  and waits for a delivery rider.
- **The buyer lives somewhere else:** the parcel is sorted to the sorting center that serves the
  buyer's town. It then travels there on a **truck trip** ([page 6](06-riders-and-hub-to-hub-trips.md#linehaul-drivers-hub-to-hub-trips)),
  and that company carries on from step 3.

### Step 5: delivery

Back on **Deliveries**, the delivery job goes to a rider who covers the **buyer's** barangay
(Auto-assign, or by hand). If no rider covers it, any of the company's riders may take it, and the
page lists the nearest ones first. The rider delivers it with a photo and collects any cash
([page 6](06-riders-and-hub-to-hub-trips.md#a-delivery-step-by-step)).

### If the delivery fails

The rider records why, and the parcel shows up in **Failed Deliveries**:

- **Buyer not home:** it is rescheduled for another try.
- **Wrong address:** it goes back to the seller (**Return to Seller**).
- **Buyer refused it, or this was the 3rd failed try:** the order is cancelled and the parcel stays
  at the hub for the seller to collect.

### If something goes wrong

A parcel can be reported **lost** or **damaged** (with a photo). That opens a problem record that
someone must resolve, with a reason. Nothing in a parcel's history is ever erased: if a step was
recorded by mistake, a *correction* is added that points to the wrong one.

### The rules that keep this safe

- A parcel can only move to certain next steps. For example, it can't jump from "at the hub"
  straight to "delivered". Any other move is refused with an explanation.
- Every step is written to the parcel's history, which is what the buyer sees as tracking.
- When two staff members press a button on the same parcel at the same moment, only one succeeds.

## Returns from buyers

As soon as a buyer asks for a return, the company that delivered the order gets a pickup job for it
in **Return Items**, like a pickup in reverse:

1. Staff assign a rider. The rider accepts, then collects the item from the buyer with a photo and a
   waybill scan.
2. At the hub, the item goes through **four steps, in order**, on its own page (`/sc/returns/{id}`):
   1. **Sort:** where it goes next. If the seller's town is served by this company, the next stop is
      *the seller, from this hub*; otherwise it's the hub of the seller's town (e.g. HUB-QC).
   2. **Check:** its condition (good, damaged, opened, wrong item), with a note. A photo is required
      unless it's good.
   3. **Scan** its waybill for the record.
   4. **Send:** to a rider who brings it to the seller, or onto a **hub-to-hub trip** to the next hub
      (a trip is made if none is waiting). The next hub receives it from the trip and does the same
      four steps.
3. The rider hands it to the seller. This lets the buyer's refund go
   ([page 3](03-buyer-and-storefront.md#returns-and-refunds)).

Every step is recorded with who, where and when (`return_scan_events`), and the buyer sees the trip
on their return card. If the trip is cancelled, the item goes back to the hub's *Send* step. If the
seller rejects the return before the rider collects it, the pickup is cancelled. The delivered
parcel itself keeps the status *delivered*.

## Managing riders

On the **Riders** page the company:

- **adds riders:** name, birthday, contact details, licence, vehicle, plate, documents, and a
  barangay. The rider receives an email to set a password;
- gives a rider more barangays, or removes one (a rider always keeps at least one);
- approves or rejects people who applied to ride for this company;
- **suspends** a rider with a reason, and reactivates them;
- checks their documents.

The **vehicle** decides a rider's work. Motorcycles, tricycles, cars and vans do pickups and
deliveries. **Trucks** are marked "hub to hub" and only do truck trips between sorting centers
([page 6](06-riders-and-hub-to-hub-trips.md#which-rider-does-what)).

## Money

- **COD Remittances:** cash riders collect from buyers stays with them until they hand it to the
  company. Staff count it, then **accept** the amount the rider sent (they can't change it) or
  **reject** it with a reason (the cash doesn't match or wasn't handed over: the COD stays on the
  rider, who remits again). Older remittances counted short before this rule can still have the gap
  **taken from the rider's earnings**.
- **Rider Pay:** since 16 Nov 2026 a rider earns **30% of the parcel's shipping fee** for the pickup
  and **30%** for the delivery (nothing for a failed try; older jobs keep ₱30 / ₱50). The pay goes
  into the rider's wallet, and the company processes the riders' cash-outs to GCash, Maya or a bank
  (completed with a reference, or rejected).
- **Commission & Earnings:** the other **40%** of the shipping fee is split evenly between every hub
  the parcel passed (both ends of each trip, and the hub that delivered it), minus ALVY's commission
  on it. A cancelled or refunded order earns nothing.
- **ALVY Remittance:** the company sends ALVY the item price of the COD orders its riders delivered,
  with a report of each delivery. ALVY owes the company the shipping fee of prepaid orders, so that
  is taken off. The admin checks the report and confirms it or flags a mismatch
  ([page 7](07-admin-panel.md#money-refunds-payouts-and-remittances)).
- **Zones & Rates:** the company can change shipping rates ([page 8](08-payments-vouchers-shipping.md#shipping-fees)).

## Other pages

- **Dashboard:** how many parcels are at each step for a date range; each number opens the list.
- **Coverage Areas:** choose which barangays of the town the company delivers to.
- **Scan driver QR (Handover):** staff scan the linehaul driver's Handover QR (or type its 6-digit
  code) to load a truck or receive one, scanning the parcels on or off with live counts
  ([page 6](06-riders-and-hub-to-hub-trips.md#the-handover-qr)).
- **Reports** and the **Activity** log, and **Chat** with ALVY's admins.

## The admin's view

ALVY admins can see every company's parcels at `/admin/logistics`. They can resolve problems and add
corrections, but they **can't** move an order or parcel to another step themselves: that always
happens through the seller, the company and the rider.

## Where to find it in the code

Controllers are in [app/Http/Controllers/SortingCenter/](../app/Http/Controllers/SortingCenter), pages
in [resources/views/sc/](../resources/views/sc).

| What | File |
|---|---|
| Dashboard, coverage areas, riders, pickup requests, receiving, sorting, deliveries, reports | [SortingCenterController](../app/Http/Controllers/SortingCenter/SortingCenterController.php) |
| Shipment Board | [ShipmentController](../app/Http/Controllers/SortingCenter/ShipmentController.php), scanner [partials/waybill-scanner](../resources/views/partials/waybill-scanner.blade.php), `public/js/waybill-scanner.js` |
| Dispatch board, Auto-assign, COD, rider pay | [RiderWorkController](../app/Http/Controllers/SortingCenter/RiderWorkController.php), [RiderDispatch](../app/Services/RiderDispatch.php) |
| Nearest rider | [NearestRiders](../app/Services/NearestRiders.php) |
| Failed Deliveries | [IssuesController](../app/Http/Controllers/SortingCenter/IssuesController.php) |
| Return Items and the four hub steps | [ReturnsController](../app/Http/Controllers/SortingCenter/ReturnsController.php), [ReturnShipments](../app/Services/ReturnShipments.php), [ReturnScanEvent](../app/Models/ReturnScanEvent.php), view `sc/return-process` |
| Scan driver QR (Handover) | [HandoverController](../app/Http/Controllers/SortingCenter/HandoverController.php), [TripHandover](../app/Services/TripHandover.php) |
| ALVY Remittance | [AlvyRemittanceController](../app/Http/Controllers/SortingCenter/AlvyRemittanceController.php), [HubRemittances](../app/Services/HubRemittances.php), [HubRemittance](../app/Models/HubRemittance.php) |
| Bulk pickup stops | [PickupStops](../app/Services/PickupStops.php), [PickupStop](../app/Models/PickupStop.php) |
| Return to Seller, rates, shipment lists | [LogisticsController](../app/Http/Controllers/SortingCenter/LogisticsController.php) |
| Truck trips | [TripsController](../app/Http/Controllers/SortingCenter/TripsController.php), [Linehaul](../app/Services/Linehaul.php) |
| Rider applications | [CourierApplicationController](../app/Http/Controllers/SortingCenter/CourierApplicationController.php) |
| Commission & Earnings | [CommissionController](../app/Http/Controllers/SortingCenter/CommissionController.php), [SortingCenterCommission](../app/Services/SortingCenterCommission.php) |
| Rider pay and wallets | [RiderWallet](../app/Services/RiderWallet.php) |
| Creating parcels, suggesting the barangay | [ParcelService](../app/Services/ParcelService.php) |
| Receiving, sorting, problems (with locking) | [ShipmentWorkflow](../app/Services/ShipmentWorkflow.php) |
| Allowed steps of a parcel | `Parcel::TRANSITIONS` in [Parcel](../app/Models/Parcel.php) ([page 11](11-database-models.md#the-steps-of-a-parcel)) |
| Tracking history | [ShipmentTracker](../app/Services/ShipmentTracker.php), [ShipmentEvent](../app/Models/ShipmentEvent.php) |
| Admins can't change steps | [AdminCannotChangeShipmentStatus](../app/Http/Middleware/AdminCannotChangeShipmentStatus.php) |
| Admin's logistics pages | [Admin/Logistics/](../app/Http/Controllers/Admin/Logistics), views in `resources/views/logistics/` |
