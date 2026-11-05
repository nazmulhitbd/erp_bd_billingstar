# Billing Star

Billing and ERP for computer and mobile shops: sales, purchase, stock, accounts, HR and service, across branches and stores.

## Modules

The menu (`billing/menu_catalog.php`) has: Dashboard, Sales, Purchase & Stock, Products, Customers, Sales Team, HR, Stores, Accounts, Approvals, Service, Reports, Audit and Settings.

Besides the staff screens under `billing/` there are two portals:

- `customer_portal/`: customers see their invoices, orders and warranty.
- `sr_portal/`: sales representatives see their customers, sales, targets and commission, book orders and record collections.

Which modules an install may use comes from its licence in Billing CRM (Settings > Service Licence).

## Requirements

PHP 8.0 or newer, MySQL 8.0 or MariaDB 10.3 or newer, and Apache with `.htaccess` allowed. Full steps are in [doc/INSTALL.md](doc/INSTALL.md).

## Install

Copy the folder to the web server and open it in a browser. With no `addon/display/dbconfig.php`, it opens the installer (`install_script/`), which creates the database from `install_script/sql/install.sql`. Delete `install_script` on a live server once the install is done.

After installing, add the licence check to cron, every minute:

```sh
* * * * * php /path/to/billing_star/cron/licence.php >/dev/null 2>&1
```

Without it the pages still check the licence, only less often.

## Database changes

Each module keeps its own `schema.sql`. After changing one, rebuild the single install file:

```sh
php install_script/build_sql.php
```

`addon/display/dbconfig.php` holds the database password. Keep it out of git and out of shared backups.

Remaining work is listed in [future.md](future.md).
# erp_bd_billingstar
