
---

# Multi-Business + Branches — the account/business/branch restructuring

This is the biggest architectural change since Day 1. Read this whole
section before touching anything — it changes what "your business" means
everywhere in the app.

## What changed, conceptually
**Before:** one user = one business. `users.business_id` and `users.role`
were columns directly on the user.

**Now:** a user account is independent of any business. They can own
several, or be an invited admin/staff on others they don't own at all.
Membership — including role — lives in a new `business_members` table,
one row per (user, business) pair. A business can also have branches, and
a staff member's membership can be confined to exactly one.

```
User
 ├── owns Business A (as owner) ── has Branch 1, Branch 2
 ├── member of Business B (as admin, all branches)
 └── member of Business C (as staff, confined to Branch 1 only)
```

## 1. New/changed migrations (run in this order — already numbered correctly)
```
2024_01_06_000001_create_branches_table.php
2024_01_06_000002_add_owner_user_id_to_businesses_table.php   ← backfills from old owner
2024_01_06_000003_create_business_members_table.php            ← backfills from users.business_id/role
2024_01_06_000004_add_branch_id_to_sales_expenses_draft_sales_table.php
2024_01_06_000005_drop_business_id_and_role_from_users_table.php
```
```bash
php artisan migrate
```
The backfill migrations are safe to run against your existing data —
they copy the old `users.business_id`/`role` into `business_members`
*before* those columns get dropped, so nobody loses their existing
business/role in the process.

## 2. The mechanism: X-Business-Id header
Since a user can belong to several businesses, the API can no longer
infer "which business" from the logged-in user alone. Every tenant
request must now include:
```
X-Business-Id: 3
```
A new middleware (still called `has.business` — same alias, same routes,
totally different internals) reads that header, checks the user actually
has an **active** `business_members` row for it, and — if so — resolves
the `Business` and `BusinessMember` onto the request. Two new methods are
available everywhere as a result, via a `Request` macro:
```php
$request->business()      // the resolved Business model
$request->membership()    // the resolved BusinessMember (role, branch_id, status)
```
This works identically inside controllers *and* FormRequest classes
(`$this->business()`), since `FormRequest` extends `Request` — one macro,
registered once in `AppServiceProvider`, no per-class helper needed.

**No `X-Business-Id` header → 422 `NO_BUSINESS_SELECTED`.**
**Header present but not a member (or membership deactivated) → 403 `NOT_A_MEMBER`.**

## 3. New endpoints

| Method | Endpoint | Auth needed | Purpose |
|---|---|---|---|
| GET | /api/my/businesses | Just logged in | Every business you belong to, in any role — **this is what powers the business switcher**, and deliberately sits outside the `X-Business-Id` requirement, since you need this list before you can pick one |
| POST | /api/business | Logged in + verified | Create a business — no more "only one per account" limit |
| GET/PUT | /api/business | + X-Business-Id | View/update the *currently selected* business |
| GET/POST | /api/branches | + X-Business-Id | List / create branches (create is owner-only) |
| GET/PUT | /api/branches/{id} | + X-Business-Id | View / update one branch (update is owner-only) |

`/team` endpoints work the same as before from the outside, but now
operate on `business_members` rows, not `users` directly — see below.

## 4. Team invites now handle "this person already has an account"
`POST /team` used to always create a brand-new `User`. Now it first
checks whether the email already belongs to someone (they might run
their own separate business on Ledgerly, or already work at one of your
other businesses):
- **Email doesn't exist yet** → creates the account, using the
  name/password from the form, then attaches them.
- **Email already exists** → attaches that existing account. The
  name/password submitted in the form are simply ignored for that person
  — they keep using their existing login.

"Removing" someone (`DELETE /team/{id}`) deactivates *that one
membership* — not the user account, which might still be active on other
businesses. Their sales/expense history stays attributed to them either
way.

## 5. Branches are for sales/expenses attribution, not per-branch stock
Deliberate scope decision: **the product catalog is shared across every
branch of a business.** A branch tags *where a transaction happened*
(`sales.branch_id`, `expenses.branch_id`), not *how much stock sits in
that specific location*. If you later need "Branch A has 12 units, Branch
B has 3" as a distinct number per branch, that's a real follow-up
(product_stocks pivot table), not something this change includes.

Staff confined to one branch (their `business_members.branch_id` is set)
are automatically scoped to it everywhere that matters:
- Creating a sale/expense → their branch is assigned automatically, no
  need to pass `branch_id` at all
- Listing sales/expenses, and every report endpoint (dashboard, daily,
  monthly, profit) → force-filtered to their branch, they can't see
  another branch's numbers by passing a different `branch_id`

Owner/admin have `branch_id = null` on their membership (every branch),
and can optionally pass `?branch_id=` to filter reports/lists down to
one, or `branch_id` in the request body to tag a sale/expense to a
specific branch when creating it.

## 6. What every existing endpoint needed (and got)
Every controller that used to read `$request->user()->business_id` now
reads `$request->business()->id` instead — 33 occurrences across 23
files, all verified consistent. `authorizeBusiness()` (the base
Controller helper every show/update/destroy calls) now checks against
`request()->business()?->id` the same way. Role checks
(`is.owner`/`not.staff` middleware) now check `$request->membership()`
instead of the user's own (now-removed) `role` column.

## Verified
Every PHP file in `app/` and `database/migrations/` passes `php -l`
(syntax lint) — genuinely checked, not just eyeballed, since PHP is now
installed in this environment. That confirms there are no typos or
malformed code; it does **not** confirm every piece of business logic is
correct, since I can't run Laravel itself here (no Composer/Packagist
access in this sandbox). Test the real flows yourself before trusting
this in production:
1. Register → verify → create a business → confirm you land in it
2. Create a second business with the same account → confirm `/my/businesses` shows both
3. Create a branch, add a staff member confined to it → confirm they only see that branch's sales/reports
4. Add an existing Ledgerly user (one you already registered elsewhere) as a team member on a different business → confirm no duplicate account gets created

## What's not built yet (next phase)
This is backend-only. The frontend still assumes one business per login
everywhere — the auth store, every API call, the whole routing structure.
None of it sends `X-Business-Id` yet, so nothing on the frontend will
work against this until that's rebuilt. That's the next piece: a business
switcher, branch management screens, and rewiring the API client to
attach the header on every request.
