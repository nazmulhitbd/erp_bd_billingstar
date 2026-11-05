# Installing Billing Star

## What you need
- PHP 8.0 or newer with the `mysqli`, `mbstring`, `fileinfo`, `json` and `session` extensions (`gd` is optional)
- MySQL 8.0 or newer
- Apache with `.htaccess` allowed (`AllowOverride All`)
- An empty MySQL database, or a MySQL user allowed to create one

## 1. Put the files on the server
Copy the project into the web folder, for example `/var/www/html/billing_star`.
Do **not** copy `addon/display/dbconfig.php`: its presence is what marks a system as installed.

## 2. Allow the server's address
The application only runs on the addresses listed in `addon/conf/service_check.php`, in the `$Content` line. Add this server's IP there. The installer warns you if it is missing.

## 3. Run the installer
Open `http://YOUR-SERVER/billing_star/`. With no configuration file, it goes to the installer.

1. **Server**: anything marked Fix must be solved. Anything marked Note can wait. If folders are not writable, the wizard shows the exact Terminal command for this server.
2. **Database**: the application gets its own MySQL user, `billinguser` by default, with rights on its database only.
   - *Create the database and user for me*: give a MySQL administrator login such as root. It is used once and not saved.
   - *Use a user I already made*: open "See the SQL", run it yourself as a MySQL administrator, then enter that user.
3. **Company**: company details, the first branch and the first store.
4. **Administrator**: the SuperAdmin login. At least 10 characters with letters and numbers.
5. **Install**: "See the files" lists the one SQL file, `install_script/sql/install.sql`, and the configuration, and each opens to read. Progress shows the part running. If it stops, it says where, and Try Again starts over safely.
6. **Finish**: sign in. If the server could not write the configuration, this step shows the file to save as `addon/display/dbconfig.php`, then press I Saved It.

If root cannot sign in because of its password method, use the SQL by hand. A MySQL user for this PHP needs `mysql_native_password`.

A finished install has 333 tables and 19 views, 352 rows in the phpMyAdmin list. Fewer means a step stopped, so read the message it showed. Start from an empty database, as the installer refuses one that already has tables. Do not import `install.sql` by hand as a way to install: it makes the tables, but not the configuration, company, administrator or licence.

## 4. After installing
- The finish screen deletes the `install_script` folder by itself. If the web server is not allowed to, the screen says so: delete the folder by hand. It stays locked either way.
- Only the session that finished the install can delete the folder. Opening it on a system already running changes nothing.
- Sign in and set up Users, Roles & Permissions.
- Set up in this order: Product Category, Product Classification (a product needs a group), Unit Information, Products, Customers. Then enter opening stock or a Purchase.
- Sales do not create accounting vouchers. Enter them in Accounts, or use the sales and stock reports for those figures.
- Set up Settings > Email & SMS, and use its test buttons. The SMTP password and SMS key are encrypted with a key taken from `addon/display/dbconfig.php`: if that file changes, enter them again.
- Set HR > Department & Designation > Salary Accounts before paying salaries.
- Back up the database and these folders: `billing/hr/uploads`, `billing/sales/uploads`, `billing/accounts_info/uploads`, `addon/uploads`.

## Security

`addon/inc/security.php` runs first on every request: security headers, GET, POST and HEAD only, refusing forms posted from other sites, and locking every cookie (HttpOnly, SameSite, and Secure on https). Use a real certificate and turn on Force HTTPS.

- **Sign-in:** 5 wrong passwords pause a user name and 20 pause an address, for 15 minutes. The table `security_throttle` is made by itself on first use. Old MD5 passwords change to a proper hash the next time each user signs in.
- **Before going live:** keep `addon/display/dbconfig.php` and database dumps out of the repository, delete `install_script`.
- **New pages:** use prepared statements (`sales_exec`, `cp_exec`, `perm_q`), never put `$_GET`, `$_POST` or `$_COOKIE` into SQL, and print input through `sales_h` or `bs_h`.

## Audit

The Audit menu records every sign-in, data change and form, and attacks, for staff, customers, employees and store logins, with address, country, browser and device. Grant each screen in User Access Control. Tables are made by themselves on the first page opened after an update, or run `billing/audit/schema.sql`.

- **New pages are audited by themselves** if they write through `sales_exec`, `sales_insert`, `cp_exec`, `cp_insert`, `perm_q`, `hr_exec`, `rma_exec` or `mysql_query`. Use one of these, or `audit_sql()` around your own statement. Use `audit_event()` for anything else worth recording.
- **Passwords and tokens are never recorded**: `AUDIT_SECRET` in `addon/inc/audit.php` masks any column or form field whose name looks like one. Name new secret fields accordingly.
- **Country** comes from an outside service, each new public address being sent to it once. Switch it off in Audit Settings.
- **Locked out by a block?** `DELETE FROM audit_blocked_ip WHERE ip='YOUR.ADDRESS';`

## Changing a schema
After editing a module's `schema.sql`, run `php install_script/build_sql.php` so new installs get the change.

## Install again
The installer refuses to run while `addon/display/dbconfig.php` exists, and refuses a database that has tables. To start over, remove that file and use an empty database.

## Update an existing system
Do not run the installer. Run the schema files for what changed; they are safe to run again:

```sh
mysql -u root -p YOUR_DATABASE < billing/user/schema.sql
mysql -u root -p YOUR_DATABASE < billing/hr/schema.sql
```

## cPanel and MariaDB

MariaDB 10.3 or newer works; the installer changes the MySQL 8 collation names as it runs. Make the database and user in
cPanel > MySQL Databases, add the user with All Privileges, then choose **Use a user I already made**. Set Database Host
to `localhost`, and enter the full names with your account prefix. Do not upload database dumps to the host.
