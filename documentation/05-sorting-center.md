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
  buyer's town. It then travels there on a **truck trip** ([page 6](06-riders-and-hub-to-hub-trips.md#trucks-hub-to-hub-trips)),
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

When a seller approves a buyer's return, the company that delivered the order collects it, like a
pickup in reverse:

1. Staff assign a rider (in **Return Items**).
2. The rider collects the item from the buyer, with a photo and a waybill scan.
3. The hub receives it.
4. A rider brings it back to the seller and hands it over.

The seller is told at every step. If the buyer withdraws the return before pickup, it's cancelled.

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
  company, and the company confirms it here.
- **Rider Pay:** riders earn ₱30 for each pickup and ₱50 for each delivery (nothing for a failed
  try). Trucks are paid per trip. The company presses *Send pay* to put it in the rider's wallet,
  and processes the riders' requests to cash out to GCash.
- **Commission & Earnings:** the company earns the shipping fee of the parcels it handles. If two
  hubs handled a parcel (one picked it up, another delivered it), they split it. Rider pay comes off
  that. A cancelled or refunded order earns nothing.
- **Zones & Rates:** the company can change shipping rates ([page 8](08-payments-vouchers-shipping.md#shipping-fees)).

## Other pages

- **Dashboard:** how many parcels are at each step for a date range; each number opens the list.
- **Coverage Areas:** choose which barangays of the town the company delivers to.
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
| Return Items | [ReturnsController](../app/Http/Controllers/SortingCenter/ReturnsController.php), [ReturnShipments](../app/Services/ReturnShipments.php) |
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
