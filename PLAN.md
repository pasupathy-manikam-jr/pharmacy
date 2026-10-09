# Pharmacy Management System — Plan

Status: planning · 2026-10-09 · Target market: Malaysian community/retail pharmacy

## 1. Market scan (what exists)

| Product                                                                                                              | Type        | Stack                   | Price              | Notes                                                                                                                          |
| -------------------------------------------------------------------------------------------------------------------- | ----------- | ----------------------- | ------------------ | ------------------------------------------------------------------------------------------------------------------------------ |
| [KiviCare Pharma](https://codecanyon.net/search/laravel%20pharmacy)                                                  | CodeCanyon  | Laravel, PHP 8, MySQL 8 | $29–89             | Newest (Mar 2026). Pharmacy + inventory.                                                                                       |
| [Medix Pharmacy POS](https://codecanyon.net/search/pharmacy%20pos)                                                   | CodeCanyon  | Laravel                 | $29–75, ~164 sales | Last update Oct 2023 (stale). Buyers ask about multi-branch, stock valuation, per-customer discounts. Demo reported broken.    |
| [Pharmacy Mgmt + Ecommerce PWA](https://codecanyon.net/search/pharmacy%20management%20system)                        | CodeCanyon  | Laravel                 | $60, ~128 sales    | POS + online store.                                                                                                            |
| [Acnoo Pharmacy add-on](https://codecanyon.net/search/pharmacy)                                                      | CodeCanyon  | Laravel                 | $29                | Add-on to Acnoo POS.                                                                                                           |
| [Acculance](https://codecanyon.net/search/Pharmacy%20Inventory%20System) / Delta POS                                 | CodeCanyon  | Laravel                 | $29–39             | Generic POS/inventory/accounting, not pharmacy-aware.                                                                          |
| [emon21/pharmacymanagemetsystem](https://awesome.ecosyste.ms/projects/github.com%2Femon21%2Fpharmacymanagemetsystem) | GitHub      | Laravel/Blade           | free               | Medicines, purchase, supplier, stock, sales, low-stock. Toy-sized (1 star).                                                    |
| [AM-ASKY-97/Laravel-Pharmacy-Management-System](https://github.com/AM-ASKY-97/Laravel-Pharmacy-Management-System)    | GitHub      | Laravel                 | free               | Only prescription upload → quotation flow.                                                                                     |
| NexoPOS                                                                                                              | GitHub      | Laravel + Vue           | free               | Solid generic POS; no batch/expiry/pharmacy concepts.                                                                          |
| [Odoo pharmacy modules](https://apps.odoo.com/apps/modules/17.0/pharmacy_management_app)                             | Odoo app    | Python                  | ~$44               | Medicine types/categories/companies over Odoo stock.                                                                           |
| [ERPNext Pharmacy POS (ECOSIRE)](https://ecosire.com/apps/erpnext/erpnext-pharmacy-pos)                              | ERPNext app | Frappe                  | paid               | Best feature reference: server-side FEFO, prescriptions, refill limits, append-only controlled-drug log, generic substitution. |

**Item-page / demo deep-dive (2026-10-09)**

| Item                                                                                                                                                            | Stack                | Batch/FEFO                                              | Notable features                                                                                                                                                                                                  | Demo                                                                                                  |
| --------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------- | ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| [Medicine Care / Pharmacy Mgmt + Ecommerce PWA](https://codecanyon.net/item/pharmacy-management-software-laravel/36422063) (ayaantec, $60, 145 sales, Aug 2026) | Laravel 11           | **Yes** — batch+expiry, FEFO, FEFO-safe store transfers | POS w/ camera barcode + duplicate-scan guard, customer credit ("due"), partial refunds, Rx→sale, cash/bank/e-wallet accounts, P&L, VAT/GST, thermal invoice, multi-store add-on, storefront w/ Rx upload approval | [demopharma.ayaantec.com](https://demopharma.ayaantec.com/)                                           |
| [Medix](https://codecanyon.net/item/medix-the-pharmacy-pos-management-system/20479904) (Spantiklab, $75, Oct 2023)                                              | Laravel 8 + Vue 3    | No (expired report only)                                | Separate return POS, wholesale POS, supplier/customer ledgers, cheques, full double-entry (CoA, JV, trial balance), webcam Rx capture                                                                             | admin@spantiklab.com / 123456                                                                         |
| [AcnooPharmacy SaaS](https://codecanyon.net/item/acnoo-pharmacy-saas-pharmacy-management-software-flutter-app-with-admin-panel/56960760) ($29, 119 sales)       | Laravel + Flutter    | Not described                                           | SaaS plans, split payment, Bluetooth thermal + label printing, WhatsApp receipt share                                                                                                                             | [acnoopharmacy.acnoo.xyz](https://acnoopharmacy.acnoo.xyz/) shopowner@acnoo.com / 123456              |
| [KiviCare Pharma](https://codecanyon.net/item/kivicare-tm-pharma-addon-pharmacy-inventory-management-in-laravel/60994895) ($89)                                 | Laravel              | Batch + expiry alerts                                   | Clinic add-on: doctors prescribe from pharmacy stock, commissions. No POS.                                                                                                                                        | apps.iqonic.design/kivicare-laravel                                                                   |
| [PharmacyNext](https://codecanyon.net/item/pharmacynext-modern-pharmacy-pos-management-system/57668356) ($98)                                                   | Next.js + MongoDB    | Batch, FEFO flags                                       | Cash-drawer sessions w/ reconciliation, loyalty, 58/80mm receipts, AI (Rx photo OCR, interactions, reorder forecast)                                                                                              | [pharmacy-next-sigma.vercel.app](https://pharmacy-next-sigma.vercel.app/) admin@pharmacy.com / 123456 |
| [MediPulse](https://codecanyon.net/item/medipluse-pharmacy-crm-saas-pharmacy-crm-medical-store-management-system-medical-shop-system/56515752) ($99)            | React + Node + Mongo | No                                                      | Daily closing, supplier/customer balances, short/expiring stock views. Author doesn't support it.                                                                                                                 | erp.samyotech.in                                                                                      |

**Takeaways**

- Closest competitor is ayaantec's Medicine Care: it already has batch + FEFO. Nobody has a poison register, refill enforcement, MyInvois, or the MOH 2027 Rx fields — that's our edge.
- Features adopted from the demos into v1: customer credit balance + settle, partial returns, cash-drawer shift with reconciliation, camera/keyboard barcode, purchase payment status (paid/partial/pending), 58/80mm receipt.
- CodeCanyon items are cheap generic Laravel POS apps with a medicine skin. None advertise a poison register or Malaysian compliance (MyInvois, Poisons Act).
- The only feature set worth copying is the ERPNext one: **batch-level stock, FEFO enforced on the server for every sale path, append-only controlled-drug register with running balance, prescription + refill enforcement, generic substitution**.
- **Decision: build our own** on the house stack. Buying gets us a codebase we'd rewrite to add the parts that matter, and we already own the hard Malaysian piece (MyInvois SDK in `~/Sites/E-invoice`).

## 2. Malaysian compliance drivers

- **Poisons Act 1952 / Poisons Regulations** ([MIMS classification](https://www.mims.com/malaysia/viewer/html/poisoncls.htm), [MOH Good Dispensing Practice](https://pharmacy.moh.gov.my/sites/default/files/document-upload/gdsp-2016-final.pdf)):
    - Group B/C: dispensed medicine → entry in the **Prescription Book** on the day of sale (s.24).
    - Group D: entry in the **Poisons Book**.
    - Psychotropics / Dangerous Drugs Act items: separate register with running balance.
    - ⇒ Registers must be append-only, corrections as new reversing entries, printable per statutory format.
- **New MOH prescription format from 1 Jan 2027** ([Malay Mail](https://www.malaymail.com/amp/news/malaysia/2026/04/17/moh-to-introduce-new-prescription-format-with-fuller-patient-and-drug-details-from-2027/216577)): name, age, MyKad, weight, sex, contact, diagnosis, allergies, citizenship; drug, form, dose, frequency, duration, qty. Scope (MOH facilities vs private) unconfirmed — model the fields anyway, they're cheap.
- **LHDN MyInvois e-invoice**: B2C walk-ins → monthly **consolidated e-invoice**; customer asks for one → individual e-invoice. 7-year retention. Use our `pasupathy-manikam-jr/e-invoice-sdk`.
- **PDPA**: patient data → role-scoped access, audit log, no patient data in logs.
- Open question for the client: is an electronic Prescription/Poisons Book accepted by Pharmacy Enforcement Division, or must we print/sign? (Plan: electronic + printable daily export.)

## 3. Stack (lms-mentor / luris-soft backend & deploy conventions, Vue frontend)

- Latest Laravel (13.x), PHP 8.4, official **Laravel Vue starter kit** (`laravel new pharmacy --vue`): Inertia + Vue 3 `<script setup>` + TS, Tailwind 4, shadcn-vue (Reka UI), Fortify, Wayfinder, plus `spatie/laravel-permission`.
- Pin exact versions at scaffold time (`composer show laravel/framework`, lockfiles committed).
- MySQL 8. Money as integer sen. Quantities as integer base units (tablet/ml).
- Barcode scanning = keyboard-wedge input (no lib). Receipts = browser print CSS for 80mm thermal (no driver lib).
- CI: `composer ci:check` (pint + eslint/prettier + `vue-tsc --noEmit` + larastan + phpunit). Deploy: GitHub Actions builds assets with `APP_PATH_PREFIX=pharmacy` → `deploy` branch → staging `~/pharmacy`, `https://ui.staging.oriclabdev.com/pharmacy`, DB `stagingoriclabde_pharmacy`. No node on server.

## 4. Domain model

```
branches(id, name, licence_no, address, tin, …)            # multi-branch from day 1 — retrofitting is painful
users + roles: owner, pharmacist, assistant, cashier
products(id, name, generic_name, strength, form, poison_group[none|B|C|D|psychotropic|DDA],
         barcode, base_unit, pack_size, reorder_level, tax_code, mal_reg_no, is_active)
generics: products.generic_name drives substitution lookup (no separate table until needed)
suppliers(id, name, tin, contact, …)
batches(id, product_id, batch_no, expiry_date, cost_sen, supplier_id)        # unique(product_id, batch_no)
stock_movements(id, branch_id, batch_id, qty_delta, type[grn|sale|return|adjust|transfer|expire_writeoff],
                ref_type, ref_id, user_id, created_at)                       # APPEND-ONLY ledger
stock_levels(branch_id, batch_id, qty)                                      # derived cache, updated in same txn
purchase_orders / goods_receipts (+ lines with batch_no, expiry, qty, cost)
patients(id, name, mykad, dob, sex, weight, phone, allergies, citizenship)
prescriptions(id, patient_id, prescriber_name, prescriber_mmc_no, clinic, diagnosis, issued_at,
              image_path, refills_allowed, refills_used)
  prescription_items(drug, form, dose, frequency, duration, qty)
sales(id, branch_id, no, patient_id?, prescription_id?, cashier_id, pharmacist_id?, totals…, status, einvoice_status)
  sale_lines(product_id, batch_id, qty, price_sen, discount_sen, tax_sen)
  payments(sale_id, method[cash|card|ewallet|qr], amount_sen, ref)
poison_register(id, branch_id, register[prescription_book|poisons_book|psychotropic|dda],
                sale_line_id, product_id, batch_id, patient snapshot, prescriber snapshot,
                qty, balance_after, pharmacist_id, reverses_id?, created_at)   # APPEND-ONLY
einvoice_documents → owned by the E-invoice SDK
audit_log(user_id, action, subject, before/after json, ip, at)
```

**Invariants (enforced in one place each)**

1. Stock only changes via `StockLedger::move()` — single service, inside a DB transaction with `lockForUpdate` on the stock_levels row. No negative stock unless branch setting allows it.
2. **FEFO**: `StockLedger::allocate(product, branch, qty)` returns batches ordered by `expiry_date`, skipping expired. Every sale path (POS, API, import) calls it; manual batch override requires pharmacist role + reason in audit log.
3. Expired batches are unsellable (server-side check, not UI-only).
4. Selling a product with `poison_group ≠ none` requires a pharmacist on the sale and writes the register row in the same transaction. B/C/psychotropic/DDA also require a prescription.
5. Registers + stock_movements are never updated/deleted: DB trigger or model `updating/deleting` guard throws; corrections are reversing rows.
6. Refills: total dispenses on a prescription ≤ 1 + `refills_allowed`, checked on dispense.

## 5. Modules & milestones

**M1 — Foundation (week 1)**

- Scaffold with Laravel Vue starter kit, roles/permissions, branches, branch switcher in session.
- Products, suppliers CRUD; CSV import for products (opening catalogue).
- CI green + staging deploy pipeline working end-to-end on an empty app.

**M2 — Inventory core (week 2)**

- `StockLedger` service + tests (FEFO, locking, no-negative, expired block).
- Goods receipt (batch/expiry/cost entry), stock adjustments, opening-stock import by batch.
- Stock list per branch, batch drill-down, movement history.

**M3 — POS (weeks 3–4)**

- Fast POS screen: barcode/keyword search, cart, auto-FEFO batch shown per line, discounts, multi-tender payments, hold/resume, returns/refund (reverses stock).
- Thermal receipt print view.
- Cashier shift open/close with cash count.

**M4 — Pharmacy-specific (week 5)**

- Patients, prescriptions (with 2027 MOH fields + scan upload), refills.
- Poison-group enforcement at checkout; Prescription Book / Poisons Book / psychotropic registers with running balance; printable daily/monthly register PDF (browser print).
- Allergy warning when patient has a matching generic on record.
- Generic substitution: same `generic_name` + strength + form, in-stock batches.

**M5 — Purchasing & alerts (week 6)**

- Purchase orders → GRN, supplier returns.
- Dashboard: low stock (≤ reorder level), near-expiry (30/60/90 days), expired, today's sales.
- Scheduled daily job: near-expiry + low-stock digest email.
- Inter-branch stock transfer (two ledger moves in one txn).

**M6 — E-invoice & reports (week 7)**

- Wire E-invoice SDK: monthly consolidated B2C job; individual e-invoice on request from sale screen; QR on receipt when validated.
- Reports: sales by day/product/cashier, gross margin (FEFO cost), stock valuation, expiry write-offs, register exports. CSV export.

**M7 — Hardening (week 8)**

- Audit log viewer, PDPA review, backups, UAT with a pilot pharmacy, opening-stock migration rehearsal.

## 6. Explicitly NOT in v1 (add when a client asks)

- Online store / PWA / delivery — CodeCanyon competitors have it; add after POS is solid.
- Insurance / panel-clinic claims, loyalty points, offline-first POS (needs service worker + sync conflict handling — big).
- Full accounting (GL). Export sales/purchases CSV to client's accounting system instead.
- Drug-interaction database (licensed data, e.g. MIMS) — allergy check by generic only for now.
- Mobile app. The web POS works on tablets.

## 7. Testing

- Feature tests for each invariant in §4 (FEFO order, expired block, concurrent sale on last unit, register written + immutable, refill limit, poison sale without pharmacist rejected).
- One end-to-end feature test: GRN → POS sale → return → stock and register balances correct.
- MyInvois: SDK sandbox only in CI via fake driver.

## 8. Open questions for client

1. Single pharmacy or chain? (Model supports branches either way.)
2. Electronic registers acceptable to Pharmacy Enforcement, or printed + signed daily?
3. Existing data to migrate (catalogue, opening stock with batch/expiry)?
4. Hardware: barcode scanner, 80mm thermal printer, cash drawer (kicks via printer).
5. E-invoice: do they already have LHDN MyInvois credentials + digital certificate?

## 9. Build status (2026-10-09)

Scaffolded from the current Laravel Vue starter kit (Laravel 13, Inertia 3, Vue 3.5, shadcn-vue, Fortify) + spatie/laravel-permission + in-house E-invoice SDK. `composer ci:check` green (67 tests). Not yet a git repo.

**Done: all of M1–M7**

| Area        | What's there                                                                                                                                                                                                                                                                                                             |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Access      | Roles owner / pharmacist / assistant / cashier; Staff page (add, change role, reset password); public sign-up and delete-account removed; quick login when `DEMO_LOGINS=true` (password `Zx123456`).                                                                                                                     |
| Branches    | Multi-branch: owner adds/edits branches (licence, address, TIN/BRN/SST/MSIC) and switches branch from the sidebar; every query is scoped to the user's branch.                                                                                                                                                           |
| Stock       | `StockLedger` is the only writer: FEFO, expired unsellable, row locks, no negatives; append-only movements with reasons. Goods received (from a PO or ad hoc), stock by batch, stock take / write-off (damaged, expired, supplier return), inter-branch transfer, movement history.                                      |
| Purchasing  | Purchase orders (pre-filled from low stock), sent / cancelled / received, printable PO, receive delivery against PO. Product CSV import with template (all-or-nothing validation).                                                                                                                                       |
| Counter     | Shifts (opening float, expected vs counted cash, variance); POS needs an open shift; cash / card / e-wallet / on account; discounts; allergy warning; 80mm receipt. Partial refunds per line, exact to the sen. Customer accounts with balance and payments.                                                             |
| Pharmacy    | Poison-group rules (pharmacist required; Rx for B / psychotropic / DDA); prescriptions with 2027 MOH fields; refills limited to 1 + refills allowed; Prescription Book / Poisons Book / Psychotropic / DDA registers, append-only with reversing rows, printable.                                                        |
| E-invoice   | Individual e-invoice for customers with a TIN, refund notes against validated ones, monthly consolidated e-invoice for walk-in sales (net of refunds), poll / cancel, MyInvois credentials per TIN. Uses `pasupathy-manikam-jr/e-invoice-sdk`; `EINVOICE_DRIVER=fake` locally.                                           |
| Insight     | Dashboard (net takings, expiring, low stock); reports (daily, by product with margin, by staff, payment methods, stock valuation, write-offs) with CSV export; audit log (owner); daily 07:47 stock digest email (`pharmacy:stock-digest`).                                                                              |
| Languages   | English, Bahasa Melayu, 中文: switcher in the header, login and welcome page; choice saved per user; `lang/ms.json` / `lang/zh.json` keyed by the English text (a test fails if any interface string lacks a translation); Laravel validation/auth messages translated; stock digest email in each recipient's language. |
| Deploy prep | `scripts/deploy.sh`, `.github/workflows/deploy-assets.yml` (builds with `APP_PATH_PREFIX=pharmacy`), Vite base-path plugin.                                                                                                                                                                                              |

**Before go-live**

- Create the GitHub repo, push, set up staging (`~/pharmacy`, DB `stagingoriclabde_pharmacy`, symlink into `ui.staging.oriclabdev.com/public/pharmacy`, `.env` with APP_URL/ASSET_URL/SESSION_PATH/SESSION_COOKIE, `QUEUE_CONNECTION=sync`, cron for `schedule:run`).
- Confirm the poison-group → register mapping (`app/Enums/PoisonGroup.php`) with the client's pharmacist, and whether electronic registers are accepted.
- Enter real branch TIN and MyInvois sandbox credentials; send one consolidated month in sandbox before production.
- Passkey login won't work under the `/pharmacy` subfolder (the `@laravel/passkeys` package uses root URLs); email login does.

**Later, if a client asks**: online store / PWA, insurance & panel claims, loyalty, offline POS, drug-interaction data (licensed), full accounting.
