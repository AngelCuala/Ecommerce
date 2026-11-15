# ALVY

A marketplace for Laguna: buyers shop from local sellers, and logistics companies (sorting centers)
pick parcels up, move them between hubs and deliver them with their riders. One Laravel 12 app
serves the website and the mobile app's API.

```bash
composer run setup
php artisan db:seed --class=DemoAccountsAndProductsSeeder
php artisan serve
```

Full documentation, covering how each part works and where its code is, is in [documentation/](documentation/README.md).
