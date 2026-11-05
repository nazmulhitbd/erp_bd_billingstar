# Inventory, purchase, challan, price, amendment, transfer and approval screens

## Install

Run these in order against the database in `addon/display/dbconfig.php`. Each is safe to run again.

```sh
mysql -u USER -p DB < billing/sales/schema.sql
mysql -u USER -p DB < billing/sales/sales_order/schema.sql
mysql -u USER -p DB < billing/accounts_info/schema.sql
mysql -u USER -p DB < billing/inventory/schema.sql
mysql -u USER -p DB < billing/purchase/schema.sql
mysql -u USER -p DB < billing/sales/schema_v2.sql
mysql -u USER -p DB < billing/challan/schema.sql
mysql -u USER -p DB < billing/price_history/schema.sql
mysql -u USER -p DB < billing/product/schema.sql
mysql -u USER -p DB < billing/trader/schema.sql
mysql -u USER -p DB < billing/customer/schema.sql
mysql -u USER -p DB < billing/sales_person/schema.sql
mysql -u USER -p DB < customer_portal/schema.sql
mysql -u USER -p DB < sr_portal/schema.sql
mysql -u USER -p DB < store_portal/store/schema.sql
mysql -u USER -p DB < billing/report/schema.sql
```

`inventory/schema.sql` creates the stock tables and adds approval columns to the existing sales invoices, orders and returns. `report/schema.sql` creates the read-only `rep_*` views used by Reports, so run it after the sales order, bill from order and sales return SQL files too.

## Screens

| Menu | File |
| --- | --- |
| Task, Purchase: Purchase / Purchase Order / Purchase Requisition / Purchase Return | `purchase/purchase.php`, `purchase/purchase_order.php`, `purchase/requisition.php`, `purchase/purchase_return.php`. `purchase/schema.sql` adds Order Date, supplier payments, payment schedule and attached files (stored in `purchase/uploads`, which must be writable) |
| Task, Challan: Challan / Challan Return / Sales Bill From Challan / Requisition | `challan/challan.php`, `challan/challan_return.php`, `challan/sales_bill_from_challan.php`, `purchase/requisition.php` |
| Customer Management | `customer/customer_list.php`, `customer_profile.php` (profile, SR, credit rules; writes the customer-type trader and syncs `sales_customers`), `customer_credit.php`, `customer_ledger.php`. Credit rules are enforced when a Sales Invoice is saved |
| Sales Person Management | `sales_person/sr_list.php`, `territory.php`, `sr_target.php`, `sr_commission.php`, `sr_portal_accounts.php` (SR portal logins and rights), `sr_collections.php` (verify / reject / reverse collections recorded on the SR portal). Invoices and orders carry `sr_id`, set from the Sales Person name. |
| SR / Staff Portal | `sr_portal/login.php`: SRs sign in with their login or mobile number to see their customers, sales, target and commission, product price and stock, book orders (arrive as pending Sales Orders) and record collections (become invoice payments only when the office verifies them). Uses `addon/inc/portal_base.php` like `customer_portal`. The old CDR pages are in `billing_star/_archive_cdr` (web access blocked) |
| Stores | `store_admin/store_customers.php` (customers by store) and `store_admin/store_staff.php` (sales persons by store), both read from `store_profiles`. The old store portal and its sign-in through the reseller tables were removed on 2026-11-05 |
| Product Information | `product/product_category.php`, `periodical_warranty.php`, `product_classification.php` (groups and brands), `product_info.php` (the product list), `sales_price_fixing.php`, `unit_info.php`, `warranty.php`, `transaction_category.php`, `purchase_price_fixing.php`. Purchase Price is the cost on purchase screens until a product is bought, Serial Required makes stock documents ask for every serial, and an active Periodical Warranty is the warranty offered when selling |
| Price & History | `price_history/price_quotation.php` (Make Sales Invoice from it), `serial_history.php` (movements and warranty), `sales_price_mrp_view.php` and `sales_price_view.php` (edit prices; every change is logged in `inv_price_history`), `invoice_search.php` (by mobile, invoice no or serial) |
| Task, Amendment | `amedment/purchase_price_amendment.php`, `stock_amendment_in.php`, `stock_amendment_out.php`, `transaction_lock.php` |
| Transfer | `transfer/stock_transfer.php` |
| Approve, Sales / Purchase / Transfer | `approve/approve.php?doc=...` |
| Reports: Stock / Sales / Purchase / Transfer / Challan Register | `report/stock_register.php`, `sales_register.php`, `purchase_register.php`, `transfer_register.php`, `challan_register.php` (Accounts and RMA registers stay in `accounts_info/` and `rma/`) |
| Approve, Account | `accounts_info/acc_posting.php` filtered by voucher type, and `acc_cheque_book.php` |

## How it works

- **One engine.** Every document screen is a short configuration of `inventory/inv_doc_engine.php`. All documents share the `inv_docs` and `inv_doc_lines` tables, with numbers like `DESPUR2026000001`.
- **Source documents.** A purchase order can be made Requisition Wise, a purchase Purchase Order Wise, and a challan Sales Order Wise. A return or bill is made from a challan, and a price amendment from a purchase. Only quantities still open on the source can be used.
- **Stock.** Purchase, Challan Return and Stock Amendment IN add stock. Challan and Stock Amendment OUT remove it. Stock Transfer moves it between stores. A store can never go below zero.
- **Serials.** Serials can be typed, entered as a range, or generated (A.G Serial). Each serial is tracked per product, and Serial History shows every movement.
- **Editing and cancelling.** A document can't change once it is approved, used by a later document, or has serials moved later. Cancelling reverses its stock under the same rules.
- **Sales Bill From Challan** creates a normal sales invoice, with a cash payment when one is entered. Returns use up free quantity first.
- **Purchase Price Amendment** rewrites the purchase's costs and totals, and the stock cost of those items.
- **Branches.** Pick the working branch at the top right of each page. Transaction Lock blocks dates on or before the lock for one branch or all branches. It also applies to accounts vouchers.
- **Barcode labels** load the JsBarcode library from cdnjs, so the browser needs internet access for that page.
- **Sales and stock.** Sales Invoice, POS and Sales Bill From Sales Order take goods out of stock, and a Sales Return of such an invoice puts them back. Each one keeps its stock movement in a linked `inv_docs` row (type `sales_invoice` / `sales_return`, same number as the sales document) that is approved together with it. Set `inv_settings.sales_stock_mode` to `off` to stop sales touching stock. Quantity delivered by a challan made from a sales order is billed from the challan, not again from the order.
