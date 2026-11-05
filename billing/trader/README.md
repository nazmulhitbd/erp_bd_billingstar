# Trader Info module

Customers, suppliers and other trading parties, kept in one trader master.

## Install

Run `schema.sql` once against the database configured in `addon/display/dbconfig.php`. It is safe to run again.

```sh
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < /Users/nazmul/Git/www/billing_star/billing/trader/schema.sql
```

The web server user must be able to write to `billing/trader/uploads/`, which stores attachments. That folder blocks direct web access. Files are served only through the logged-in page.

## Menu: Task, then Trader Info

| Menu item | Page |
| --- | --- |
| Credit Customer | `trader_info.php?cat=3` |
| Trader wise SR Fixing | `trader_sr.php` |
| Supplier Info | `trader_info.php?cat=1` |
| Trader Group | `trader_group.php` |
| Cash Supplier | `trader_info.php?cat=5` |
| Trading wise Trader Flag Fixing | `trader_flag.php` |
| Trader Search | `trader_search.php` |
| Trader Convert | `trader_convert.php` |

`trader_info.php` serves every category. Its category picker also opens Dealer, Cash Customer, Retailer, Importer, C&F and Indenter.

## How it fits together

- **Categories** are fixed: Supplier, Dealer, Credit Customer, Cash Customer, Cash Supplier, Retailer, Importer, C&F and Indenter. Each one is a customer type or a supplier type.
- **Trader codes** are the category prefix plus the trader number, for example `CRC000012`. Converting a trader gives it the new prefix and logs the old code.
- **Syncing.** Saving a customer-type trader creates or updates its row in `sales_customers`, so the sales pages can find it. Saving a supplier-type trader does the same in `rma_suppliers`. Each sync is skipped when that module is not installed.
- **SR fixing** keeps one current SR per trader. Older assignments stay as history.
- **Flags** hold credit permission, credit limit and days, price level, special discount, VAT exemption and sales or purchase blocks. Other modules can read them from `trader_flags`.
- **Delete** is refused when a trader's customer copy already has an invoice or sales order. Untick Active instead.
