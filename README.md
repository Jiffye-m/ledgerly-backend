# Ledgerly Backend — Foundation + Auth + Categories/Products

## 1. Fresh install
```bash
composer create-project laravel/laravel ledgerly-backend
cd ledgerly-backend
php artisan install:api
```

## 2. Database
`.env`:
```
DB_CONNECTION=mysql
DB_DATABASE=ledgerly
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

## 3. IMPORTANT — fix the default users migration
Open `database/migrations/0001_01_01_000000_create_users_table.php` and
**delete the `Schema::create('users', ...)` block** (and its matching
`Schema::dropIfExists('users')` in `down()`). Keep `password_reset_tokens`
and `sessions`. Our own `2024_01_01_000002_create_users_table.php` is the
only migration that should create `users` — it needs to run after
`businesses` exists for the foreign key.

## 4. Copy these files in

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php          ← overwrite (adds authorizeBusiness() helper)
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── BusinessController.php
│   │       ├── CategoryController.php
│   │       └── ProductController.php
│   ├── Middleware/
│   │   └── EnsureBusinessExists.php
│   ├── Requests/
│   │   ├── Auth/
│   │   ├── Business/
│   │   ├── Category/
│   │   │   ├── StoreCategoryRequest.php
│   │   │   └── UpdateCategoryRequest.php
│   │   └── Product/
│   │       ├── StoreProductRequest.php
│   │       └── UpdateProductRequest.php
│   └── Resources/
│       ├── UserResource.php
│       ├── BusinessResource.php
│       ├── SettingResource.php
│       ├── CategoryResource.php
│       └── ProductResource.php
└── Models/                          ← overwrite default User.php
database/migrations/                 ← the 9 custom migrations
routes/api.php                       ← overwrite
```

## 5. Register the new middleware
Open `bootstrap/app.php` and add the alias inside `->withMiddleware()`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'has.business' => \App\Http\Middleware\EnsureBusinessExists::class,
    ]);
})
```

If `bootstrap/app.php` doesn't already have a `->withMiddleware()` call,
add one to the `Application::configure()` chain.

## 6. Run it
```bash
php artisan migrate:fresh
php artisan serve
```

## Endpoints

| Method | Endpoint                | Auth | Business required | Purpose                      |
|--------|--------------------------|------|--------------------|-------------------------------|
| POST   | /api/register            | No   | —                  | Create account                |
| POST   | /api/login                | No   | —                  | Get a token                   |
| GET    | /api/me                   | Yes  | —                  | Current user + business       |
| POST   | /api/logout                | Yes  | —                  | Revoke token                  |
| POST   | /api/business              | Yes  | —                  | Create your business (once)   |
| GET    | /api/business              | Yes  | —                  | View your business            |
| PUT    | /api/business              | Yes  | —                  | Update business (owner only)  |
| GET    | /api/categories            | Yes  | Yes                | List categories               |
| POST   | /api/categories            | Yes  | Yes                | Create category                |
| GET    | /api/categories/{id}       | Yes  | Yes                | View category                  |
| PUT    | /api/categories/{id}       | Yes  | Yes                | Update category                |
| DELETE | /api/categories/{id}       | Yes  | Yes                | Delete category (products keep, category_id → null) |
| GET    | /api/products               | Yes  | Yes                | List products — supports `?search=&category_id=&low_stock=1&per_page=` |
| POST   | /api/products               | Yes  | Yes                | Create product                 |
| GET    | /api/products/{id}          | Yes  | Yes                | View product                   |
| PUT    | /api/products/{id}          | Yes  | Yes                | Update product                 |
| DELETE | /api/products/{id}          | Yes  | Yes                | Delete product                 |

## Test it
```bash
TOKEN="paste your token here"

# Create a category
curl -X POST http://localhost:8000/api/categories \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Phones"}'

# Create a product
curl -X POST http://localhost:8000/api/products \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"iPhone 13","category_id":1,"cost_price":250000,"selling_price":320000,"quantity":5,"low_stock_threshold":2}'

# List, search, filter low stock
curl "http://localhost:8000/api/products?search=iphone" -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8000/api/products?low_stock=1" -H "Authorization: Bearer $TOKEN"
```

## Design notes
- **`authorizeBusiness()` in the base Controller** — every show/update/destroy
  calls this first. Route-model-binding will happily fetch product #17 even
  if it belongs to a different shop; this stops that.
- **`has.business` middleware** blocks products/categories/etc. until the
  user has created a business — no half-broken state where a brand-new
  account can create a product with `business_id = null`.
- **Slugs and SKUs are unique per business**, not globally — two shops can
  both have a "Phones" category or a `SKU-001`.
- **Deleting a category doesn't delete its products** — `category_id` just
  goes null (already set up via `nullOnDelete()` in the migration).

## Next
Customers + Sales/POS (Day 3–4) — the sales endpoint is the one that
actually decrements stock and snapshots price/name into `sale_items`. Say
the word.

---

# Day 3–4 addition — Customers + Sales/POS

## New files
```
app/Http/Controllers/Api/CustomerController.php
app/Http/Controllers/Api/SaleController.php
app/Http/Requests/Customer/{Store,Update}CustomerRequest.php
app/Http/Requests/Sale/StoreSaleRequest.php
app/Http/Resources/{Customer,Sale,SaleItem}Resource.php
routes/api.php                         ← overwrite again
```
No new migration needed — `customers`, `sales`, `sale_items` already exist
from Day 1.

## New endpoints

| Method | Endpoint              | Purpose                                   |
|--------|------------------------|--------------------------------------------|
| GET    | /api/customers          | List — `?search=`                          |
| POST   | /api/customers          | Create                                      |
| GET/PUT/DELETE | /api/customers/{id} | View / update / delete                  |
| GET    | /api/sales               | List — `?customer_id=&status=&from=&to=`   |
| POST   | /api/sales               | **Create a sale (the POS checkout)**       |
| GET    | /api/sales/{id}          | View one sale with items                   |
| POST   | /api/sales/{id}/void      | Void a sale, restock items, reverse totals |

## How POST /sales works
```bash
curl -X POST http://localhost:8000/api/sales \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{
    "customer_id": 1,
    "items": [
      {"product_id": 1, "quantity": 2},
      {"product_id": 3, "quantity": 1, "unit_price": 15000}
    ],
    "discount": 0,
    "tax": 0,
    "payment_method": "cash",
    "amount_paid": 655000
  }'
```
- `unit_price` is optional per item — omit it and it uses the product's
  current `selling_price`. Pass it to override (e.g. a manual discount on
  one item).
- The whole thing runs in **one DB transaction** with `lockForUpdate()` on
  each product row. If two cashiers try to sell the last unit of something
  at the same moment, the second request waits for the first to finish,
  then correctly sees the reduced stock — it can't oversell.
- Stock check happens **before** anything is written — if any item doesn't
  have enough stock, the whole sale fails with a 422 and nothing is changed.
- `sale_items` stores a **snapshot** of `product_name`, `unit_price`, and
  `cost_price` at sale time. Change a product's price next week, and last
  week's receipts and profit reports are unaffected.
- `invoice_number` is sequential per business (`INV-000001`, `INV-000002`,
  ...) generated inside the same locked transaction.
- If `customer_id` is set, their `total_purchases` is incremented — useful
  later for a "top customers" report with zero extra queries.

## Voiding vs. deleting
Sales are never hard-deleted through the API — `POST /sales/{id}/void`
instead: it restocks every item, reverses the customer's total, and flips
`status` to `void` while keeping the invoice number and full history intact.
This matters for reconciliation — a deleted row can't explain a stock
discrepancy later, a voided one can.

## Note on cost_price
`SaleItemResource` deliberately does **not** expose `cost_price` — that's
your margin, not something that belongs on a customer-facing receipt. It's
still in the database for profit reports (Day 8).

## Next
Expenses + Reports (Day 8 in the milestone plan — the dashboard/profit
numbers everything else feeds into). Say the word.

---

# Day 5–8 addition — Expenses + Reports

## New files
```
app/Http/Controllers/Api/ExpenseController.php
app/Http/Controllers/Api/ReportController.php
app/Http/Requests/Expense/{Store,Update}ExpenseRequest.php
app/Http/Resources/ExpenseResource.php
routes/api.php                         ← overwrite again
```
No new migrations — `expenses` table already exists from Day 1.

## New endpoints

| Method | Endpoint                | Purpose |
|--------|---------------------------|---------|
| GET/POST | /api/expenses            | List (`?category=&from=&to=`) / create |
| GET/PUT/DELETE | /api/expenses/{id} | View / update / delete |
| GET | /api/reports/dashboard      | Home screen: today's sales/profit/expenses, low stock, recent sales |
| GET | /api/reports/daily?date=    | One day's sales, profit, expenses, breakdown by payment method |
| GET | /api/reports/monthly?month=&year= | Day-by-day chart data for the month, totals, top 5 products |
| GET | /api/reports/profit?from=&to=      | Revenue, cost of goods sold, gross/net profit for a date range |

## How profit is calculated
Every `sale_items` row snapshots `unit_price` and `cost_price` at the moment
of sale (from Day 3–4). Reports never touch the live `products` table for
historical numbers — that's what makes a March profit report still correct
in July even after prices changed:

```
gross_profit = Σ (unit_price − cost_price) × quantity   [per sale item]
net_profit   = gross_profit − expenses_total
```

`/reports/profit` returns two revenue figures on purpose:
- `order_revenue` — what customers actually paid (`sales.total`, after
  per-order discount/tax)
- `item_revenue` — sum of item subtotals, before any order-level discount

They usually differ slightly whenever a sale had a discount or tax applied.
`gross_profit` is derived from `item_revenue`, since that's what ties back
to actual product margin — a discount is a business decision, not a change
in what the product cost to acquire.

## Test it
```bash
curl -X POST http://localhost:8000/api/expenses \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"Shop rent","category":"Rent","amount":50000,"expense_date":"2026-07-13"}'

curl http://localhost:8000/api/reports/dashboard -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8000/api/reports/monthly?month=7&year=2026" -H "Authorization: Bearer $TOKEN"
```

## That's the full MVP backend
Auth, Business, Categories, Products, Customers, Sales/POS, Expenses,
Reports — every module from the master plan's Core Modules list is now
covered. What's left on the backend side is polish (rate limiting,
form-request error formatting) rather than new features.

## Next
Your call — either the React web dashboard consuming this API (the
originally planned next step), or PDF/WhatsApp/Email receipts (Day 5–6 in
the milestone doc) before moving to the frontend. Which do you want first?

---

# Day 5–6 addition — PDF / Email / WhatsApp Receipts

## 1. Install the PDF package
```bash
composer require barryvdh/laravel-dompdf
```
No `vendor:publish` needed — the `Pdf` facade auto-discovers.

## 2. New files
```
app/Http/Controllers/Api/ReceiptController.php
app/Services/ReceiptPdfService.php
app/Services/WhatsAppService.php
app/Mail/ReceiptMail.php
resources/views/receipts/pdf.blade.php
resources/views/emails/receipt.blade.php
routes/api.php                              ← overwrite again
```

## 3. Add to `config/services.php`
Open the file and add a `whatsapp` key inside the returned array (don't
overwrite the whole file — it already has `mailgun`, `postmark`, etc.):
```php
'whatsapp' => [
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
],
```

## 4. `.env` additions
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

# Optional — leave blank to use the wa.me fallback link (see below)
WHATSAPP_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=
```
For MAIL_*, any SMTP works to start — Gmail SMTP, Brevo, Mailtrap for
testing. Gmail has sending limits, so for a live product move to Brevo or
similar once you have real volume.

## New endpoints

| Method | Endpoint                          | Auth | Purpose |
|--------|-------------------------------------|------|---------|
| GET    | /api/sales/{id}/receipt/pdf          | Yes  | Download the PDF directly |
| POST   | /api/sales/{id}/receipt/email         | Yes  | Email it — body: `{"email"?: "..."}`, falls back to customer's saved email |
| POST   | /api/sales/{id}/receipt/whatsapp      | Yes  | Send/share via WhatsApp — body: `{"phone"?: "..."}`, falls back to customer's saved phone |
| GET    | /api/receipts/{id}/view?signature=...  | No   | Public — the link customers actually open, no login needed |

## Why WhatsApp has two modes
Sending WhatsApp messages programmatically requires a **WhatsApp Business
Cloud API** account (Meta) — that needs business verification and, outside
a 24-hour window of the customer messaging you first, an approved message
*template* rather than free text. That's not something to wait on before
shipping.

So `POST /sales/{id}/receipt/whatsapp` works in two modes:
- **If `WHATSAPP_TOKEN` is set** in `.env` → sends directly via the Cloud API.
- **If not set (or the API call fails)** → returns a `wa.me` link with the
  receipt message pre-filled. Your frontend just opens that link — the
  cashier's own WhatsApp opens with the message ready to send in one tap.
  No account, no approval, works today.

Response shape tells you which happened:
```json
{ "sent_via": "api", "message": "Receipt sent via WhatsApp." }
// or
{ "sent_via": "link", "whatsapp_link": "https://wa.me/234...", "message": "Hi! Here's your receipt..." }
```

## Why the public link is "signed", not just a plain URL
`/api/receipts/{id}/view` needs to work **without login** — a customer
reading WhatsApp or email has no Sanctum token. A signed URL
(`URL::temporarySignedRoute`) embeds a cryptographic signature tied to the
sale ID and an expiry (7 days here), so nobody can guess
`/api/receipts/2/view`, `/api/receipts/3/view`, etc. and see someone else's
receipt — only a link Ledgerly itself generated will pass the signature
check.

## Test it
```bash
# Download PDF directly
curl -o receipt.pdf http://localhost:8000/api/sales/1/receipt/pdf -H "Authorization: Bearer $TOKEN"

# Email it
curl -X POST http://localhost:8000/api/sales/1/receipt/email \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"email":"customer@example.com"}'

# WhatsApp it (will return a wa.me link unless WHATSAPP_TOKEN is set)
curl -X POST http://localhost:8000/api/sales/1/receipt/whatsapp \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"phone":"08012345678"}'
```

## Next
The React web dashboard, consuming everything built so far — auth,
business setup, categories/products, customers, sales/POS, expenses,
reports, and now receipts. Say the word and I'll start scaffolding it.

---

# Team / Roles / Profile / Business Settings

## 1. Register two more middleware aliases
Add to `bootstrap/app.php` alongside `has.business` from before:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'has.business' => \App\Http\Middleware\EnsureBusinessExists::class,
        'is.owner' => \App\Http\Middleware\EnsureIsOwner::class,
        'not.staff' => \App\Http\Middleware\EnsureNotStaff::class,
    ]);
})
```
Restart `php artisan serve` after saving — same reason as last time, config/route changes need a fresh process.

## 2. New files
```
app/Http/Middleware/EnsureIsOwner.php
app/Http/Middleware/EnsureNotStaff.php
app/Http/Controllers/Api/TeamController.php
app/Http/Controllers/Api/ProfileController.php
app/Http/Controllers/Api/BusinessController.php   ← updated, +updateSettings
app/Http/Requests/Team/{Store,Update}TeamMemberRequest.php
app/Http/Requests/Profile/{Update,UpdatePassword}Request.php
app/Http/Requests/Settings/UpdateSettingsRequest.php
app/Http/Resources/TeamMemberResource.php
routes/api.php                                     ← overwrite
```

## The permission model
Three roles, already on the `users` table since Day 1 — this just makes
them mean something:

| Action | Staff | Admin | Owner |
|---|---|---|---|
| Sell, add customers, log expenses, view reports | ✅ | ✅ | ✅ |
| Edit products/categories/customers/expenses | ✅ | ✅ | ✅ |
| **Delete** products/categories/customers/expenses | ❌ | ✅ | ✅ |
| **Void** a sale | ❌ | ✅ | ✅ |
| Manage team (add/edit/deactivate) | ❌ | ❌ | ✅ |
| Business settings (receipt footer, tax rate) | ❌ | ❌ | ✅ |
| Business profile (name, address) | ❌ | ❌ | ✅ |

The logic behind the line between admin and staff: **staff can do
anything that only moves the business forward** (a sale, a new customer,
logging an expense). **Deleting or voiding undoes something that already
happened** — the kind of action that should need a second set of eyes,
not something a cashier does alone under pressure at the till.

## New endpoints

| Method | Endpoint | Who | Purpose |
|---|---|---|---|
| GET | /api/team | Any team member | List everyone on the business |
| POST | /api/team | Owner | Add a team member (name, email, phone, password, role: admin\|staff) |
| PUT | /api/team/{id} | Owner | Edit name/phone/role/is_active, optionally reset password |
| DELETE | /api/team/{id} | Owner | **Deactivates**, doesn't delete (see below) |
| PUT | /api/profile | Anyone | Update your own name/phone/email |
| PUT | /api/profile/password | Anyone | Change your own password (requires current password) |
| PUT | /api/business/settings | Owner | Receipt footer, tax rate, WhatsApp/email toggles, low-stock alerts |

## Why "removing" a team member deactivates instead of deleting
`sales.user_id` and `expenses.user_id` both `cascadeOnDelete()` — if you
actually deleted a `User` row, every sale and expense they ever logged
would be deleted with them. That's a silent, catastrophic loss of records
disguised as "removing an employee." `DELETE /team/{id}` instead sets
`is_active = false` and revokes their tokens immediately (`$member->tokens()->delete()`)
— they can't log in anymore, but every sale they ever rang up stays exactly
where it was.

## Test it
```bash
# Add a cashier (owner's token)
curl -X POST http://localhost:8000/api/team \
  -H "Authorization: Bearer $OWNER_TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Ada","email":"ada@example.com","password":"password123","role":"staff"}'

# Try deleting a product as that cashier — should 403
curl -X DELETE http://localhost:8000/api/products/1 -H "Authorization: Bearer $STAFF_TOKEN"

# Update receipt footer / tax rate (owner's token)
curl -X PUT http://localhost:8000/api/business/settings \
  -H "Authorization: Bearer $OWNER_TOKEN" -H "Content-Type: application/json" \
  -d '{"receipt_footer":"Thank you for shopping with us!","tax_rate":7.5}'
```

---

# Setting Up Email & WhatsApp For Real

The code has been ready since Day 5–6 — what's been missing is real
credentials in `.env`. Here's exactly how to get them.

## Email — fastest path: Gmail SMTP (good for testing, low volume)

1. You need a Google Account with 2-Step Verification turned on
   (myaccount.google.com → Security → 2-Step Verification).
2. Once that's on, go to myaccount.google.com → Security → **App Passwords**.
3. Create one for "Mail" / "Other (custom name)" → name it "Ledgerly".
   Google gives you a 16-character password — copy it.
4. In `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=youraddress@gmail.com
   MAIL_PASSWORD=the16characterapppassword
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=youraddress@gmail.com
   MAIL_FROM_NAME="Ledgerly"
   ```
5. Restart `php artisan serve`, then test:
   ```bash
   curl -X POST http://localhost:8000/api/sales/1/receipt/email \
     -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"email":"your-own-email@gmail.com"}'
   ```

**Gmail's limit is ~500 emails/day** on a free account — fine for testing
and even fine for a while at low sales volume, but it's not built for
production email sending (Google will eventually flag automated mail from
a personal account). When you outgrow it, move to Brevo:

## Email — for production: Brevo (free tier: 300 emails/day, built for this)

1. Sign up at brevo.com (free, no card required).
2. Go to **SMTP & API** → **SMTP** tab. Brevo gives you a host, port, login,
   and a separate SMTP key (not your account password).
3. `.env`:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USERNAME=your-brevo-login@...
   MAIL_PASSWORD=your-smtp-key
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="Ledgerly"
   ```
   `MAIL_FROM_ADDRESS` should ideally be on a domain you control (better
   deliverability than a Gmail address as the "from"), but it'll send with
   a Gmail from-address too if that's all you have right now.

## WhatsApp — Meta Cloud API (the real thing, needs a bit more setup)

This is genuinely a multi-step process on Meta's side — the code is
already built to use it the moment you have credentials.

1. Go to **developers.facebook.com** → log in with a Facebook account →
   **My Apps** → **Create App** → choose **"Business"** as the type.
2. In your new app's dashboard, find **WhatsApp** in the left sidebar
   under "Add products" and click **Set up**.
3. Meta gives you a **test phone number** for free immediately — no
   business verification needed to start testing. On the WhatsApp → API
   Setup page, you'll see:
   - A **temporary access token** (valid ~24 hours — fine for testing,
     you'll need a permanent one before relying on this for real)
   - A **Phone number ID** (a long number, not the phone number itself)
4. Under that same page there's a field to add **recipient test numbers**
   — add your own WhatsApp number and verify it with the code Meta sends.
   Meta's test number can only message verified numbers until you go live.
5. Put both values in `.env`:
   ```
   WHATSAPP_TOKEN=the_temporary_or_permanent_token
   WHATSAPP_PHONE_NUMBER_ID=the_phone_number_id
   ```
6. Test:
   ```bash
   curl -X POST http://localhost:8000/api/sales/1/receipt/whatsapp \
     -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"phone":"your_verified_test_number"}'
   ```
   Response `"sent_via": "api"` means it actually sent. `"sent_via": "link"`
   means the API call failed silently and you got the wa.me fallback
   instead — check Meta's dashboard for the specific error (expired token
   is the most common one during testing).

**For a permanent token** (so this doesn't break every 24 hours): Meta
Business Settings → System Users → create a system user → generate a
token for it with `whatsapp_business_messaging` permission, no expiry.
This step requires your Meta Business Account to exist, which usually
means some form of business verification — this is the part that takes
actual days/weeks with Meta, not something to block your launch on.

**Realistic recommendation given your timeline**: ship with the `wa.me`
fallback link (already working, zero setup) for now. It's not "worse" for
a small shop — the cashier taps a button, WhatsApp opens with the message
ready, they hit send themselves. Circle back to the full Cloud API once
you have paying customers and it's worth the verification wait.

---

# v1.5 — Inventory Log, Draft Sales, Barcode Lookup

## New files
```
database/migrations/2024_01_02_000001_create_inventory_logs_table.php
database/migrations/2024_01_02_000002_create_draft_sales_table.php
app/Models/InventoryLog.php
app/Models/DraftSale.php
app/Models/Product.php          ← updated, +inventoryLogs()
app/Models/Business.php          ← updated, +inventoryLogs(), +draftSales()
app/Models/Sale.php              ← updated, +inventoryLogs()
app/Http/Controllers/Api/InventoryLogController.php
app/Http/Controllers/Api/DraftSaleController.php
app/Http/Controllers/Api/ProductController.php   ← updated, logs stock changes + barcode lookup
app/Http/Controllers/Api/SaleController.php      ← updated, logs sale/void_restock movements
app/Http/Requests/InventoryLog/StoreInventoryLogRequest.php
app/Http/Requests/DraftSale/StoreDraftSaleRequest.php
app/Http/Resources/InventoryLogResource.php
app/Http/Resources/DraftSaleResource.php
routes/api.php                   ← overwrite
```

Run `php artisan migrate` (not `migrate:fresh` — these are additive, no
need to nuke your existing data).

## 1. Inventory Movement Log
Every stock change is now recorded automatically — you don't have to do
anything for these to start appearing:
- Creating a product with initial stock → logged as `adjustment`
- Editing a product's quantity directly → logged as `adjustment`
- A sale → logged as `sale` (negative), linked to the invoice
- Voiding a sale → logged as `void_restock` (positive), linked to the invoice

Plus a **manual entry** for things the system can't infer on its own —
stock delivered from a supplier, a customer return handled outside a
formal void, or correcting a count that didn't match a physical stock-take:

| Method | Endpoint | Who | Purpose |
|---|---|---|---|
| GET | /api/inventory-logs?product_id=&type=&per_page= | Anyone | Full history, or one product's history |
| POST | /api/inventory-logs | Admin/Owner | Manual purchase/return/adjustment |

`type: 'sale'` and `type: 'void_restock'` are **not accepted** from
`POST /inventory-logs` — those only ever come from `SaleController`
itself, so nobody can fake a sale-driven stock change through this
endpoint.

```bash
# Supplier delivered 20 more units
curl -X POST http://localhost:8000/api/inventory-logs \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"product_id":1,"type":"purchase","quantity_change":20,"note":"Delivery from Supplier X"}'
```

## 2. Draft Sales
A cashier can save an in-progress cart and come back to it — the "I'll
transfer, hold on" scenario. Deliberately **not** built on the `sales`
table: a draft has no stock effect and no financial reality until it's
actually completed through the normal `POST /sales` flow. It's just a
saved cart (`items` as JSON), so it survives even if you delete a product
that was in someone's paused cart.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | /api/draft-sales | List every paused cart in the business (any cashier can resume any draft) |
| POST | /api/draft-sales | Save a cart |
| GET/PUT/DELETE | /api/draft-sales/{id} | View / edit / discard |

**Resuming a draft is a frontend job, not a backend endpoint** — load the
draft's `items` into the POS cart, let the cashier complete the sale
normally through `POST /sales` (full stock check happens there, since
stock may have changed since the draft was saved), then
`DELETE /draft-sales/{id}` to clean up.

## 3. Barcode Lookup
The `products.barcode` column and search-by-barcode have existed since
Day 1 — what was missing was a fast, exact-match endpoint for a scanner
to hit (fuzzy `LIKE` search works but is the wrong tool for "the scanner
just read this exact code"):

```
GET /api/products/barcode/{barcode}
```
Scoped to the business, 404s cleanly if nothing matches. Registered
*before* `Route::apiResource('products', ...)` in `routes/api.php` so it
takes priority over the `/products/{product}` pattern — otherwise Laravel
would try to look up a product by an ID that happens to be a barcode
string and 404 for the wrong reason.
