# BusBook RMA (warranty / service)

Admin screens for receiving faulty items from customers, sending them to suppliers, receiving replacements and delivering them back. Every screen is reached from the **RMA** menu in `billing/topmain.php`.

## Install the tables

Install the sales tables first, because RMA shares their customer, product and store records. Then install the RMA tables:

```sh
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < /Users/nazmul/Git/www/bus_book/billing/sales/schema.sql
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < /Users/nazmul/Git/www/bus_book/billing/rma/schema.sql
```

Both scripts can be run more than once. The RMA script also adds the four starting problems (DISPLAY, No power, No Detected, Hang Problem) and a store named `RMA STORE`.

## Job flow

| Step | Screen | Approval does this |
|---|---|---|
| 1 | RMA Complain Receive | Faulty item goes **into** RMA stock |
| 2 | RMA Diagonis (optional) | Saved straight away; marks the job *diagnosed* |
| 3 | RMA Replace Out | Faulty item goes **out** to the supplier |
| 4 | RMA Replace In | Replacement comes **in** as good stock |
| 5 | RMA Replace Delivery | Good item goes **out** to the customer. **Settled** then closes the job |
| – | RMA Item Utilize | Spare parts go **out** when the record is saved (no approval step) |
| – | RMA Service Bill | Charges for paid or out-of-warranty work |

- Documents are saved as **draft** and become read-only once approved under **Approve - RMA**.
- Each search picks only jobs that are ready for that step. For example, Replace In lists only approved Replace Out lines that have not come back yet.

## Screen controls

- **Add** puts the line into the grid and saves the document.
- **Select** loads a line back into the fields. **Edit** saves the changes to that line.
- **Delete** in the grid removes a line. Removing the last line deletes the draft.
- **◀ / ▶** move to the previous or next record. **⏸** clears the line fields.
- **Serial View** lists the serial numbers on the record. **Preview** and **Print** open the printable copy.

## Settings

- **RMA Delivery Store Fixing** sets the default *Store Name* for each screen.
- Document numbers look like `DESCOM2023000003`. To change the `DES` part, edit `RMA_DOC_PREFIX` in `rma_common.php`.

## Files

| File | What it holds |
|---|---|
| `rma_docs.php` | Field layout of every document screen, plus the approve and stock-posting rules |
| `rma_document.php` | Shared save, edit, delete, list and print engine |
| `rma_common.php` | Database helpers, document numbering and search lookups |
| `approve.php?type=…` | The approval screens |
| `stock_register.php`, `service_register.php` | Reports |
