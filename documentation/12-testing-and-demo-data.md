# 12 · Testing and demo accounts

## What the tests are

ALVY has over a thousand **automatic tests**. Each one pretends to be a user, such as a buyer checking
out or a rider delivering, and checks that the system does the right thing. Running them after a
change tells you quickly whether you broke something somewhere else.

The tests never touch your real data. They run on an empty, temporary database made in memory, and
stop immediately if they're ever pointed at a real one.

## Running them

```bash
php artisan test                         # everything in tests/Unit and tests/Feature
php artisan test tests/Feature/Seller    # just one folder
php artisan test --filter=VoucherTest    # just one test file or test name
vendor/bin/phpunit tests/Blackbox        # the mobile test-workbook cases (run separately)
```

The tests are grouped like the code:

- `tests/Feature/Auth`: signing in and up, codes, passwords
- `tests/Feature/Buyer`, `Seller`, `Logistics`, `Rider`, `Linehaul`, `Admin`: each part of the system
- `tests/Feature/Api`: the mobile app's API, including tests that the app and website agree
- `tests/Feature/System`: rules across the whole site, like "every page opens"
- `tests/Blackbox`: the cases from the test workbooks, which save their results to `storage/app/blackbox/`
- `tests/Concerns`: shared helpers that create sample buyers, sellers, riders and trips for tests

Test names often start with the workbook code (`test_sel_prod_001_…` is seller case PROD-001).

### If many tests fail on your computer

Tests that create fake pictures need PHP's image extension **with JPEG support**. If you see
"imagejpeg function is not defined", your PHP can't make JPEG pictures:

- **Herd Lite's PHP** (`C:\Users\<you>\.config\herd-lite\bin\php.exe`) is built without JPEG, so about
  120 tests fail with it. Use another PHP for tests.
- **XAMPP's PHP** has the extension but it is switched off. Either remove the `;` in front of
  `extension=gd` in `C:\xampp\php\php.ini`, or switch it on just for the test run:

```bash
C:\xampp\php\php -d extension=gd vendor/bin/phpunit tests
```

The tests also ignore your personal `.env` for the commission rates (they are pinned in `phpunit.xml`),
so a local `SORTING_CENTER_COMMISSION=10` won't make them fail.

## Demo accounts

To fill your database with a small working marketplace, run:

```bash
php artisan db:seed --class=DemoAccountsAndProductsSeeder
```

It's safe to run more than once: it never deletes anything, and it updates existing demo accounts
instead of making copies. These are the logins it creates:

| Role | Emails | Password |
|---|---|---|
| Admin | `admin@alvy.test` | `Admin@2026` |
| Seller | `seller1@alvy.test` to `seller8@alvy.test` | `Seller@2026` |
| Buyer | `buyer1@alvy.test` to `buyer6@alvy.test` | `Buyer@2026` |
| Logistics company | `logistics1@alvy.test` to `logistics8@alvy.test` | `Logistics@2026` |
| Rider (motorcycle or tricycle) | `rider1@alvy.test` to `rider8@alvy.test` | `Rider@2026` |
| Truck rider (hub to hub) | `courier1@alvy.test` to `courier8@alvy.test` | `Courier@2026` |

The eight logistics companies are in Calamba, Santa Rosa, Biñan, Los Baños, Luisiana, Santa Cruz, Pagsanjan
and Cavinti (logistics1 to logistics8, in that order).
Each has a hub, its barangays, one delivery rider and one truck rider; the number in the email
matches (rider7 and courier7 work for logistics7, Pagsanjan). Each seller has products, most with options.

## Other seeders

Seeders are the files that fill the database with sample data. Besides the demo one:

- **CatalogCategoriesSeeder**: ALVY's product categories (the demo seeder runs it for you).
- **DemoMarketplaceSeeder**: more shops, products with photos, and vouchers.
- **SampleLogisticsSeeder** and **SampleLogisticsAllStatusesSeeder**: a logistics company with
  parcels at every step, handy for looking at the sorting center pages.
- **SampleVoucherSeeder**: sample vouchers.
- **FreshTestAccountsSeeder**: **careful, this deletes every account** and everything belonging to
  them, then makes fresh test accounts.
- The `OneOff` folder: old one-time fixes from early development. A new setup doesn't need them.

Before running any seeder on a database you care about, make a copy of `database/database.sqlite`.

## Where to find it in the code

| What | File |
|---|---|
| Test settings | [phpunit.xml](../phpunit.xml), [tests/TestCase.php](../tests/TestCase.php) |
| Shared test helpers | [tests/Concerns/](../tests/Concerns) |
| Workbook result recording | [RecordsCases](../tests/Blackbox/RecordsCases.php) |
| Demo accounts | [DemoAccountsAndProductsSeeder](../database/seeders/DemoAccountsAndProductsSeeder.php), [SortingCenterCouriersSeeder](../database/seeders/SortingCenterCouriersSeeder.php) |
| Other seeders | [database/seeders/](../database/seeders) |
