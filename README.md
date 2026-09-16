# VOID Retail System

Laravel rebuild of the VOID clothing system: the existing online store plus a
new point-of-sale register for the counter.

- **Storefront** — the approved e-commerce design, carried over unchanged.
- **Register** — the new POS, built from the selected `pos-design-2` prototype.
- **Back office** — dashboard, stock room, order desk, customers, staff accounts.

---

## Requirements

| | |
|---|---|
| PHP | 8.3+ (developed against 8.4) |
| Database | MySQL / MariaDB |
| Composer | 2.x |
| Node | not required — no build step |

The front end is plain CSS and vanilla JS served from `public/`. There is no
Vite/npm step to run before deploying.

---

## Setup

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

Point `DB_DATABASE` at an empty database, then:

```bash
php artisan migrate
```

```bash
php artisan storage:link
```

### Bringing the existing data across

The legacy system's three MySQL schemas (`users_db`, `customers_db`,
`void_inventory`) are imported by one command. It **reads only** — the source
databases are never modified, so they stay available as a fallback.

```bash
php artisan void:import-legacy
```

Set `APP_TIMEZONE` **before** importing. Legacy timestamps are naive local
times and are read in the application timezone.

The import is safe to re-run: records are matched on their natural keys
(username, email, product name + size, order reference) and updated rather than
duplicated. Passwords on accounts that already exist are left alone, so a
re-run after go-live will not undo a password someone has since changed.

### Without the legacy databases

For a clean environment, seed a working catalog and two logins instead:

```bash
php artisan db:seed
```

That creates `admin` / `password` and `staff1` / `password`. **Change both
before this is exposed to anyone.**

### Running it

```bash
php artisan serve
```

| Area | URL |
|---|---|
| Storefront | `/` |
| Staff sign-in | `/staff/login` |
| Register (POS) | `/pos` |
| Back office | `/admin` |

---

## How it is put together

### Two audiences, two guards

Staff and shoppers authenticate through separate guards (`web` and `customer`)
so a cashier and a shopper can be signed in on the same browser without
clobbering each other — the behaviour the old system got from two differently
named PHP sessions. Signing out of one leaves the other alone.

Staff accounts carry a role of `admin` or `staff`. Staff run the register, the
stock room and the order desk; customers, staff accounts and order deletion are
admin-only, enforced by the `admin` middleware and by `OrderPolicy` /
`ProductPolicy`.

### Catalog

The legacy `products` table held one row per (name, size) — a variant table
with no parent. It is split into:

- `products` — one row per design, with its description and artwork
- `product_variants` — one row per design + size, carrying price, stock and SKU

That gives the storefront a real product page and the register a product row
with per-size chips, without duplicating the design metadata.

### Orders

One `orders` table covers both channels, distinguished by `channel`
(`online` / `pos`). Every component of the total is stored — `subtotal`,
`discount_amount`, `shipping_fee`, `tax_amount`, `total_amount` — so the
receipt, the order desk and the shopper's order history cannot disagree. (The
old schema stored only the subtotal and added the shipping fee separately in
three different views.)

Supporting tables: `order_items`, `payments`, `receipts`,
`order_status_histories`, `held_sales`.

Order lines keep their own copy of the product name, size and unit price, so a
receipt reprinted a year later still shows what was actually sold.

### Order workflow

```
online:  pending ──approve──> approved ──fulfil──> completed
              └──reject──> rejected
counter: completed  (tendered and handed over in one step)
any:     ──cancel──> cancelled  (admin only)
```

Stock follows the status automatically:

- **deducted** when an order starts holding stock (approved / completed)
- **returned** when it stops (rejected, cancelled, or reverted to pending)

Counter sales deduct stock in the same transaction as the sale. Online orders
do not touch stock until a staff member approves the payment proof — the legacy
rule, preserved. Every change is written to `order_status_histories` with who
made it and when.

### Pricing

`PricingService` is the only place that turns lines into money, so a basket
priced in the storefront and the same basket rung up at the register agree to
the centavo. Prices always come from the catalog; a price in a request payload
is ignored.

Rules live in `config/void.php`:

| Setting | Default | Applies to |
|---|---|---|
| `shipping_fee` | ₱50 flat | online only |
| `pos.discount_threshold` / `pos.discount_rate` | 5% at 3+ items | counter only |
| `tax.rate` / `tax.inclusive` | 12% VAT, inclusive | both |
| `low_stock_threshold` | 5 | stock warnings |

VAT is configured inclusive: it is broken out on the receipt and stored for
reporting, but never changes what the customer pays.

### Receipts

A receipt is issued once per order with its own sequential number
(`VD-000001`). Reprints reproduce the original document and are stamped
`REPRINT #n` rather than minting a new number. Orders that predate the receipt
table get one issued on first view.

`/pos/receipts/{ref}` shows it; `/pos/receipts/{ref}/print` opens the bare
print view and sends it to the printer.

---

## The register

`pos-design-2` converted into the live interface, not embedded as a static
page. Products, stock, customers, totals and orders are all live.

- One tap on a size chip adds that item; chips show remaining stock and grey
  out at zero
- Search box takes focus on any keystroke, so a barcode scanner works without
  clicking first
- **F2** opens the payment dialog, and confirms it
- **Esc** closes the topmost dialog
- Cash tendering computes change and refuses a short tender; the check is
  repeated server-side against the authoritative total
- **Hold** parks a basket in the database, so it survives a reload or a move to
  another terminal
- Order tracking and receipt reprint are available without leaving the register

Stock counts refresh from the server after each sale, so the chips stay honest.

---

## Testing

```bash
php artisan test
```

122 tests / 412 assertions, covering authentication, authorization, the cart,
checkout, the full POS sale path, pricing and discounts, stock movement on every
status transition, receipts and reprints, held sales, validation, and a smoke
pass over every GET route including an empty database.

```bash
vendor/bin/pint
```

---

## Notes for whoever picks this up

- `config/void.php` holds the commerce rules. Changing the discount, the
  shipping fee or the VAT rate should not need a code change.
- The storefront design is deliberately untouched. `public/css/index_design.css`
  is the original file; the only additions are at the bottom, under a marked
  comment, and cover markup that did not exist before (flash messages, the
  search form, sold-out labels).
- Payment proofs live on the `public` disk under `payment_proofs/`, reachable
  at `/storage/payment_proofs/...` once `storage:link` has been run.
- Laravel's bundled pagination markup is Tailwind-only; this app ships its own
  at `resources/views/vendor/pagination/void.blade.php`.
