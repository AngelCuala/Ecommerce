# Refunds and seller remittance: plan (revision 3)

Status: **proposal, waiting for approval.** Nothing in this plan is built yet.

Revision 3 follows the latest refund spec:
- **the refund is sent the moment the returned item is handed back to the seller,** with no seller inspection hold;
- the buyer's bank details are collected **as soon as the refund is approved**, so they're ready by then;
- admins send by InstaPay or PESONet, through a payout API or by hand with a second admin approving;
- notices to buyers are web only;
- sellers are protected by stronger rider proof and an after-refund claim.

It replaces revisions 1 and 2. The ledger idea stays.

Stack: Laravel 12, SQLite on your computer and in tests, MySQL on the live site. Every migration must work on both.

---

## 1. What ALVY has today (reviewed Nov 17)

| Area | Today | Change |
|---|---|---|
| **Payments** | GCash = buyer pays **ALVY's own GCash** and uploads the receipt ([AlvyGcash](../../app/Support/AlvyGcash.php)); COD = the rider collects, the hub remits ([HubRemittances](../../app/Services/HubRemittances.php)). PayMongo coded but off. | Unchanged. Every refund goes out by bank transfer from ALVY (question 1). |
| **Approval** | [ReturnDecisions](../../app/Services/ReturnDecisions.php) `approve()` marks the order **Refunded immediately** (COD: only an amount is written; GCash receipt: "Refunds to send"). | Approval **creates a refund** (`approved_awaiting_details`). The order becomes Refunded only when the money is confirmed sent. |
| **Return logistics** | [ReturnShipments](../../app/Services/ReturnShipments.php): pickup (**1 photo + waybill scan**) → at each hub sort → check → scan → trip to the seller's town → the rider hands it to the seller (`returned`; **photo optional**). Recorded in `return_scan_events`. | `returned` with a **required** proof photo becomes the refund trigger. Pickup proof gets stronger: item + sealed-parcel photos, checklist, **weight**. Weights are also recorded at the hub check and at hand-over, which drives the weight-mismatch hold. |
| **Seller money** | [SellerWallet](../../app/Services/SellerWallet.php) (Lawrence): lines rebuilt from orders, released when the order is **Completed** (the buyer taps Order Received, or after 7 days), clamped at **₱0**. Withdrawals sent by hand by an admin. | A ledger: frozen until the window ends with no return, deducted at the refund trigger, negative balance allowed (withdrawals blocked). Lawrence's pages keep their numbers. |
| **Admins** | One `admin` role, no permission levels, one demo admin. Since Nov 16, nobody can message the admin (announcements only). | Admin permissions `finance` and `approve_refunds`, plus a second admin for the two-person rule. |
| **Buyer pages** | Order page return card + timeline ([ReturnProgress](../../app/Support/ReturnProgress.php)). No My Returns page, no site-wide banner. | Banner, My Returns, refund card and timeline. |
| **Webhooks / ledger** | None. | Built. |

---

## 2. Refund statuses

```
On approval (seller accepts, or admin decides a dispute):
  return required:  approved_awaiting_details ──(buyer submits)──► details_submitted_awaiting_delivery
                    (name mismatch on submit) ──► needs_review ──(admin clears)──► back on the line above
  refund only:      ready_to_send  or  ready_to_send_awaiting_details

Trigger = return_shipment "returned" (handed to the seller) WITH the rider's proof photo:
  details_submitted_awaiting_delivery ─► ready_to_send ─► send now
  approved_awaiting_details           ─► ready_to_send_awaiting_details ─(submits)─► ready_to_send ─► send now
  needs_review                        ─► stays; shown URGENT to admins until cleared, then ready_to_send
  weight mismatch beyond tolerance    ─► on_hold_review (the only automatic hold); admin releases or decides

Sending:
  ready_to_send ─► processing (payout API) ─(verified webhook)─► refunded
                                            └(bounce / invalid)─► refund_failed
  ready_to_send ─► manual record ─► pending_second_approval ─(second admin)─► refunded
  refund_failed ─► buyer re-submits ─► ready_to_send ─► send now   (after N attempts: escalated)
  any open status ─► cancelled (admin, with reason) → the seller deduction is reversed
```

**Allowed moves:** they're listed in code, and every change goes through `RefundFlow`, which:
- locks the row;
- checks the move is allowed;
- writes the timeline and the audit log;
- sends the web notices.

---

## 3. Schema

| Table | What it holds |
|---|---|
| **`refunds`** | `order_id`, `return_request_id` (RMA), `buyer_id`, `seller_id`, `resolution_type` (refund_with_return / refund_only / partial), `approved_by_role` (seller / admin / auto); amounts in **centavos** with the breakdown (items, shipping by fault, already refunded, fee deducted); `fault` (seller / logistics / buyer); `status`; `released_at` (the trigger); `details_due_at`; `attempts`; `name_mismatch` + reason; `hold_reason` (weight); `needs_approval` / `approved_by`; `funded_from` (holding / platform_funds_with_logistics_receivable); `idempotency_key` (**unique**); `refunded_at`; `cancel_reason` |
| **`refund_bank_details`** | One per submission (re-submissions kept): `bank_code`, `account_name`, `account_number` (**encrypted**), `last4`, `mobile`, `screenshot_path` (private), `submitted_at`, `submitted_ip`, `locked` |
| **`refund_attempts`** | `method` (gateway / manual), `rail` (instapay / pesonet), `amount`, `fee`, `gateway_payout_id`, `reference_number`, `sent_at`, `source_account`, `receipt_path`, `notes`, `status`, `failure_reason`, `created_by`, `approved_by` (≠ `created_by`), `idempotency_key` (**unique**) |
| **`refund_timeline_events`** | The buyer timeline and bell: approved / details_submitted / picked_up / delivered_to_seller / sent / failed / refunded / held / cancelled, with reference, rail and expected arrival |
| **`payout_banks`** | The configurable bank and e-wallet list with `rails`, number length and pattern, `active` (seeded: BDO, BPI, Metrobank, Landbank, UnionBank, RCBC, PNB, Security Bank, GCash, Maya) |
| **`seller_claims`** | `refund_id`, `seller_id`, `filed_at` (within 48 h of delivery), `statement`, `evidence` (photos / video, private), `status` (open / ruled_for_seller / ruled_for_buyer), `decision`, `compensation_centavos`, `funded_by` (platform / buyer_recovery), `decided_by` |
| **`buyer_flags`** | `buyer_id`, `reason`, `seller_claim_id`, `requires_inspection` (true: their future refunds wait for the seller's inspection), `active`, `set_by` |
| **`ledger_transactions` + `ledger_entries`** | Immutable, double-entry, centavos, a **unique idempotency key** per operation. Accounts: platform_holding, seller_frozen, seller_available, platform_commission, refunds_in_progress, buyer_refunds_paid, logistics_liability, logistics_receivable, payouts_in_progress, transfer_fees, claims_paid. Balances come from these, cached and reconciled nightly. |
| **`webhook_events`** | `provider`, `event_id` (**unique**), `signature_ok`, `processed_at` |
| **`return_shipments`** (extend) | `pickup_item_photo`, `pickup_sealed_photo`, `pickup_checklist` (JSON), `pickup_weight_kg`, `hub_weight_kg` (at the hub check), `delivered_weight_kg` (optional), `returned_proof_path` **required** |
| **`platform_settings`** | Return window (7 d), details reminder cadence, max attempts (3), InstaPay limit (₱50,000), approval threshold, fee policy and amounts, weight tolerance (e.g. 10% or 0.2 kg), seller claim window (48 h), the flagged-buyer rule |
| **`users`** | `admin_permissions` (JSON: finance, approve_refunds) |
| **`activity_logs`** | add `ip`, `amount_centavos`, `reference` |

---

## 4. How it works

### 4.1 On approval

- **The refund is created** with its calculated amount: the returned items' paid prices after vouchers, plus shipping if the seller or logistics was at fault, minus earlier refunds, and never more than was paid.
- **The seller's frozen amount for the order is kept frozen** (the return window doesn't release it).
- **Web notices:**
  - a bell entry;
  - the **site-wide banner**: "Action needed: submit your refund details for order #000123". It gets more urgent as days pass, and the refund is never cancelled;
  - the order page: "Refund approved – details needed" with **Fill in refund details**;
  - **My Returns**: the entry is marked *Action needed*.
- **Refund only** (no return needed): the refund goes straight to ready_to_send, or ready_to_send_awaiting_details.

### 4.2 The buyer's details form

- **Shown, not editable:** the order, the RMA, the amount (and the fee, if one is deducted) and the buyer's name.
- **Filled in by the buyer:** the bank or e-wallet, the account name, the account number typed twice, the mobile, an optional screenshot, and the confirmation box.
- **Checks:** the account number's length and format for the chosen bank; the two numbers match.
- **The name check:** case, spacing, punctuation, middle names and word order are ignored. A mismatch shows a warning, requires a reason, and sets **needs_review**.
- **Password:** the buyer re-enters their password (5 tries, then a 15-minute wait).
- **After submitting:** the details are **locked**. The account number is **encrypted** and shown as ••••4567, except to finance admins; their **Reveal** is audit-logged.

### 4.3 The trigger: the item is handed back to the seller

- **When:** the rider marks the return handed over, and the **proof photo is now required**. `ReturnShipments::handedToSeller` calls `RefundFlow::release()`. It doesn't wait for the seller to inspect or confirm.
- **What the release does:**
  1. The seller's deduction is applied to the frozen amount, as **pending**. The commission is reversed in proportion.
  2. Then, by refund status:
     - **details submitted:** ready_to_send, and **sent now**;
     - **no details yet:** ready_to_send_awaiting_details. The banner becomes "Your item has been returned. Submit your refund details to receive ₱X now.", and it is sent on submission;
     - **needs_review:** it stays held, and goes to the top of the admin queue as URGENT;
     - **weight beyond the tolerance:** the weight at pickup is compared with the hub check and the hand-over. A difference beyond the tolerance sets **on_hold_review** for an admin to decide.
- **"Send now":**
  - **Payout API** (when set up): InstaPay by default (refused above the limit), PESONet above it. The call carries the idempotency key, and the refund becomes `processing`. It becomes **refunded only on a verified webhook** (signature checked, a duplicate event ignored), with the reference saved. A 10-minute job asks the gateway about anything stuck.
  - **Manual mode, or no gateway:** it goes to the **top of the admin queue, marked URGENT**, and the admins get a notification.
- **Guards:**
  - an amount above the approval threshold needs an admin with `approve_refunds` first (it's still marked urgent);
  - COD not yet remitted by the hub needs an admin to approve paying from platform funds, which records a **receivable** from that hub.

### 4.4 The admin refund queue

- **Tabs:** Urgent / ready to send · Awaiting details · Needs review · On hold (weight) · Processing · Failed · Refunded · Waiting on buyer (overdue) · Escalated.
- **The disbursement balance:** typed in by an admin, or read from the gateway. A warning shows when it is below the total of ready-to-send refunds.
- **Detail page:**
  - the order, RMA, buyer and seller;
  - the original payment and its reference;
  - the breakdown;
  - who approved it (seller or admin);
  - the **return shipment timeline**, with the rider's pickup and delivery photos and the weights (from the return record);
  - the bank details, masked;
  - the name-check result.
- **Actions:**
  - **Send refund:** choose the rail.
  - **Record manual refund:** amount, reference (required), date and time, source account, receipt (required), notes. A **second, different admin approves** it before it becomes refunded.
  - **Clear review / release hold**, **cancel** (with reason), **unlock details**.
- **Guards:** buttons are hidden **and** refused on the server once processing or refunded (row lock plus unique keys); the per-order cap; the fee policy; and every action is audit-logged.

### 4.5 Failed refunds

- `refund_failed`: the buyer's form unlocks, and the banner reads "We couldn't send your refund. Please check and re-submit your bank details."
- A re-submission makes it ready_to_send and it's **sent now**.
- After 3 attempts it is **escalated** to the admin support queue.

### 4.6 Seller money

| When | What happens |
|---|---|
| **Delivered** | The seller's earning (item minus commission) is **frozen**. It is **released** to available only after the 7-day window with **no return**. A return keeps it frozen. |
| **At the trigger** (or refund-only approval) | The deduction is applied, as pending. A full refund leaves the seller ₱0 for the order; a partial one leaves the rest minus its commission. The commission is reversed in proportion. |
| **refunded** | The deduction is final. |
| **cancelled for good** | The deduction is reversed. |
| **Already released** | The buyer is still refunded at once from platform funds. The amount is taken from the seller's available balance, which **can go negative**; then withdrawals are blocked and future releases settle it first. |
| **Logistics' fault** (lost or damaged in transit) | Charged to `logistics_liability`, not to the seller. |
| **Statement** | Each deduction is shown with the order, the RMA and the breakdown (item, shipping, commission reversal). |

### 4.7 Seller protection (no inspection hold)

- **Rider pickup proof, required:**
  - a photo of the item;
  - a photo of the sealed parcel;
  - a checklist (item matches the order; its condition);
  - the weight.
- **The hand-over to the seller:** a **required** photo showing the parcel sealed.
- **Seller claim:** within **48 h** of delivery the seller can file a claim with photos or an unboxing video. An admin reviews it.
  - **Ruled for the seller:** compensation from platform funds, or recovered from the buyer, as the admin decides. The buyer is **flagged**.
  - **A flagged buyer's** future refunds wait for the seller's inspection (a setting).
- **Admin metrics:** claim losses per buyer, and claim rates per seller.

### 4.8 The buyer timeline (order page and My Returns)

The steps:
1. Refund approved
2. Details submitted
3. Item picked up
4. Item delivered to the seller
5. Refund sent: the reference, the rail, and the expected arrival ("within minutes" for InstaPay, "next banking day" for PESONet). Or **Refund failed**, with **Re-submit details**.

Each step is also a bell notification. The existing [ReturnProgress](../../app/Support/ReturnProgress.php) card is extended rather than replaced.

---

## 5. Phases

I stop for your review after each phase, with its tests and the **full suite passing.**

1. **Schema, statuses, ledger,** settings, admin permissions, opening balances from today's wallet.
2. **Refund creation on approval** + remittance freeze. Replaces the immediate refund in `ReturnDecisions`. The order is Refunded only when the refund is.
3. **Web notices + buyer details form:** banner, bell, order page, My Returns, validation, name check, password, encryption, lock.
4. **The trigger and sending:** required delivery photo, release, ready_to_send, payout API + webhooks (fake gateway until there is an account), the urgent manual queue, the weight hold.
5. **Admin refund queue:** the detail page with photos and weights, reveal, approval threshold, manual refund with second-admin approval, disbursement balance, COD receivable.
6. **Failed refunds** and the buyer timeline.
7. **Seller remittance adjustments:** pending, final, reverse; after release (negative balance, blocked withdrawals); logistics fault.
8. **Seller protection:** stronger rider pickup proof (photos, checklist, weight), hub weights, the weight-mismatch hold, seller claims, buyer flags, metrics.

**Tests:** all 16 cases in your spec, in the phase that builds each part, plus:
- the opening balances keep every seller's numbers;
- non-finance admins see only masked numbers;
- the same admin can't record and approve a manual refund;
- the refund trigger can't fire without the delivery photo.

## 6. Risks

- **No payout gateway yet:** the instant sending of section 4.3 is instant only through an API. In manual mode "immediately" means "URGENT at the top of the queue the moment the seller has it". An admin still has to transfer it.
- **It changes Lawrence's wallet** and the approve-equals-refunded behaviour from Nov 16. Agree it with him first.
- **Riders' work grows:** pickup needs two photos, a checklist and a weight, and the riders' app screens change with it.
- **The other plans still waiting:** returns, return routing, linehaul driver. This plan builds the refund side they hand over to.

## 7. Questions (my recommendation first)

1. **Payout gateway:** which provider (PayMongo payouts, Xendit, or a bank API)? *Recommended:* build it against a fake gateway now and run in manual URGENT mode until an account exists.
2. **Lawrence's wallet:** may the release move from "Completed" to "7 days after delivery, no return", with negative balances allowed? *Recommended:* yes, but agree it with Lawrence first.
3. **Admins:** may I seed a second demo admin (admin2@alvy.test) with `finance` + `approve_refunds`, so the two-admin rule can be shown?
4. **Transfer fees:** who pays the InstaPay/PESONet fee? *Recommended:* ALVY absorbs it; the setting allows deducting it later.
5. **Shipping refund:** refunded only when the seller or logistics is at fault? *Recommended:* yes; buyer's change of mind = items only.
6. **Seller claims:** when the admin rules for the seller, who pays the compensation? *Recommended:* the platform pays first, then tries to recover it from the buyer.
7. **Weight tolerance:** *Recommended:* 10% or 0.2 kg, whichever is larger.
8. **Build order:** this plan first, or after the returns / return-routing / linehaul driver plans? *Recommended:* this plan first, since returns already reach the seller.
