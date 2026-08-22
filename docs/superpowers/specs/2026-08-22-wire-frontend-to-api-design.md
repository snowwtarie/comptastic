# Wire the frontend to the real API

Status: approved
Date: 2026-08-22

## Problem

`apps/web` is a standalone Vue 3 + Pinia SPA. All data lives in `src/stores/ledger.js` as hardcoded `SEED_*` arrays, computed client-side. `apps/api` is a working Laravel + Sanctum backend (auth, accounts, transactions, debts, budgets, settings, dashboard summary, savings projection) with 41 passing feature tests, but nothing in the frontend calls it. `LoginView.vue` is a stub that validates locally and routes to `/dashboard` without ever hitting `/api/login` or `/api/register`.

This spec covers replacing the mock store with real API calls end to end: auth, session handling, and all six views (Dashboard, Transactions, Budgets, Comptes, Dettes, Projection).

## Key mismatch: names vs ids

The mock store keys everything by category/account **name** (`transaction.category === 'Alimentation'`, `<option v-for="c in store.categories">`). The API keys by numeric `category_id` / `account_id` and returns amounts as decimals already divided from cents (e.g. `"amount": -64.2`), with writes taking a separate `amount` + `type: 'expense'|'income'` pair that the backend converts to a signed `amount_cents`.

Decision: adopt the API's shape everywhere. The frontend store data becomes id-keyed; no name-to-id translation layer. This touches every view but avoids a class of bugs where a translation layer drifts from reality, and it removes a whole layer of code.

## Architecture

Replace `stores/ledger.js` with one fetch client and one Pinia store per API resource:

```
src/lib/
  api.js              — fetch wrapper: CSRF priming, credentials, JSON parsing, ApiError, central 401 handling
src/stores/
  auth.js             — session user; boot/login/register/logout
  categories.js        — category list (id, name, color_hex, is_income) — fetched once on boot
  accounts.js          — accounts list + create
  transactions.js       — paginated, period/account-scoped list + create/update(reconciled)/delete
  debts.js             — debts list + create
  budgets.js            — per-month category aggregate (budget/spent/pct/status) + update
  settings.js           — income / monthly_savings_contribution / annual_return_rate; show/update
  dashboard.js           — summary bars + category totals, per period (current/previous/year)
  projection.js          — savings history + projection, per horizon
```

Each store holds only what its endpoint returns — no renaming, no derived-on-the-client aggregation that the API already computes server-side (e.g. budget %/status, dashboard bars, projection curve all come pre-computed).

Views compose 1–2 stores each, same as they compose the single `ledgerStore` today:
- `DashboardView` → `dashboard`, `accounts`
- `TransactionsView` → `transactions`, `accounts`, `categories`, `debts` (for the link-to-debt select)
- `BudgetsView` → `budgets`, `settings` (income)
- `ComptesView` → `accounts`
- `DettesView` → `debts`
- `ProjectionView` → `projection`, `settings` (contribution/rate are edited here, not returned by the projection endpoint itself)

## API client (`src/lib/api.js`)

Native `fetch`, no new dependency:

```js
const BASE = import.meta.env.VITE_API_BASE_URL;

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message || 'Request failed');
    this.status = status;
    this.errors = body?.errors ?? null; // Laravel 422 { field: [msg] } shape
  }
}

let csrfPrimed = false;
let onUnauthorized = null;
export function registerUnauthorizedHandler(fn) { onUnauthorized = fn; }

async function primeCsrf() {
  if (csrfPrimed) return;
  await fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
  csrfPrimed = true;
}

function xsrfTokenFromCookie() {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : '';
}

export async function apiFetch(path, { method = 'GET', body } = {}) {
  if (method !== 'GET') await primeCsrf();

  const res = await fetch(`${BASE}${path}`, {
    method,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(method !== 'GET' ? { 'X-XSRF-TOKEN': xsrfTokenFromCookie() } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  if (res.status === 401) {
    onUnauthorized?.();
    throw new ApiError(401, await res.json().catch(() => null));
  }
  if (!res.ok) throw new ApiError(res.status, await res.json().catch(() => null));
  if (res.status === 204) return null;
  return res.json();
}
```

- CSRF cookie is primed once per page load, only before the first mutating call — mirrors the working curl sequence verified manually (register → login → authenticated reads → logout).
- 401 handling is centralized here via `registerUnauthorizedHandler`, not duplicated per store. `auth.js` registers a handler on app boot that clears `user` and redirects to `/login`. Every store's write/read action just lets `ApiError` propagate to the caller.

## Auth store + router guard

```js
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const status = ref('idle'); // idle | loading | ready

  async function boot() {
    status.value = 'loading';
    try { user.value = (await apiFetch('/api/user')).data; }
    catch { user.value = null; }
    status.value = 'ready';
  }
  async function login(email, password) {
    await apiFetch('/api/login', { method: 'POST', body: { email, password } });
    await boot();
  }
  async function register(payload) {
    user.value = (await apiFetch('/api/register', { method: 'POST', body: payload })).data;
  }
  async function logout() {
    await apiFetch('/api/logout', { method: 'POST' });
    user.value = null;
  }

  return { user, status, boot, login, register, logout };
});
```

`registerUnauthorizedHandler(() => { authStore.user = null; router.push('/login'); })` is wired once in `main.js` after both the router and pinia are set up.

`router.beforeEach`:
1. On the very first navigation, `await authStore.boot()` (guarded by `status !== 'idle'` so it only runs once).
2. If target route isn't `/login` and `user` is null → redirect to `/login`.
3. If target route is `/login` and `user` is set → redirect to `/dashboard`.

`LoginView.vue` drops its stub `submit()` and calls `authStore.login(...)` / `authStore.register(...)`, catching `ApiError` and showing `e.message` in the existing red-banner `error` ref (e.g. "Identifiants invalides." from the backend, verbatim).

## Error handling in forms

422 responses map onto per-field errors; everything else shows the existing generic banner:

```js
async function submit() {
  formErrors.value = {};
  try {
    await transactionsStore.create(payload);
    closeModal();
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) formErrors.value = e.errors;
    else generalError.value = 'Une erreur est survenue.';
  }
}
```

Applies to the transaction-create modal, debt-create modal, account-create modal, budget/settings inline edits, and login/register.

## View-by-view changes

- **`categories` store**: replaces `CATEGORIES` (name array) and `CAT_COLORS`/`CAT_COLOR_LIST` (hardcoded hex maps) in `stores/ledger.js`. Fetched once on boot (after auth resolves). Exposes `items`, `byId`, `expense` (non-income, sorted) getters. `color_hex` from the API replaces the hardcoded Tailwind-indigo ramp.
- **`accounts` store**: `items` (list with computed `balance`/`pending_encours` from the API), `byId`, `savings` (type === 'savings') getters, `create(payload)`.
- **`transactions` store**: `items`, `pagination` meta, `fetch({ period, account_id })`, `create(payload)` (handles `mode: simple|installment|recurring` passthrough as today's form already models), `toggleReconciled(id)` (PATCH `reconciled`), `remove(id)`.
- **`debts` store**: `items`, `create(payload)`.
- **`budgets` store**: `rows` (category_id, name, color_hex, budget, spent, pct, status — already merged server-side), `fetch(month)`, `update(categoryId, amount)`.
- **`settings` store**: `income`, `monthlySavingsContribution`, `annualReturnRate`, `fetch()`, `update(payload)`.
- **`dashboard` store**: `bars`, `categories` (category_id + amount, joined against the `categories` store for name/color in the template), `fetch(period)`.
- **`projection` store**: `history`, `projection`, `fetch(horizon)`.

Selects/dropdowns switch from name-valued `<option>`s to id-valued ones (`:value="cat.id"`), and table cells that displayed a stored name directly now look it up via `categoriesStore.byId[id].name` / `accountsStore.byId[id].name`. No visual/UX change — same dropdowns, same columns.

`ProjectionView`'s editable contribution/rate inputs write through `settingsStore.update(...)`, then re-trigger `projectionStore.fetch(horizon)` so the curve reflects the new values (the projection endpoint doesn't return contribution/rate itself — it only consumes them server-side).

`BudgetsView`'s income figure and "over budget" check use `settingsStore.income`, edited the same way (`settingsStore.update`).

`SEED_ACCOUNTS`, `SEED_TRANSACTIONS`, `SEED_DEBTS`, `DEFAULT_BUDGETS`, and `stores/ledger.js` itself are deleted. No mock/demo fallback mode — if the API is unreachable, views show their existing empty/error states rather than falling back to fake data.

## Env config & dev workflow

`apps/web/.env.example` (committed) and `.env` (gitignored, developer-created):
```
VITE_API_BASE_URL=http://localhost:8000
```

Local dev requires both `php artisan serve` (apps/api, :8000) and `npm run dev` (apps/web, :5173) running concurrently — already the configuration `config/cors.php` (`FRONTEND_URL=http://localhost:5173`) and `SANCTUM_STATEFUL_DOMAINS=localhost:5173` expect, and matches what was verified working manually via curl in the prior session (CSRF cookie → register → session-authenticated reads → logout → login → authenticated reads, all 200/204 as expected).

## Testing

No frontend test runner exists in `apps/web` (no Vitest/Playwright in `package.json`); adding one is out of scope here. Verification is manual, via Chrome DevTools MCP against both servers running locally:
1. Register a new user → redirected to `/dashboard`.
2. Create an account, a transaction (simple + installment), a debt.
3. Edit a budget amount and the monthly income; confirm the over/under-budget indicator updates.
4. Edit projection contribution/rate; confirm the chart curve changes.
5. Log out → redirected to `/login`; direct navigation to `/dashboard` while logged out also redirects.
6. Log back in as the seeded demo user (`demo@comptastic.test` / `password`) and confirm dashboard/accounts/transactions match the seeded data already verified via curl.

Backend regression: `php artisan test` must stay green (41/41) since no backend files change in this work.

## Out of scope

- Adding a frontend test runner.
- "Forgot password" flow (the link exists in `LoginView` today as a dead `<a href="#">`; stays that way).
- Any backend changes — this is a frontend-only wiring task against the existing API contract.
