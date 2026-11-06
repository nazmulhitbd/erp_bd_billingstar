# Sample data builder

Makes `install_script/sql/sample_data.sql`, the demo shop the installer loads when **Start with sample data** is ticked on
the Company step. This folder is for developers. It is not part of an install, and `.htaccess` keeps it off the web.

## What the sample contains

A demo electronics shop in Bangladesh, September to November 2026:

- 5 categories, 8 product groups, 5 brands and 14 products.
- A second branch and a showroom store.
- 3 suppliers, 2 dealers and 4 credit customers, with trader groups and sub-ledgers.
- 3 purchases, 1 purchase return, 1 purchase order, 1 stock transfer and 2 stock amendments.
- 9 sales invoices (credit and cash), 1 POS sale, 1 sales order, 1 sales return, 1 quotation and 1 challan.
- Approvals: most documents are approved. Two invoices, the sales order and the challan wait in the approval lists.
- Vouchers: an opening-capital journal, 2 receipts, 2 payments and 1 contra.
- 4 departments, 5 designations and 6 employees, with leave entitlements, 1 leave application and a week of attendance.
- 3 territories and 3 sales representatives, with targets for two months.
- 1 service (RMA) complaint and 1 staff announcement.

All names, brands, addresses and numbers are invented:

- Brands: Nova, Zentro, Kivo, Orbit and Lumo.
- Phone numbers use the `0100000xxxx` range. No Bangladeshi operator uses the `010` prefix, so an SMS can never reach anyone.
- E-mail addresses use `example.com`.

Do not copy rows from a real database into the sample.

## How it is made

Nothing is written into the database by hand. The scripts sign in to a throwaway copy of PetaBill ERP and post to the
application's own forms (Product Information, Trader Information, Purchase, Sales Invoice, POS, Approve, vouchers,
HR, and so on). Stock, ledgers, numbering and approvals therefore agree with each other exactly as they would for a user.

`export_sample.php` then compares that database with a second, untouched fresh install and writes only the rows the
seeding added. It treats some rows specially:

- **Left out:** anything tied to one install or to security. That means the administrator's session, the audit and login
  logs, the licence host, and the tax rates. The application creates tax rates per country on first use.
- **Users:** a reference to the administrator becomes `@sample_user`. The installer sets it to its own administrator's ID.
- **Stores:** they are added by name, because the install's first store may be called something else.
- **Document counters:** they only move forward, so the next real invoice follows the sample's numbers.

## Rebuilding (after a schema change)

1. Start a private MySQL 8 that is used for nothing else. Use a short folder path: macOS allows at most 103 characters
   in a socket path.

   ```sh
   export SAMPLE_MYSQL_DATA=~/petabill-sample-db/data
   mysqld --no-defaults --initialize-insecure --datadir=$SAMPLE_MYSQL_DATA
   cd $SAMPLE_MYSQL_DATA && mysqld --no-defaults --datadir=$SAMPLE_MYSQL_DATA --port=3399 --bind-address=127.0.0.1 \
     --socket=s.sock --mysqlx=OFF --mysql-native-password=ON &
   mysql --socket=s.sock -uroot -e "CREATE USER 'bsapp'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'SampleBuild2026x';
     GRANT ALL ON bs_sample.* TO 'bsapp'@'127.0.0.1'; GRANT ALL ON bs_fresh.* TO 'bsapp'@'127.0.0.1';"
   ```

   The password only protects this throwaway server, which listens on 127.0.0.1 only.

2. Copy the application next to it in the web root, so it opens at `http://localhost/bs_sample_build/`:

   ```sh
   rsync -a --exclude .git --exclude 'addon/display/dbconfig.php' billing_star/ bs_sample_build/
   ```

3. Run `./build_all.sh`. It ends with `BUILD-OK` and the new `sample_data.sql`. If the application refuses a form,
   the script stops and prints the application's own message.

4. Test it: install a fresh copy through the installer with **Start with sample data** ticked, sign in, and open every
   menu page.

5. Afterwards, delete `bs_sample_build/`, stop the private MySQL and delete its folder.
