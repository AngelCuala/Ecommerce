<?php
foreach (\App\Models\RiderRemittance::latest('id')->take(5)->get() as $r) { echo "REM {$r->id} rider={$r->rider_id} status={$r->status} amount={$r->amount} expected={$r->expected_amount} kept={$r->pay_kept} counted={$r->counted_amount} at={$r->created_at}\n"; }
$rid = \App\Models\RiderRemittance::latest('id')->value('rider_id');
if ($rid) { $rider = \App\Models\Rider::find($rid); echo "RIDER {$rider->id} {$rider->full_name} wallet_since={$rider->wallet_since}\n";
 foreach (\App\Models\RiderWalletEntry::where('rider_id',$rid)->latest('id')->take(12)->get() as $e) { echo "  E{$e->id} {$e->type} {$e->source_type}#{$e->source_id} status={$e->status} amount={$e->amount} earned={$e->earned_at} created={$e->created_at}\n"; }
 print_r(app(\App\Services\RiderWallet::class)->balances($rider)); }
