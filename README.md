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

---

# Returns + Supplier Management

## New files
```
database/migrations/2024_01_03_000001_create_suppliers_table.php
database/migrations/2024_01_03_000002_add_supplier_id_to_products_table.php
database/migrations/2024_01_03_000003_create_returns_table.php
database/migrations/2024_01_03_000004_create_return_items_table.php
database/migrations/2024_01_03_000005_add_supplier_and_return_to_inventory_logs_table.php
app/Models/Supplier.php
app/Models/Return_.php                      ← named Return_, "Return" is a reserved PHP word
app/Models/ReturnItem.php
app/Models/Product.php                       ← updated, +supplier()
app/Models/InventoryLog.php                  ← updated, +supplier(), +return()
app/Models/Sale.php                          ← updated, +returns()
app/Models/Business.php                      ← updated, +suppliers(), +returns()
app/Http/Controllers/Api/SupplierController.php
app/Http/Controllers/Api/ReturnController.php
app/Http/Controllers/Api/ProductController.php    ← updated, supplier eager-loaded
app/Http/Controllers/Api/InventoryLogController.php ← updated, +supplier_id support
app/Http/Requests/Supplier/{Store,Update}SupplierRequest.php
app/Http/Requests/Returns/StoreReturnRequest.php
app/Http/Resources/SupplierResource.php
app/Http/Resources/ReturnResource.php
app/Http/Resources/ReturnItemResource.php
app/Http/Resources/ProductResource.php       ← updated, +supplier
app/Http/Resources/InventoryLogResource.php  ← updated, +supplier
routes/api.php                                ← overwrite
```

Run `php artisan migrate` — these are additive, no data loss.

## Why the model is `Return_`, not `Return`
`return` is a reserved keyword in PHP — `class Return extends Model` is a
parse error. The class is `Return_` (trailing underscore) but the actual
database table is still plainly `returns` (set via `protected $table` on
the model), so nothing about routes, JSON responses, or the table name
looks unusual — this is purely an internal naming workaround.

## Supplier Management
Straightforward CRUD, same shape as Categories:

| Method | Endpoint | Purpose |
|---|---|---|
| GET/POST | /api/suppliers | List / create |
| GET/PUT | /api/suppliers/{id} | View / update |
| DELETE | /api/suppliers/{id} | Admin/owner only — products keep existing (`supplier_id` → null) |

Products can now optionally have a `supplier_id`, and a manual `purchase`
inventory log entry can optionally note which supplier delivered it —
neither is required, both are there for when you want to start asking
"who do I buy this from" instead of just "how much do I have."

## Returns — how it actually works
This is the one genuinely non-trivial piece: **a return never edits the
original sale.** Your invoice for July 12th still shows exactly what was
sold that day, even if half of it comes back on July 15th. The return is
its own record — `return_number` (`RET-000001`, sequential, same pattern
as invoice numbers), tied to the original `sale_id`, with its own line
items and its own refund total. This mirrors how a paper business
actually handles it: an invoice plus a separate credit note, not an
edited invoice.

| Method | Endpoint | Who | Purpose |
|---|---|---|---|
| GET | /api/returns?sale_id=&customer_id= | Anyone | List returns |
| GET | /api/returns/{id} | Anyone | View one return |
| POST | /api/returns | Admin/Owner | Process a return |

**What `POST /returns` actually validates:**
- The sale must belong to your business and be `status: completed` — you
  can't file a return against a sale that's already been voided (voiding
  already reversed everything).
- Each returned line item is checked against **how much of that specific
  sale_item hasn't already been returned** — so two partial returns on the
  same original sale can't together exceed what was actually sold. Try to
  return 3 units of something when only 1 remains un-returned (2 were
  already returned last week), and it fails with the exact remaining
  count in the error message.
- Stock is restocked and logged (`inventory_logs`, type `return`) exactly
  like a void does, using the same `lockForUpdate()` safety.
- If the sale had a customer attached, their `total_purchases` is reduced
  by the refund amount — same reasoning as void.

```bash
# Return 1 unit of sale_item #5 from sale #12
curl -X POST http://localhost:8000/api/returns \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"sale_id":12,"items":[{"sale_item_id":5,"quantity":1}],"reason":"Wrong size"}'
```

**Why returns are admin/owner only, same as void:** processing a return
refunds money and puts stock back — exactly the "reversing an action that
already happened" category that staff shouldn't do unsupervised, same
line drawn for delete and void elsewhere in this file.

---

# SaaS Admin Panel — Trials, Subscriptions, Manual Billing

This is a different kind of feature from everything before it: it's not
part of the product any business uses — it's **your** internal tool for
running Ledgerly as a business. Read this whole section before using it;
it gates whether your customers can get in.

## 1. Register two more middleware aliases
Add to `bootstrap/app.php`, alongside the three already there:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'has.business' => \App\Http\Middleware\EnsureBusinessExists::class,
        'is.owner' => \App\Http\Middleware\EnsureIsOwner::class,
        'not.staff' => \App\Http\Middleware\EnsureNotStaff::class,
        'is.super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
    ]);
})
```
Restart `php artisan serve` after saving.

## 2. Run the new migrations + seed some plans
```bash
php artisan migrate
php artisan db:seed --class=PlanSeeder
```
This creates three placeholder plans (Starter/Growth/Pro at ₦5,000/₦12,000/₦25,000)
so the admin panel has something to work with immediately. **Edit these
prices** — via `PUT /admin/plans/{id}` once the frontend is built, or
directly in the `plans` table — they're just a starting point, not a
recommendation.

## 3. Make yourself a super admin
There's deliberately no public signup for this — it's not something
customers should ever be able to reach. Register a normal account through
the regular `/register` flow (or use your existing one), then:
```bash
php artisan tinker
>>> $u = App\Models\User::where('email', 'you@example.com')->first();
>>> $u->is_super_admin = true;
>>> $u->save();
```
Log out and back in (or hit `/me` again) — your token now passes
`is.super_admin` on every `/admin/*` route.

## New files
```
database/migrations/2024_01_04_000001_add_is_super_admin_to_users_table.php
database/migrations/2024_01_04_000002_create_plans_table.php
database/migrations/2024_01_04_000003_create_subscriptions_table.php
database/migrations/2024_01_04_000004_create_payments_table.php
database/seeders/PlanSeeder.php
app/Models/Plan.php
app/Models/Subscription.php
app/Models/Payment.php
app/Models/User.php                          ← updated, +is_super_admin
app/Models/Business.php                      ← updated, +subscription(), +payments()
app/Http/Middleware/EnsureSuperAdmin.php
app/Http/Middleware/EnsureSubscriptionActive.php
app/Http/Controllers/Api/Admin/AdminDashboardController.php
app/Http/Controllers/Api/Admin/AdminBusinessController.php
app/Http/Controllers/Api/Admin/AdminPaymentController.php
app/Http/Controllers/Api/Admin/AdminPlanController.php
app/Http/Controllers/Api/BusinessController.php   ← updated, creates a trial subscription on signup
app/Http/Controllers/Api/AuthController.php       ← updated, eager-loads subscription
app/Http/Controllers/Api/ProfileController.php    ← updated, eager-loads subscription
app/Http/Requests/Admin/*.php
app/Http/Resources/Admin/AdminBusinessResource.php
app/Http/Resources/Admin/AdminBusinessDetailResource.php
app/Http/Resources/SubscriptionResource.php
app/Http/Resources/BusinessResource.php      ← updated, +subscription
routes/api.php                                ← overwrite
```

## The core design decision: super admin is not a business role
`is_super_admin` is a flag on `users`, completely separate from `role`
(owner/admin/staff). A super admin **doesn't need to belong to any
business** — `business_id` stays `null` for that account. This matters
because it means the admin panel genuinely sits above the multi-tenant
system rather than being "one more permission level" bolted onto it — the
`/admin/*` routes don't go through `has.business` or
`subscription.active` at all, since neither concept applies to you
managing the platform.

## How trials and subscriptions actually work

**On signup:** `POST /business` now automatically creates a `subscriptions`
row — `status: trialing`, `trial_ends_at` = 14 days out, no plan chosen
yet. Nothing else to configure; every new business gets this for free.

**What blocks access:** `EnsureSubscriptionActive` runs on every tenant
route (right after `has.business`). It calls `Subscription::isUsable()`,
which checks **dates, not just the status string**:
- `trialing` → usable only if `trial_ends_at` hasn't passed
- `active` → usable only if `expires_at` hasn't passed
- `suspended` / `cancelled` / `expired` → never usable

This means a trial correctly locks out the moment it's actually past its
end date, **even though nothing runs on a schedule to flip the status
field** — there's no cron job here, and deliberately so: for your first
10–20 customers, checking a date on each request is enough; you don't
need a job scheduler for that. If a blocked request comes in, the
frontend gets a `402 Payment Required` with a clear message and the
subscription's actual status, so it can show a "your trial ended, here's
how to pay" screen rather than a confusing generic error.

**How you activate someone (Phase 1 manual flow, matching what you
described):**
1. They sign up → automatic 14-day trial, no action from you needed.
2. They pay you directly (bank transfer, Paystack payment link — however
   you're currently collecting money, unchanged by any of this).
3. You go to `POST /admin/businesses/{id}/activate` with a `plan_id` and
   `duration_days` (e.g., 30 for monthly, 365 for annual) → their
   subscription becomes `active` with a real `expires_at`.
4. Optionally, `POST /admin/businesses/{id}/payments` logs the actual
   payment for your own records (amount, reference, notes) — this can
   activate the subscription in the same call if you pass `plan_id` and
   `extend_days`, or you can log the payment and activate separately.

## Full endpoint reference

| Method | Endpoint | Purpose |
|---|---|---|
| GET | /api/admin/dashboard | Total businesses, MRR, counts by real status, recent signups/payments |
| GET | /api/admin/businesses?search=&status=&per_page= | List every business, with subscription + usage at a glance |
| GET | /api/admin/businesses/{id} | Full detail: owner, subscription, usage, payment history |
| POST | /api/admin/businesses/{id}/activate | `{plan_id, duration_days, payment_provider?}` — mark as paid |
| POST | /api/admin/businesses/{id}/extend-trial | `{days}` — give someone more trial time |
| POST | /api/admin/businesses/{id}/suspend | Lock them out immediately (abuse, chargeback, etc.) |
| POST | /api/admin/businesses/{id}/reactivate | Undo a suspension |
| POST | /api/admin/businesses/{id}/payments | `{amount, provider_reference?, paid_at?, notes?, plan_id?, extend_days?}` — log a manual payment |
| GET | /api/admin/payments?business_id=&per_page= | Payment history, across all businesses or filtered |
| GET/POST/PUT/DELETE | /api/admin/plans | Manage subscription plans |

## Why MRR is calculated the way it is
`mrr` in `/admin/dashboard` sums `plan.price` for every subscription that
is currently `active` **and** currently usable (not expired). This treats
`plan.price` as a flat monthly figure — if you ever sell annual plans at
a discount, this number will overstate true monthly revenue unless you
either store a separate monthly-equivalent price or normalize
`duration_days` into the calculation. Fine for now with three simple
plans; worth revisiting if pricing gets more complex.

## What's deliberately NOT here (matches the review's own Phase 1/2/3 split)
- No Paystack integration — `payment_provider` and `provider_reference`
  fields exist and are ready for it, but every payment right now is you
  manually recording that money arrived.
- No automatic renewal reminders or dunning emails.
- No self-service upgrade/downgrade from the tenant side.
All of that is explicitly Phase 2/3 territory. Building it now would be
solving a problem you don't have any paying customers to justify solving
yet.

---

# Admin Panel (Platform Owner) — Trials, Subscriptions, Manual Billing

This is the layer for **you**, running Ledgerly as a SaaS — not something
any business owner ever sees. It sits entirely outside the `business_id`
scoping that governs everything else in this app.

## 1. Register two more middleware aliases
`bootstrap/app.php` should now have **all five** aliases:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'has.business' => \App\Http\Middleware\EnsureBusinessExists::class,
        'is.owner' => \App\Http\Middleware\EnsureIsOwner::class,
        'not.staff' => \App\Http\Middleware\EnsureNotStaff::class,
        'is.super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
    ]);
})
```
Restart `php artisan serve` after saving.

## 2. Migrate + seed
```bash
php artisan migrate
php artisan db:seed --class=PlanSeeder
```
This creates three starter plans (Starter/Growth/Pro — edit the prices in
`database/seeders/PlanSeeder.php` to match what you actually want to
charge; they're placeholders). If your `database/seeders/DatabaseSeeder.php`
doesn't already call `PlanSeeder::class` in its `run()` method, add it
there so `php artisan db:seed` picks it up automatically going forward.

## 3. How you become a super admin
There's deliberately no signup flow for this — it's not something the
public should ever reach. Register a normal account through the app like
anyone else, then flip the flag yourself:
```bash
php artisan tinker
>>> $u = App\Models\User::where('email', 'your-email@example.com')->first();
>>> $u->is_super_admin = true;
>>> $u->save();
```
That account can now hit every `/admin/*` endpoint — with or without a
business of its own.

## New files
```
database/migrations/2024_01_04_000001_add_is_super_admin_to_users_table.php
database/migrations/2024_01_04_000002_create_plans_table.php
database/migrations/2024_01_04_000003_create_subscriptions_table.php
database/migrations/2024_01_04_000004_create_payments_table.php
app/Models/{Plan,Subscription,Payment}.php
app/Models/User.php                          ← updated, +is_super_admin
app/Models/Business.php                      ← updated, +subscription(), +payments()
app/Http/Middleware/EnsureSuperAdmin.php
app/Http/Middleware/EnsureSubscriptionActive.php
app/Http/Controllers/Api/Admin/AdminBusinessController.php
app/Http/Controllers/Api/Admin/AdminDashboardController.php
app/Http/Controllers/Api/Admin/AdminPaymentController.php
app/Http/Controllers/Api/Admin/AdminPlanController.php
app/Http/Requests/Admin/{ActivateSubscription,ExtendTrial,RecordPayment,SavePlan}Request.php
app/Http/Resources/Admin/{AdminBusiness,AdminBusinessDetail}Resource.php
app/Http/Resources/SubscriptionResource.php
app/Http/Resources/UserResource.php          ← updated, +is_super_admin (frontend needs this to gate the admin UI)
app/Http/Controllers/Api/BusinessController.php ← updated, creates a 14-day trial subscription automatically
database/seeders/PlanSeeder.php
routes/api.php                                ← overwrite
```

## The core design decision: dates gate access, billing stays manual
The review that prompted this drew a clear Phase 1/2/3 line, and it's
worth being precise about where this build actually sits on it:

- **Collecting money is still entirely manual** — no Paystack, no card
  processing, nothing automated. You get paid however you already get
  paid (bank transfer, Paystack payment link you send by hand) and then
  tell the system about it.
- **Enforcing access is automatic**, and deliberately so. Every tenant
  route now sits behind `subscription.active`, which checks
  `Subscription::isUsable()` — a real method that compares `trial_ends_at`
  /`expires_at` against today, not just a status label someone has to
  remember to update. A trial that hits day 15 locks out on its own; you
  don't have to notice and flip a switch.

That's not scope creep on "keep billing manual" — reading dates and
comparing them isn't billing infrastructure, it's the bare minimum for
the data you're already storing to mean something. The complex stuff
(webhooks, retries, card updates, coupons) is still correctly deferred to
Phase 3.

## What happens when a trial expires
Every tenant request gets a 402 with a clear body:
```json
{
  "message": "This business's trial or subscription is no longer active. Contact support to continue.",
  "code": "SUBSCRIPTION_INACTIVE",
  "status": "trialing"
}
```
The frontend can catch this specific code and show a "your trial has
ended" screen instead of a generic error — worth wiring that up rather
than letting it surface as an unhandled failure.

## Admin endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | /api/admin/dashboard | MRR, subscription counts by state, recent signups/payments |
| GET | /api/admin/businesses?search=&status=&per_page= | Every business on the platform |
| GET | /api/admin/businesses/{id} | Full detail: owner, subscription, usage, payment history |
| POST | /api/admin/businesses/{id}/activate | `{plan_id, duration_days, payment_provider?}` — "they paid, mark it active" |
| POST | /api/admin/businesses/{id}/extend-trial | `{days}` — give someone more runway |
| POST | /api/admin/businesses/{id}/suspend | Lock out immediately (abuse, chargeback, whatever) |
| POST | /api/admin/businesses/{id}/reactivate | Undo a suspension — restores trial or active based on dates already on file |
| GET | /api/admin/payments?business_id=&per_page= | Every recorded payment, across all businesses |
| POST | /api/admin/businesses/{id}/payments | Record a manual payment; optionally activates/extends the subscription in the same call via `plan_id`/`extend_days` |
| GET/POST/PUT/DELETE | /api/admin/plans | Manage what businesses can subscribe to |

## Test the whole lifecycle
```bash
ADMIN_TOKEN="..."  # your super-admin token

# See who's on the platform
curl http://localhost:8000/api/admin/businesses -H "Authorization: Bearer $ADMIN_TOKEN"

# Someone paid — activate them on the Growth plan for 30 days
curl -X POST http://localhost:8000/api/admin/businesses/1/activate \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"plan_id":2,"duration_days":30,"payment_provider":"manual"}'

# Or: record the payment and activate in the same step
curl -X POST http://localhost:8000/api/admin/businesses/1/payments \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"amount":12000,"provider_reference":"bank-transfer-july","plan_id":2,"extend_days":30}'

# Someone's asking for more time to decide
curl -X POST http://localhost:8000/api/admin/businesses/1/extend-trial \
  -H "Authorization: Bearer $ADMIN_TOKEN" -H "Content-Type: application/json" \
  -d '{"days":7}'
```

## Next
The admin frontend — a separate section of the same React app (not a new
project), gated by `user.is_super_admin`, since this is your internal
tool, not something worth standing up infrastructure for separately.

---

# Email Verification (OTP)

## 1. Register one more middleware alias
`bootstrap/app.php` now needs **six**:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'has.business' => \App\Http\Middleware\EnsureBusinessExists::class,
        'is.owner' => \App\Http\Middleware\EnsureIsOwner::class,
        'not.staff' => \App\Http\Middleware\EnsureNotStaff::class,
        'is.super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
        'subscription.active' => \App\Http\Middleware\EnsureSubscriptionActive::class,
        'email.verified' => \App\Http\Middleware\EnsureEmailVerified::class,
    ]);
})
```
Restart `php artisan serve` after saving.

## 2. Migrate
```bash
php artisan migrate
```

## New files
```
database/migrations/2024_01_05_000001_create_email_otps_table.php
app/Models/EmailOtp.php
app/Services/OtpService.php
app/Mail/OtpMail.php
resources/views/emails/otp.blade.php
app/Http/Controllers/Api/EmailVerificationController.php
app/Http/Controllers/Api/AuthController.php    ← updated, sends the first code on register
app/Http/Middleware/EnsureEmailVerified.php
app/Http/Requests/Auth/VerifyEmailRequest.php
app/Http/Resources/UserResource.php            ← updated, +email_verified_at
routes/api.php                                  ← updated
```

## The flow, exactly as specified
```
Register → account created, token issued, OTP emailed
   → POST /verify-email (6-digit code)
   → email_verified_at set
   → POST /business now allowed (was 403 before this)
   → normal tenant flow continues
```

## Endpoints

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| POST | /api/register | No | Creates the account **and** sends the first OTP in the same request |
| POST | /api/verify-email | Yes | `{otp}` — 6 digits |
| POST | /api/resend-otp | Yes | Invalidates the old code, issues and emails a new one |
| POST | /api/business | Yes | Now returns **403 `EMAIL_NOT_VERIFIED`** if the email isn't verified yet |

## Security, matching every rule from the spec
- **6 digits**, generated with `random_int()` (cryptographically secure, not `rand()`)
- **Expires in 10 minutes** — checked via `EmailOtp::isExpired()`, not just trusted client-side
- **Max 5 attempts** — the 6th submission is rejected before it's even compared, telling the user to request a new code
- **60-second resend cooldown** — `OtpService::secondsUntilResendAllowed()` checks the previous code's `created_at`, server-side, so it can't be bypassed by refreshing the page
- **A resend always issues a new code** and deletes the old unverified one — the old code stops working the moment you ask for a new one, it's not still valid alongside it
- **Never stored in plain text** — `EmailOtp.otp` uses the same `'hashed'` cast the `User` model uses for passwords. Even a full database dump doesn't reveal anyone's active code.

## Why register still returns a token
The flow diagram treats "Account Created" as a distinct step before verification — which means the user needs to be authenticated to call `/verify-email` and `/resend-otp` (both are `auth:sanctum` routes, not public). Register issuing a token immediately, before verification, is what makes that possible. What it does *not* do is let that token create a business — `/business` is the one route with `email.verified` on it.

## Test it
```bash
# Register — check storage/logs/laravel.log or your inbox for the code
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Miracle","email":"you@example.com","password":"password123","password_confirmation":"password123"}'

TOKEN="..." # from the response above

# Try creating a business before verifying — should 403
curl -X POST http://localhost:8000/api/business \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Test Shop"}'

# Verify with the code from your email
curl -X POST http://localhost:8000/api/verify-email \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"otp":"482913"}'

# Now it works
curl -X POST http://localhost:8000/api/business \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Test Shop"}'
```
