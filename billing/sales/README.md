# Sales module

All sales pages sit under `billing/sales/`. They need an administrator login and the MySQL tables below.

## Install the tables

Run the SQL files in this order against the database configured in `addon/display/dbconfig.php`. Each file is safe to run again.

```sh
cd /Users/nazmul/Git/www/billing_star/billing/sales
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < sales_order/schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < sal_bill_from_sal_order/schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < sales_order_adjustment/schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < sales_retrun/schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < ../inventory/schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < schema_v2.sql
```

`schema_v2.sql` adds what the Sales Invoice/Bill and Sales Order screens need: stock link, branch, member points (`sales_members`, `sales_member_points`), product exchange, attachments (stored in `sales/uploads`, which must be writable) and additional info. Run `../report/schema.sql` again afterwards. Settings live in `inv_settings`: `sales_stock_mode` (deduct / off), `member_points_per_100` (points per 100 Tk of net) and `member_point_value` (Tk per point redeemed).

The account that runs them needs permission to create tables, views and foreign keys.

## Pages

| Page | Path | Tables used |
| --- | --- | --- |
| Sales Invoice | `sales_invoice/sales_invoice.php` | `schema.sql` |
| POS Sales | `pos_sales/pos_sales.php` | `schema.sql` |
| Sales Order | `sales_order/sales_order.php` | `sales_order/schema.sql` |
| Sales Order Adjustment | `sales_order_adjustment/sales_order_adjustment.php` | `sales_order_adjustment/schema.sql` |
| Bill from Sales Order | `sal_bill_from_sal_order/sal_bill_from_sal_order.php` | `sal_bill_from_sal_order/schema.sql` |
| Sales Return | `sales_retrun/sales_return.php` | `sales_retrun/schema.sql` |

The pages share `sales_common.php`, `sales.css` and `sales.js`. Sales Invoice/Bill and Sales Order also use `sales_doc.php`, `sales_doc.js` and `sales_doc.css`; stock for sales is in `sales_stock.php`.

## How the documents connect

- **Sales Order** saves an order with an `SO` number, its lines, an optional advance and payment schedule. Open orders can be cancelled while they have no bill.
- **Order Adjustment** changes an open order and records an `SA` audit row with old and new values. A line cannot drop below the quantity already billed.
- **Bill from Order** creates a normal `SI` sales invoice from all or part of an open order. Line discounts are split by quantity. Unused advance is applied as an invoice payment. The order becomes `converted` when every line is fully billed.
- **Sales Return** returns items from a posted invoice with an `SR` number. The return value can reduce the invoice due, be refunded, or both. Refunds cannot exceed what the customer paid. Voiding a return adds any adjusted due back to the invoice.
- **POS Sales** saves each counter sale as a cash `SI` invoice with reference `POS`, so it also appears in the invoice list and can be returned. It prints an 80 mm receipt.
