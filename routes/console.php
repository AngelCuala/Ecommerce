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
