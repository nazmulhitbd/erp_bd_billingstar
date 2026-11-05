# Accounts module

Chart of accounts, party sub ledgers, vouchers, posting and the Accounts Register reports.

## Install

Run `schema.sql` once against the database configured in `addon/display/dbconfig.php`. It is safe to run again.

```sh
mysql -u YOUR_DATABASE_USER -p YOUR_DATABASE_NAME < /Users/nazmul/Git/www/billing_star/billing/accounts_info/schema.sql
```

The script creates a starter chart of accounts with groups, cash, bank, debtors, creditors, CC loan, sales, purchase and expenses. It also sets default configuration. The web server user must be able to write to `billing/accounts_info/uploads/`, which stores voucher documents and blocks direct web access.

## Menu: Accounts

| Menu | Page |
| --- | --- |
| Accounts Information, Accounts Group Distribution | `acc_group.php` |
| Accounts Information, General Accounts | `acc_account.php` |
| Accounts Information, Accounts Sub Ledger Group | `acc_sub_ledger_group.php` |
| Accounts Information, Accounts Sub Ledger | `acc_sub_ledger.php` |
| Accounts Information, MoneyReceiptCategory | `acc_receipt_category.php` |
| Accounts Information, Accounts Configuration | `../user/company_settings.php?tab=accounts` |
| Accounts Information, CC Loan Interest | `acc_cc_interest.php` |
| Accounts Information, Over Due Permission | `acc_overdue.php` |
| Voucher Entry, Debit / Credit / Journal / Contra Voucher | `acc_voucher.php?type=...` |
| Voucher Posting | `acc_posting.php` |
| Accounts Register | `acc_register.php`, which opens `acc_report.php` |

## How it works

- **Groups and accounts.** Each group has a nature: asset, liability, equity, income or expense. Sub-groups inherit it. An account's type marks it as cash, bank, receivable, payable or loan.
- **Sub ledgers.** A control account such as Sundry Debtors carries party sub ledgers, grouped by area or branch, for example Elephant Road. "Import from Trader Info" creates sub ledgers for traders, with customers under the receivable account and suppliers under the payable account.
- **Vouchers.** Numbers are prefix + type + year + serial, for example `DESJUR2024000002`. The types are DRV debit (payment), CRV credit (receipt), JUR journal and CON contra. Debits must equal credits.
- **Voucher rules.** A payment must pay out of cash or bank. A receipt must receive into cash or bank. A contra may use only cash and bank. Control accounts need a party.
- **Posting.** Only posted vouchers count in the reports. With "Vouchers need Posting" on, vouchers save as drafts and are posted from Voucher Posting. Posted vouchers are read-only until unposted.
- **CC Loan Interest** charges interest on the daily outstanding loan balance at the rate in force each day. It posts a journal voucher that debits the interest expense account and credits the loan. The same days cannot be charged twice.
- **Over Due Permission** shows on the Due List. A party is flagged "Over limit" when its due is above its trader credit limit plus any active permission.
- **Reports.** Each report can run on voucher date or on cheque date. The Accounts Ledger is titled Creditors or Debtors Ledger for parties. It lists unposted vouchers under "Pending". Branch Wise Due List groups dues by Sub Ledger Group. Date Wise Sales Received reads the payments recorded by the sales module.

## Upgrading and report speed

Run `schema.sql` again after updating these files. The script adds three columns and four indexes to `acc_voucher_lines`, then fills them. It is safe to repeat, and the first run on a large ledger can take about a minute.

Each voucher line keeps a copy of its voucher's posted flag, voucher date and cheque date. `acc_sync_lines()` in `acc_common.php` updates the copy on every save, post, unpost and cancel. Report totals then read one covering index instead of joining every line to its voucher. Any new code that changes a voucher's status or dates must call `acc_sync_lines()`.

The Accounts Register shows previews inside the page. Print prints the preview directly, so it works inside the admin frame and needs no popup permission.

## Receive and payment vouchers, cheques and branches

- **Receive and Payment vouchers** (`acc_rp_voucher.php`) put the cash or bank account in Under. Each row is one party amount with its own mode, cheque number and date, and invoice number. Invoice List offers the party's unpaid sales invoices when its sub ledger is linked to a trader.
- **Inter Branch vouchers** carry a from branch and a to branch. Inter Branch Journal lines each name their branch. Numbers use the IBC, IBD and IBJ codes.
- **Cheque Book.** A cheque book must be approved before its leaves are used. Once a bank account has books, payments by cheque from it must use an unused leaf.
- **Cheque Treatment** marks cheques honoured or dishonoured. Dishonour posts a reversing journal voucher.
- **Cheque History** shows every cheque and its status changes.

