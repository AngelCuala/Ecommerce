# Returns, refunds and reverse logistics: plan (revision 2)

Status: **proposal, waiting for approval.** Nothing in this plan is built yet. Revision 2 follows the updated spec (GCash is the only
online method; the full reverse-logistics flow with hubs, scans, weights, attempts, exceptions and liabilities) and the work done
since revision 1 (admin dispute pages, return pop-up, return progress card).

Stack: Laravel 12, SQLite on your computer and in tests, MySQL on the live site. Every migration must work on both.

---

## 1. What ALVY already has, and what happens to it

### Returns

| Already built | Where | In the new system |
|---|---|---|
| Return request: reason, description, 1–5 photos, chosen items | `return_requests`, [ReturnRequest](../../app/Models/ReturnRequest.php), [_return-modal](../../resources/views/profile/_return-modal.blade.php) | **Kept and extended.** It becomes the return case (with an RMA number). Items move from a JSON list to a `return_items` table with quantities. Videos are added. |
| Seller approves or rejects (note optional) | [Seller/OrderController](../../app/Http/Controllers/Seller/OrderController.php) `decideReturn` | Accept requires a resolution type; reject requires a reason. |
| Seller silent for 3 days → **approved automatically** | `returns:auto-approve` | **Changed:** silence after 48 h **escalates to the admin.** |
| Refund happens **the moment** the return is approved, before the item comes back | [ReturnDecisions](../../app/Services/ReturnDecisions.php) `approve` | **Changed:** the refund follows the case's *refund trigger*: at pickup, at delivery to the seller, or after inspection (the default). |
| Buyer escalates a rejection; the admin refunds or closes it with a reason | `escalateReturn`; [Admin/ReturnDisputeController](../../app/Http/Controllers/Admin/ReturnDisputeController.php) and the Return Disputes pages | **Extended** into a real dispute: extra evidence, a 48 h seller reply, a message thread, a decision with a resolution type and amount. The admin pages are reused. |
| Return progress card and timeline on the buyer's order page | [ReturnProgress](../../app/Support/ReturnProgress.php), [_return-progress](../../resources/views/profile/_return-progress.blade.php) | Reads from the new shared `return_events` timeline instead of working it out from timestamps. |

### Reverse logistics

| Already built | Where | In the new system |
|---|---|---|
| Return pickup: hub assigns a rider → rider accepts → photo + waybill scan at the buyer → hub receives → rider brings it to the seller | `return_shipments`, [ReturnShipments](../../app/Services/ReturnShipments.php) | **Kept and extended.** It is created **when the return is approved**, not when it is requested as today. Added: pickup or drop-off, ship-by deadline, checklist, sealed-parcel photo, weights, attempts, exceptions, linehaul between hubs, OTP or signature at the seller. |
| Hubs: each logistics company has hubs (`HUB-SCZ`…) with an address and map pin | [LogisticsHub](../../app/Models/LogisticsHub.php) | Gets a **type** (`branch` / `sorting_center` / `warehouse`, see question 4). "Destination hub" isn't a type: it is whichever hub is nearest the seller on that trip. |
| Forward-delivery tracking events, failed attempts (max 3, reasons), OTP for COD hand-over, shipment exceptions (damaged, missing…), delayed-parcel flagging, linehaul trips between companies | `shipment_events`, `parcel_deliveries`, `shipment_exceptions`, `linehaul_trips`, `shipments:flag-delayed` | **Reused** for returns instead of being built twice (see section 3). |

### Payments and money

| Already built | Where | In the new system |
|---|---|---|
| **GCash = "GCash receipt":** the buyer sends the money to **ALVY's own GCash**, uploads the receipt, and ALVY matches the 13-digit Ref No. | [AlvyGcash](../../app/Support/AlvyGcash.php), `gcash:check` | There is **no gateway transaction** to refund through an API. See **question 1**: this decides how GCash refunds work. |
| A PayMongo integration (GCash, Maya, card) that is switched off (`PAYMENT_GATEWAY` empty) | [config/payments.php](../../config/payments.php), [PaymentService](../../app/Services/PaymentService.php) `refund()` | Maya and card are removed, since GCash is the only online method. Whether the PayMongo GCash path stays depends on question 1. |
| Admin "Refunds to send" for rejected GCash receipts, with a Ref No. | `GcashPaymentController` | Becomes the general **manual payout** screen. |
| COD refund recorded as an amount only (nobody actually sends it) | `ReturnDecisions::approve` | **Replaced:** the buyer gives a GCash number and name, and ALVY pays it out. |
| Seller earnings worked out from orders; payouts only from Completed orders | `EarningsController::balances()`, `seller_payouts` | Kept, plus a **seller ledger** (freezes, deductions, negative balance blocks withdrawals). |
| Email is set to `log` (written to a file, not sent) | `.env` `MAIL_MAILER=log` | Emails are built in phase 6. They are only really sent once a mail service is set in `.env`. |

---

## 2. Statuses

### The return case (`return_requests.status`)

Allowed moves are listed in code (like `Parcel::TRANSITIONS`), and every move goes through one service, `ReturnFlow`. That service locks the row, checks that the move is allowed, writes a timeline event and sends the notices.

```
requested ─► seller_accepted ──────────────────────────────┐
          ├─► seller_rejected ─► disputed ─► admin_approved ┤
          │                   │            └─► admin_rejected ─► closed
          │                   └─► closed (no dispute in 5 days)
          ├─► escalated (seller silent 48 h) ─► admin_approved / admin_rejected
          └─► cancelled (buyer withdrew before shipping)

accepted / approved, by resolution type:
  refund_with_return ─► awaiting_shipment ─► in_transit ─► delivered_to_seller ─► inspected ─► refund_processing ─► refunded ─► closed
                         └─► expired (not shipped within 5 days)
                         inspection problem ─► disputed (stage: inspection) ─► admin decides
  refund_only        ─► refund_only_approved ─► refund_processing ─► refunded ─► closed
  partial_refund     ─► (with or without a return) ─► refund_processing ─► partial_refunded ─► closed
```

While the item travels, the case stays `in_transit` and the detail comes from the shipment status. This keeps 10 shipment states from being copied onto the case.

The refund can start earlier than inspection when the case's **refund trigger** says so: `at_pickup`, `at_delivery` or `after_inspection` (the default). The trigger is set by rules in settings (amount threshold, trusted buyer, admin-decided dispute) and can be changed by the admin per case.

The **order** keeps its summary statuses (Return Requested / In Dispute / Refunded / Completed), so the order lists, reports and earnings keep working.

### The return shipment (`return_shipments.status`)

```
awaiting_pickup ─► picked_up ─┐
awaiting_dropoff ─► dropped_off ─┴► at_origin_hub ─► (at_sorting_center ─► in_transit ─►) at_destination_hub
                ─► out_for_delivery ─► delivered_to_seller
```

The bracketed part only happens when the buyer's hub and the seller's hub differ. It runs on the existing linehaul trips.

Exceptions: `pickup_failed`, `refused_by_rider`, `damaged_in_transit`, `lost`, `weight_mismatch`, `delivery_failed`, `refused_by_seller`, `returned_to_hub`, `expired`.

**Every status change comes from a scan or a rider action**, never from typing a status.

---

## 3. Schema changes

### Changed tables

**`return_requests`**
- Identity and outcome: `rma_number` (unique, e.g. `RMA-2611-000123`), `resolution_type`, `reason_code` (the dropdown key; the old `reason` text stays for old rows), `rejection_reason`.
- Refund handling: `refund_trigger` (at_pickup / at_delivery / after_inspection), `admin_override` (once set, the seller can't block the refund).
- Routing: `return_required`, `to_warehouse` (the item goes to the platform warehouse instead of the seller, see question 5).
- Deadlines: `seller_respond_by`, `dispute_by`, `ship_by`, `inspect_by`.
- Who decided: `decided_by`, `decided_at`.
- Moved, not deleted: the existing `item_ids`, `evidence_paths`, `dispute_note`, `admin_*` columns are copied into the new tables by the migration, then left alone.

**`return_shipments`**
- Method and identity: `method` (pickup / dropoff), `dropoff_hub_id`, `waybill_number` (its own, linked to the RMA), `current_hub_id`, `destination_hub_id`.
- Weight and checks: `pickup_weight_kg`, `checklist` (JSON: item matches, condition), `sealed_proof_path`.
- Hand-over to the seller: `seller_otp`, `signature_path`, `delivered_proof_path`, `delivered_at`.
- Tracking and exceptions: `attempts`, `last_scan_at` (for stuck and lost detection), `exception`.
- The existing rider and hub columns stay.

**`logistics_hubs`**: add `type` (branch / sorting_center / warehouse) and `accepts_dropoff` (bool).

**`users`**: add `return_flagged_at` (buyer flagged for review) and `returns_risk` (seller level: none / warned / auto_release / payout_hold).

### New tables

| Table | Main columns |
|---|---|
| `return_items` | `return_request_id`, `order_item_id`, `quantity`, `unit_refund`, `buyer_condition`, `inspected_condition` |
| `return_evidence` | `return_request_id`, `uploaded_by`, `role` (buyer / seller / rider / logistics / admin), `stage` (request / dispute / dispute_reply / pickup / hub / delivery / inspection), `type` (photo / video), `path` (private disk), `caption` |
| `return_disputes` | `return_request_id`, `opened_by`, `stage` (rejection / no_response / inspection / seller_claim), `status` (open / awaiting_seller / under_review / decided), `seller_reply_by`, `decision` (resolution type), `decision_amount`, `decision_reason`, `decided_by`, `decided_at` |
| `dispute_messages` | `return_dispute_id`, `user_id`, `role`, `body`, `attachment_path`. Contact details are hidden as in chat (`ContactGuard`). |
| `support_tickets` | `user_id`, `order_id`, `return_request_id` (all optional), `channel` (in_app / email / contact_form), `subject`, `status`. Messages reuse the thread pattern. Contact-form and support-email messages land here too. |
| `return_scan_events` | `return_shipment_id`, `event_type`, `hub_id`, `scanned_by`, `rider_id`, `weight_kg`, `photo_path`, `note`, `created_at`. Append-only, like `shipment_events` (kept separate so forward-parcel reports don't change). |
| `return_rider_assignments` | `return_shipment_id`, `rider_id`, `leg` (pickup / delivery), `status` (assigned / accepted / done / failed / cancelled), `assigned_by` (dispatcher, or null for auto) |
| `return_delivery_attempts` | `return_shipment_id`, `rider_id`, `leg`, `result`, `reason`, `photo_path`, `created_at` |
| `refunds` | see below |
| `logistics_liabilities` | `return_shipment_id`, `sorting_center_id` (the company at fault), `reason` (lost / damaged_in_transit), `amount`, `status` (open / settled / waived), `note` |
| `return_events` | The single shared timeline: `return_request_id`, `actor_id`, `role`, `type` (status / message / scan / evidence / decision / refund / reminder), `from_status`, `to_status`, `note`, `audience` (all / internal / logistics), `created_at`. Riders and logistics see only shipment events. |
| `seller_ledger_entries` | `seller_id`, `return_request_id`, `type` (freeze / unfreeze / refund_deduction / adjustment), `amount` (±), `note` |
| `platform_settings` | Key–value, with defaults in `config/returns.php`. Covers every deadline and threshold in the spec, plus the 24 h reminder lead time. |

**`refunds`** columns:
- Core: `return_request_id` (nullable, so a cancellation or a rejected GCash receipt can use it too), `order_id`, `amount`, `method` (gateway_refund / gcash_payout / manual_payout).
- Gateway IDs: `original_transaction_id`, `gateway_refund_id`, `gateway_payout_id`.
- Payout details: `payout_gcash_number`, `payout_account_name`.
- Status and proof: `status` (pending / processing / succeeded / failed), `reference_number`, `proof_path`, `failure_reason`.
- Safety and audit: `idempotency_key` (**unique**), `requested_by`, `confirmed_at`.

**`platform_settings`** values:
- Deadlines: return window 7 days, seller response 48 h, dispute filing 5 days, seller dispute reply 48 h, ship-by 5 days, inspection 48 h.
- Logistics thresholds: maximum pickup attempts 3, weight tolerance (e.g. 10 % or 0.2 kg), stuck after X hours without a scan, lost after X days without a scan.
- Refund rules: the thresholds that choose the refund trigger.
- Anti-abuse thresholds.
- The warehouse-routing flag.

---

## 4. Reverse logistics, step by step

1. **Approved with a return:** a `return_shipment` is created with its own waybill. The buyer chooses between:
   - **rider pickup** at their address;
   - **drop-off** at a hub marked `accepts_dropoff` (never a sorting center).
   The buyer gets a printable label, reusing the existing waybill layout with QR and barcode. The 5-day ship-by clock starts.
2. **Pickup:**
   - The dispatcher (or automatic assignment by barangay, using the rider coverage that already exists) assigns a rider. The task shows in the rider's job list.
   - At the buyer, the rider records:
     - the checklist: the item matches the order, and its condition;
     - **required** photos of the item and of the sealed parcel;
     - a waybill scan;
     - the weight.
   - The rider can **refuse** with a reason and a photo. That raises `refused_by_rider` and the admin is told.
   - A failed attempt is logged with a reason and a photo. After 3, the pickup is cancelled and the buyer is offered drop-off.
3. **Drop-off intake** (branch staff): scan the waybill, take a photo, record the weight.
4. **Hub scans:**
   - Scan in and scan out at each hub, with the weight. If the weight differs from the pickup weight by more than the tolerance, `weight_mismatch` is raised and goes to the admin.
   - Damage report with photos (`damaged_in_transit`).
   - Moves between hubs use the existing linehaul trips.
   - At the destination hub, staff assign the last-mile rider.
5. **Delivery to the seller:**
   - The rider takes a photo of the sealed parcel and the seller confirms with an **OTP**, sent to the seller like the existing COD OTP, or a signature.
   - The seller can refuse; this is logged with a photo as `refused_by_seller`, and the item goes back to the hub or the warehouse.
   - A failed delivery is retried; after the limit, the item is held at the hub and the seller is told.
6. **Inspection:**
   - `delivered_to_seller` starts the 48 h inspection.
   - The seller either confirms, which releases the refund, or reports a problem with **required** photos or an unboxing video, which opens a dispute (stage: inspection).
   - If the seller is silent, the refund releases automatically. If the admin already overrode the seller, the refund isn't held; the seller can only file a separate claim.
7. **Exceptions:**
   - `lost` (no scan for X days) and `damaged_in_transit`: the buyer is refunded, and a `logistics_liabilities` row is charged to the logistics company. The seller is **not** charged.
   - `weight_mismatch` and `refused_by_rider` go to the admin queue.
8. **Logistics dashboard:** returns per hub, stuck parcels (no scan in X hours) and an exceptions queue. These go on the existing sorting-center pages, next to Return Items.

**Who sees what:**
- Riders see only their own tasks.
- Hub staff can only scan at their own company's hubs.
- Sellers see only their own returns.
- Buyers see only their own returns.

This is enforced in `ReturnFlow` and the controllers, and tested.

---

## 5. Money

- **Refund amount:** the sum of `return_items`. Each line is what the buyer paid for that item after vouchers and promotions; the code for this already exists (`itemRefund`). A partial refund uses an amount typed by the seller or admin, up to that sum. Shipping fee: see question 3.
- **Never twice:**
  - One `refunds` row per return (`idempotency_key = return:{id}:refund`, unique in the database).
  - The row is locked before any call.
  - A row that is already processing or succeeded is never sent again.
  - Gateway calls carry the same key.
- **Completed only when confirmed:** by the gateway webhook (signature checked), or by an admin entering the reference number and proof for a manual payout. A scheduled job asks the gateway about rows stuck in `processing`, because webhooks can't reach your computer while testing.
- **How each order is refunded** depends on question 1.
  - **COD:** the buyer gives a GCash number and the account name. The name must match the name on the order (ignoring case, spacing and middle initials). It is sent by the payout API if one exists, or by an admin who sends it and uploads the 13-digit Ref No. and a screenshot.
  - **If a gateway refund fails:** the buyer is asked for payout details, and it is sent as a payout instead.
  - **The buyer's notice:** amount, method and reference number.
- **Seller balance:**
  - Opening a dispute writes a **freeze** for the disputed amount.
  - An approved refund turns the freeze into a **refund_deduction**; a rejected one writes an **unfreeze**.
  - Payouts are already held until the return window closes: only Completed orders can be withdrawn.
  - If a case is decided after the payout, the deduction can take the balance below zero. Withdrawals are then blocked until it is positive again.
  - Logistics-fault cases are charged to `logistics_liabilities`, never to the seller.

---

## 6. Anti-abuse (phase 7)

- **Sellers:** rejection rate and admin-overturn rate over the last N returns. Crossing each threshold moves the seller up one level:
  1. a warning;
  2. refunds release without waiting for the seller's inspection;
  3. payout hold or store suspension. The existing seller-violation tools are reused for this.
- **Buyers:** refund-only count and dispute-loss rate. A buyer who crosses either threshold is flagged for admin review and shown on the case pages.
- **Evidence:** rider and hub photos, sealed-parcel photos and weights are shown side by side on the admin case page, to check a seller's claim.
- **Admin metrics screen,** with all thresholds editable in settings.

---

## 7. Screens

| Who | Screens |
|---|---|
| Buyer | Return pop-up (exists; add quantities and videos) · return page with timeline (the progress card exists, now fed by `return_events`) · pickup or drop-off choice with label and RMA · File a dispute · GCash payout form (COD only) · message thread · support ticket |
| Seller | Returns inbox (new; today returns sit inside each order) · accept with resolution type and amount, or reject with a reason · inspection: confirm or report a problem · balance showing frozen and deducted amounts · dispute reply |
| Admin | Dispute queue and case page (exist; extended with scans, weights, rider photos, chat history, thread and the decision form with resolution type and amount) · manual payout screen · exceptions and liabilities · abuse metrics · Returns settings |
| Rider | Return pickup and return delivery tasks (exist; add the checklist, sealed-parcel photo, weight, refuse, failed attempt, OTP or signature) |
| Logistics | Drop-off intake · hub scan in and out with weight · damage report · rider assignment (exists) · stuck parcels and exceptions dashboard |

---

## 8. Phases

Each phase ends with its tests and the **full suite passing.** I stop for your review before starting the next.

1. **Schema and statuses.** Migrations, moving existing return rows over. Models, `config/returns.php`, `platform_settings` and the settings page. `ReturnFlow` with allowed moves and the shared timeline. Hub types.
2. **Request and seller decision.** The pop-up with quantities, reasons and videos. The seller inbox; accept with a resolution type and partial amount, or reject with a reason. Escalation when the seller is silent. Replaces the old `requestReturn` and `decideReturn` and `returns:auto-approve`.
3. **Disputes and admin.** File a dispute within 5 days with evidence; the seller's 48 h reply; the thread; support tickets. Extends the existing admin queue and case page with the full evidence view and a decision form whose decision is final and notifies buyer, seller and logistics.
4. **Reverse logistics.** Shipment on approval; pickup or drop-off; label; rider tasks with checklist, photos, weight, refusal, attempts, and OTP or signature; drop-off intake; hub scans with weights; linehaul for returns; exceptions; liabilities; the logistics dashboard. Rebuilds today's `ReturnShipments`.
5. **Inspection, refunds and balance.** Inspection and the refund triggers; the `refunds` table and refund paths (following question 1); webhooks; COD payout form and name check; manual payout screen; seller ledger, freezes, negative balance.
6. **Notifications and jobs.** Email and in-app notice for every status change, dispute event, refund and exception, plus reminders 24 h before each deadline. Jobs: escalate a silent seller, close disputes nobody filed, expire unshipped returns, auto-release after uninspected deliveries, stuck and lost parcels, check pending refunds.
7. **Anti-abuse.** Metrics, levels, buyer flags, warehouse routing, admin screen.

**Tests**, each in the phase that builds its part:
- the decision paths:
  - seller accepts → shipped → inspected → refunded;
  - seller rejects → dispute → admin approves;
  - seller silent → escalated;
  - a false seller rejection after delivery is overridden, and the seller can't block the refund;
- the resolution types: refund-only and partial refund;
- the money:
  - GCash refund, with the gateway faked: refund ID saved, webhook confirms, a second call does nothing;
  - COD payout with the name check, a manual reference, and confirmation;
- the shipment exceptions: lost parcel, damaged in transit, weight mismatch, three failed pickup attempts → drop-off offered;
- **permissions:** every role is refused on cases and shipments that aren't theirs.

## 9. Risks

- **Size:** this rebuilds most of the return code. The existing return tests (about 40 across buyer, seller, logistics and workbook files) will be rewritten to the new flow, phase by phase.
- **Old data:** the real database has 2 returns (orders #000007 and #000008). The migration moves them to the matching new statuses; a backup is taken first.
- **Mobile app:** the API changes with the web. Until the app is updated, its old return buttons get clear error messages (see question 6).
- **Webhooks on your computer:** a gateway can't reach `127.0.0.1`. Tests fake the gateway. Real tests need a tunnel, or rely on the status-check job.
- **Email:** it is only written to the log until a mail service is set up.
