<?php

use Illuminate\Support\Facades\Schedule;

// Delivered orders complete by themselves once the return window has passed.
Schedule::command('orders:complete-delivered')->hourly();

// Unpaid online orders are cancelled after their time to pay; flash sale reminders go out.
Schedule::command('orders:cancel-unpaid')->everyMinute();
Schedule::command('flash-sales:remind')->everyMinute();

// Rider job offers expire after 60 seconds and go to the next rider.
Schedule::command('riders:expire-offers')->everyMinute();

// Return requests the seller hasn't answered in 3 days are approved (A31).
Schedule::command('returns:auto-approve')->hourly();

// App logins older than a week no longer work (config sanctum.expiration); clear them from the database.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// New or changed product pictures: the AI reads their colors so searches like "red" or "pula" find them.
Schedule::command('products:tag-colors --limit=20')->everyTenMinutes()->withoutOverlapping();
// Small WebP copies of new product pictures for the product cards (faster pages on phones).
Schedule::command('products:thumbs')->everyTenMinutes()->withoutOverlapping();

// Hub-to-hub transfers not received in time are flagged for investigation (also checked when boards open).
Schedule::command('shipments:flag-delayed')->everyFifteenMinutes();

// GCash payments to ALVY: the AI reads new receipts for the admin; payments unchecked for 24 hours get a reminder.
Schedule::command('gcash:check')->everyMinute()->withoutOverlapping();

// Barangays placed on the map once (for the nearest rider when a barangay has none).
Schedule::command('areas:locate --limit=20')->hourly()->withoutOverlapping();
