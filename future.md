# PetaBill: future work

Last updated: 5 November 2026. Install and security notes are in `doc/INSTALL.md`. This file lists only what is left to do.

## Do first: secrets

- [ ] Change the database password. `addon/display/dbconfig.php` holds a real one, it is committed to git, and it was also printed into a working session by mistake.
- [ ] Stop tracking that file: `git rm --cached addon/display/dbconfig.php`. It is in `.gitignore` now, but that does not remove what is already committed.
- [ ] Remove the four `full_voipswith_*.sql` database dumps from git history, along with `dbconfig.php`. Rewriting history affects everyone with a copy, so plan it.
- [ ] If those dumps hold real customer data, treat it as a data exposure. Check who has had access to the GitHub repository. It answered 404 to an anonymous visitor, so it looks private or gone, but this was not confirmed.
- [ ] Check the `www` folder. If it is ever served, these are exposed: a file in `bus_book copy` named like a GitHub token, `billing_star_db_git_backup` and `Data/creds.md`.
- [ ] If that token is real, revoke it on GitHub.

## Before going live

- [ ] Commit the work. The hardening and Audit menu are in the working folder, uncommitted, among about 3,500 other changes.
- [ ] Rebuild `billing_star_cpanel.zip`. It has the old code.
- [ ] Back up the database, then open the system once. The audit tables create themselves.
- [ ] Delete `install_script` on the live server.
- [ ] Turn on HTTPS and Force HTTPS.
- [ ] User Access Control: grant the Audit screens. Only the SuperAdmin sees them at first.
- [ ] Behind Cloudflare or a proxy? Tick that in Audit Settings. Otherwise every record shows the proxy's address.
- [ ] Test the main flows with real data: invoice, POS, stock, purchase, accounts vouchers and payroll. The full page check opened each page on a nearly empty database.
- [ ] Test on MariaDB. All testing used MySQL 8.4.
- [ ] Add a scheduled database backup, kept on another server.

## Security still open

- [ ] Stored script injection. Older pages print database values without escaping. Input was fixed, but not every display line.
- [ ] Plain text passwords are still in the old reseller, route user and client tables of databases installed before 5 November 2026. The pages are gone; drop the tables with `Data/drop_unused_tables_billing_star.sql` (see below).
- [ ] Older forms have no per-form token. They rely on the same-site check and SameSite cookies. Add tokens as each page is reworked.
- [ ] Content Security Policy is limited, as older pages use inline scripts. Move those into files, then tighten it.
- [ ] Old MD5 passwords change only when each user signs in. Force a reset for inactive users, then remove the MD5 check.
- [ ] Two-step sign-in for the SuperAdmin.
- [ ] `dbconnect.php` lists database processes on every page and kills slow ones. Replace this with a database timeout.
- [ ] An independent penetration test before taking real customers.

## Old VoIP code

The VoIP pages, the reseller and store portal sign-in, `store_portal---`, the VoIP settings and their tables were removed from the code and from `install.sql` on 5 November 2026. Left to do:

- [ ] On each live database, after a backup and after deploying that code, run `Data/drop_unused_tables_billing_star.sql`. PART 3 drops the reseller tables, `store_accounts`, `paypalmail` and the VoIP columns of `admin_role` and `client_theme`.
- [ ] Check the table count in `doc/INSTALL.md` (333 tables, 19 views) on a test install. It was worked out from the old count, not measured.
- [ ] Nothing creates `store_profiles` rows any more (the store portal did), so the Stores screens list no stores on a new install. Add a store setup screen, or drop the Stores menu. Its `reseller_level` and `reseller_id` columns can go then.
- [ ] `admin_role.id_reseller` and `reseller_level` stay, because the role lists filter on them. Drop them once no live database has reseller-owned roles.

## Audit menu gaps

- [ ] Keep a copy of the logs on a separate server. Anyone with direct database access can change them today, so they are not legal-grade evidence.
- [ ] Add a tamper check, such as a hash on each row.
- [ ] Requests refused by Apache are not logged, such as direct hits on `.git` or `dbconfig.php`. A custom error page could record them.
- [ ] Attack detection records but does not refuse. Review false alerts from real use and adjust the patterns.
- [ ] Updates that join tables record the SQL statement, not row by row values. One request records at most 300 writes.
- [ ] Check the terms of `country.is` for commercial use. Each new public address is sent to it once. Consider a local country database instead.
- [ ] Check whether Bangladesh law or customer contracts require telling people about this logging.
- [ ] Add a scheduled report of security events by email.

## Licence and CRM link

- [ ] Add an `audit` row to the CRM's modules if the Audit menu should be sold by package. Today it is always on.
- [ ] Test connecting a real install to the CRM with a connection code from the client portal.
- [ ] Automatic updates from the CRM's Releases page.
