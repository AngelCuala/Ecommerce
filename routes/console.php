<?php

use Illuminate\Support\Facades\Schedule;

// Delivered orders complete by themselves once the return window has passed.
Schedule::command('orders:complete-delivered')->hourly();

// Unpaid online orders are cancelled after their time to pay; flash sale reminders go out.
Schedule::command('orders:cancel-unpaid')->everyMinute();
Schedule::command('flash-sales:remind')->everyMinute();
