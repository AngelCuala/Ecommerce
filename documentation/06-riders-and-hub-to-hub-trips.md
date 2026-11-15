# 6 · Riders and trucks

Riders are the people who move the parcels. They work for a sorting center and sign in to the rider
portal at `/courier`.

## Which rider does what

The **vehicle** written on the rider's record decides their work:

- **Motorcycle, tricycle or bicycle:** pickups from sellers and deliveries to buyers.
- **Car or van:** the same, and they are also the ones who carry **bulky** parcels (over 20 kg, or
  bigger than 1 metre on a side).
- **Truck** (including closed-van and 6-wheeler trucks): **hub to hub only**. A truck carries many
  parcels at once from one sorting center's hub to another's, and never delivers to buyers.

If a company changes a rider's vehicle, their work changes with it. A rider who switches from a
truck to a tricycle stops getting trips and starts getting deliveries.

*Behind the scenes:* the truck trip code was built around a separate "courier" record, so for every
truck rider ALVY keeps a matching courier record in step automatically (same company, plate and
status). The sorting center never sees it; it only manages riders.

## Pickup and delivery riders

### Going online

A rider presses **Online** when they start work. Only riders who are Online and active receive jobs.

### How a rider gets jobs

Each rider is in charge of certain barangays. When the company presses **Auto-assign**, each job
goes to an Online rider who covers that job's barangay: the seller's barangay for a pickup, the
buyer's for a delivery. If more than one rider fits, the one with the fewest open jobs gets it.

The rider gets a notification and presses **Acknowledge** to say they've seen it. Riders don't turn
jobs down; if a rider can't do one, the company reassigns it.

### A pickup, step by step

1. The rider opens the job and presses **On my way**.
2. At the seller, they **take a photo of the parcel** and **scan the waybill's QR code** (or type
   the tracking number). A wrong number is refused, and scanning twice changes nothing.
3. They bring the parcel to the hub, where staff receive it.

### A delivery, step by step

1. The rider presses **Start delivery**. The buyer is told the rider is on the way.
2. At the buyer's door they take a **proof-of-delivery photo**. For cash on delivery, they enter the
   **exact amount collected**; it must match the order. No code from the buyer is needed.
3. If they can't deliver, they press **Failed** and choose the reason. What happens next depends on
   the reason ([page 5](05-sorting-center.md#if-the-delivery-fails)).

### Returns

The same pages handle a buyer's return: the rider collects the item from the buyer (photo and scan),
and later a rider brings it back to the seller.

### Pay and cash

- Riders earn **₱30 per pickup** and **₱50 per successful delivery** (nothing for a failed try).
- The company sends the pay into the rider's **wallet**; the rider can ask to cash it out to GCash.
- **Cash collected** from buyers stays with the rider until they hand it to the company and the
  company confirms it. The rider can see what they still owe and download receipts.

## Trucks: hub-to-hub trips

When parcels need to go to another town, a truck carries them in one trip. The trip is shared by two
companies: the one sending (origin) and the one receiving (destination).

### What the sending company does

1. **Create a trip** on the **Linehaul Trips** page. ALVY picks the **nearest** hub that serves the
   parcels' addresses (staff can change it) and lists the parcels waiting to go there.
2. **Assign a truck rider** who is Online, or press Auto-assign.
3. Both companies can follow the trip and send each other messages on it.

### What the receiving company does

1. **Accepts the trip** before it leaves. The truck can't start loading until it does.
2. When the truck arrives, staff **check the seal number**, **scan each parcel in**, mark any that
   are damaged or missing, and **close receiving**. The parcels then wait in its Parcel Sorting,
   and it carries on as in [page 5](05-sorting-center.md#step-4-sorting).

### What the truck rider does

The truck rider uses the **Hub Jobs** page:

1. **Go Online.** Before each shift the rider ticks a safety checklist (tyres, lights, brakes, fuel,
   cargo door lock), and the browser sends their location. While the page is open it checks in
   every minute; a truck that hasn't checked in for 5 minutes is treated as offline. If the browser
   can't get a location, the hub's location is used.
2. **Accept** the trip (or decline it with a reason; a declined trip pays nothing).
3. **Start loading** once the receiving company has accepted, and **scan every parcel** as it goes
   on the truck.
4. **Seal and depart.** The rider closes the cargo door with a numbered plastic seal and types
   the seal number (`BH-` followed by 7 digits). They must be within 500 metres of the origin hub,
   and every parcel must be scanned.
5. On the road they can **report a delay** with a reason and a new arrival time.
6. **Arrived**, when within 500 metres of the destination hub.

The rider is **paid when the receiving company closes receiving**: ₱1,000 per trip for a truck,
₱1,500 for a 6-wheeler. If a truck breaks down and the trip is given to another rider, the new
rider rescans the parcels and is the one paid.

A trip goes through these steps: Scheduled → Assigned → Accepted → Loading → In Transit →
Arrived → Completed (or Completed with Discrepancy if parcels were missing or damaged, or Cancelled).

## Older freelance couriers

Before riders belonged to companies, freelance couriers picked jobs from a shared list. A few old
orders still use that system and keep working; new orders always go through a sorting center.

## Where to find it in the code

| What | File |
|---|---|
| Rider record, vehicle rules | [Rider](../app/Models/Rider.php) (`HUB_TO_HUB_VEHICLES`, `BULKY_VEHICLES`, `isTruck()`) |
| Keeping the truck record in step | [TruckRiders](../app/Services/TruckRiders.php), [Courier](../app/Models/Courier.php) |
| Rider pages: jobs, pickups, deliveries, pay, wallet, cash | [Courier/RiderController](../app/Http/Controllers/Courier/RiderController.php), views in `resources/views/courier/rider/` |
| Who gets which job | [RiderDispatch](../app/Services/RiderDispatch.php) |
| Rider notifications | [RiderNotifier](../app/Services/RiderNotifier.php) |
| Pickups and deliveries | [ParcelPickup](../app/Models/ParcelPickup.php), [ParcelDelivery](../app/Models/ParcelDelivery.php) |
| Pay, wallet and cash | [RiderWallet](../app/Services/RiderWallet.php), [RiderWalletEntry](../app/Models/RiderWalletEntry.php), [RiderRemittance](../app/Models/RiderRemittance.php) |
| Returns | [ReturnShipments](../app/Services/ReturnShipments.php) |
| Truck rider's Hub Jobs | [Courier/TruckDutyController](../app/Http/Controllers/Courier/TruckDutyController.php), views `courier/trips`, `trip-history`, `trip-pay` |
| Sorting center's trip pages | [SortingCenter/TripsController](../app/Http/Controllers/SortingCenter/TripsController.php), views `sc/trips`, `sc/trip-show` |
| All the trip rules (500 m, seal, pay) | [Linehaul](../app/Services/Linehaul.php) |
| Trip records | [LinehaulTrip](../app/Models/LinehaulTrip.php), [LinehaulTripParcel](../app/Models/LinehaulTripParcel.php), [LinehaulTripEvent](../app/Models/LinehaulTripEvent.php), [LinehaulTripMessage](../app/Models/LinehaulTripMessage.php) |
| Safety checklist | [CourierChecklist](../app/Models/CourierChecklist.php) |
| The app's truck screens | [Api/V1/Linehaul/CourierTripsController](../app/Http/Controllers/Api/V1/Linehaul/CourierTripsController.php) |
| Older freelance couriers | [Courier/DeliveryController](../app/Http/Controllers/Courier/DeliveryController.php), [DeliveryService](../app/Services/DeliveryService.php), [Delivery](../app/Models/Delivery.php) |
| Rider account page | [Courier/AccountController](../app/Http/Controllers/Courier/AccountController.php) |
