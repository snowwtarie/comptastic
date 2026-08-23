# Wire Frontend to Real API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `apps/web`'s hardcoded mock Pinia store with real calls to the `apps/api` Laravel backend — auth, accounts, transactions, debts, budgets, settings, dashboard, and savings projection — across all six views.

**Architecture:** One thin `fetch` wrapper (`src/lib/api.js`) handling Sanctum's CSRF-cookie/session dance, one Pinia store per API resource (mirroring the backend's own resource boundaries), and a router guard driven by an `auth` store. Views keep their existing template structure; only the data source underneath changes from name-keyed mock arrays to id-keyed API responses.

**Tech Stack:** Vue 3 (`<script setup>`), Pinia, Vue Router, native `fetch` — no new dependencies.

**Reference spec:** `docs/superpowers/specs/2026-08-22-wire-frontend-to-api-design.md`

---

## Before you start

Two servers must run concurrently for manual verification steps in this plan:

```bash
# Terminal 1 — from apps/api
php artisan serve --port=8000

# Terminal 2 — from apps/web
npm run dev
```

The demo user is `demo@comptastic.test` / `password` (seeded by `php artisan migrate:fresh --seed`, which only runs in `local`/`testing` per the environment guard already in place).

---

## Task 1: Env config for the API base URL

**Files:**
- Create: `apps/web/.env.example`
- Create: `apps/web/.env`
- Modify: `apps/web/.gitignore`

- [ ] **Step 1: Add `.env` to the web app's gitignore**

`apps/web/.gitignore` currently reads:
```
node_modules
dist
dist-ssr
*.local
.DS_Store
bun-debug.log*
```

Add a line so local env files aren't committed:
```
node_modules
dist
dist-ssr
*.local
.DS_Store
bun-debug.log*
.env
```

- [ ] **Step 2: Create the committed example file**

`apps/web/.env.example`:
```
VITE_API_BASE_URL=http://localhost:8000
```

- [ ] **Step 3: Create the local `.env` (not committed)**

`apps/web/.env`:
```
VITE_API_BASE_URL=http://localhost:8000
```

- [ ] **Step 4: Commit**

```bash
cd apps/web
git add .env.example .gitignore
git commit -m "chore(web): add VITE_API_BASE_URL config"
```

(`.env` itself is gitignored and won't be staged — verify with `git status` that only `.env.example` and `.gitignore` show as changed.)

---

## Task 2: API client (`src/lib/api.js`)

**Files:**
- Create: `apps/web/src/lib/api.js`

This is the foundation every store depends on. It primes Sanctum's CSRF cookie before mutating requests, sends `credentials: 'include'` on every request, and centralizes 401 handling via a single registered callback (Task 3 wires the callback).

- [ ] **Step 1: Write the client**

```js
// apps/web/src/lib/api.js
const BASE = import.meta.env.VITE_API_BASE_URL;

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message || 'Request failed');
    this.status = status;
    this.errors = body?.errors ?? null; // Laravel 422 validation shape: { field: [msg, ...] }
  }
}

let csrfPrimed = false;
let onUnauthorized = null;

export function registerUnauthorizedHandler(fn) {
  onUnauthorized = fn;
}

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
    const parsedBody = await res.json().catch(() => null);
    onUnauthorized?.();
    throw new ApiError(401, parsedBody);
  }
  if (!res.ok) {
    throw new ApiError(res.status, await res.json().catch(() => null));
  }
  if (res.status === 204) return null;
  return res.json();
}
```

- [ ] **Step 2: Verify it loads without error**

Run: `cd apps/web && node --input-type=module -e "import('./src/lib/api.js').then(() => console.log('OK'))"`
Expected: `OK` (the `import.meta.env` reference only resolves at Vite build/dev time, but this smoke-checks there's no syntax error).

Note: full verification of `apiFetch` happens in Task 5 once the auth store and LoginView call it against a real running server — there's no test runner in this project (see spec's Testing section), so this task has no isolated automated test.

- [ ] **Step 3: Commit**

```bash
git add apps/web/src/lib/api.js
git commit -m "feat(web): add API fetch client with CSRF priming and 401 handling"
```

---

## Task 3: Auth store (`src/stores/auth.js`)

**Files:**
- Create: `apps/web/src/stores/auth.js`

- [ ] **Step 1: Write the store**

```js
// apps/web/src/stores/auth.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null); // { id, name, email } | null
  const status = ref('idle'); // 'idle' | 'loading' | 'ready'

  async function boot() {
    if (status.value !== 'idle') return;
    status.value = 'loading';
    try {
      const res = await apiFetch('/api/user');
      user.value = res.data;
    } catch {
      user.value = null;
    }
    status.value = 'ready';
  }

  async function login(email, password) {
    await apiFetch('/api/login', { method: 'POST', body: { email, password } });
    const res = await apiFetch('/api/user');
    user.value = res.data;
  }

  async function register({ name, email, password }) {
    const res = await apiFetch('/api/register', { method: 'POST', body: { name, email, password } });
    user.value = res.data;
  }

  async function logout() {
    await apiFetch('/api/logout', { method: 'POST' });
    user.value = null;
  }

  function clear() {
    user.value = null;
  }

  return { user, status, boot, login, register, logout, clear };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/auth.js
git commit -m "feat(web): add auth store backed by Sanctum session endpoints"
```

---

## Task 4: Wire the 401 handler and router guard

**Files:**
- Modify: `apps/web/src/main.js`
- Modify: `apps/web/src/router/index.js`

- [ ] **Step 1: Update `main.js` to register the 401 handler after pinia + router are installed**

Current `apps/web/src/main.js`:
```js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import './style.css';
import App from './App.vue';
import router from './router';

createApp(App).use(createPinia()).use(router).mount('#app');
```

Replace with:
```js
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import './style.css';
import App from './App.vue';
import router from './router';
import { registerUnauthorizedHandler } from './lib/api';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
app.use(createPinia());
app.use(router);

const authStore = useAuthStore();
registerUnauthorizedHandler(() => {
  authStore.clear();
  if (router.currentRoute.value.name !== 'login') {
    router.push({ name: 'login' });
  }
});

app.mount('#app');
```

- [ ] **Step 2: Add the navigation guard**

Current `apps/web/src/router/index.js`:
```js
import { createRouter, createWebHistory } from 'vue-router';
import AppShell from '../components/AppShell.vue';
import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import TransactionsView from '../views/TransactionsView.vue';
import BudgetsView from '../views/BudgetsView.vue';
import ComptesView from '../views/ComptesView.vue';
import DettesView from '../views/DettesView.vue';
import ProjectionView from '../views/ProjectionView.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView },
    {
      path: '/',
      component: AppShell,
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: DashboardView },
        { path: 'transactions', name: 'transactions', component: TransactionsView },
        { path: 'budgets', name: 'budgets', component: BudgetsView },
        { path: 'comptes', name: 'comptes', component: ComptesView },
        { path: 'dettes', name: 'dettes', component: DettesView },
        { path: 'projection', name: 'projection', component: ProjectionView },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/login' },
  ],
});

export default router;
```

Replace with (adds the `beforeEach` guard; route table unchanged):
```js
import { createRouter, createWebHistory } from 'vue-router';
import AppShell from '../components/AppShell.vue';
import LoginView from '../views/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import TransactionsView from '../views/TransactionsView.vue';
import BudgetsView from '../views/BudgetsView.vue';
import ComptesView from '../views/ComptesView.vue';
import DettesView from '../views/DettesView.vue';
import ProjectionView from '../views/ProjectionView.vue';
import { useAuthStore } from '../stores/auth';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView },
    {
      path: '/',
      component: AppShell,
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: DashboardView },
        { path: 'transactions', name: 'transactions', component: TransactionsView },
        { path: 'budgets', name: 'budgets', component: BudgetsView },
        { path: 'comptes', name: 'comptes', component: ComptesView },
        { path: 'dettes', name: 'dettes', component: DettesView },
        { path: 'projection', name: 'projection', component: ProjectionView },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/login' },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (auth.status === 'idle') {
    await auth.boot();
  }
  if (to.name !== 'login' && !auth.user) {
    return { name: 'login' };
  }
  if (to.name === 'login' && auth.user) {
    return { name: 'dashboard' };
  }
  return true;
});

export default router;
```

- [ ] **Step 3: Manual verification**

With both servers running (see "Before you start"), open `http://localhost:5173` in a browser. Expected: redirected to `/login` (no user session yet). Check the browser network tab: a `GET /api/user` request fires once and returns 401 — this is expected (no session), not an error to fix.

- [ ] **Step 4: Commit**

```bash
git add apps/web/src/main.js apps/web/src/router/index.js
git commit -m "feat(web): add auth-aware router guard"
```

---

## Task 5: Wire `LoginView.vue` to real auth

**Files:**
- Modify: `apps/web/src/views/LoginView.vue`

- [ ] **Step 1: Replace the script block**

Current script (lines 1–53):
```js
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useIsMobile } from '../lib/useIsMobile';

const router = useRouter();
const isMobile = useIsMobile();

const mode = ref('login');
const form = reactive({ name: '', email: '', password: '', confirmPassword: '' });
const error = ref('');

const isLogin = computed(() => mode.value === 'login');
const isSignup = computed(() => mode.value === 'signup');
const title = computed(() => (isLogin.value ? 'Connectez-vous à votre compte' : 'Créez votre compte'));
const submitLabel = computed(() => (isLogin.value ? 'Se connecter' : "S'inscrire"));
const switchPrompt = computed(() => (isLogin.value ? 'Pas encore de compte ?' : 'Déjà un compte ?'));
const switchLabel = computed(() => (isLogin.value ? "S'inscrire" : 'Se connecter'));

function setMode(next) {
  mode.value = next;
  error.value = '';
}
function switchMode() {
  setMode(isLogin.value ? 'signup' : 'login');
}

function validEmail(v) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

function submit() {
  if (!validEmail(form.email)) {
    error.value = 'Adresse e-mail invalide.';
    return;
  }
  if (!form.password || form.password.length < 6) {
    error.value = 'Le mot de passe doit contenir au moins 6 caractères.';
    return;
  }
  if (isSignup.value) {
    if (!form.name) {
      error.value = 'Merci de renseigner votre nom.';
      return;
    }
    if (form.password !== form.confirmPassword) {
      error.value = 'Les mots de passe ne correspondent pas.';
      return;
    }
  }
  // Stub auth: no backend wired up yet, just move on to the app shell.
  router.push('/dashboard');
}
```

Replace with:
```js
import { reactive, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useIsMobile } from '../lib/useIsMobile';
import { useAuthStore } from '../stores/auth';
import { ApiError } from '../lib/api';

const router = useRouter();
const isMobile = useIsMobile();
const authStore = useAuthStore();

const mode = ref('login');
const form = reactive({ name: '', email: '', password: '', confirmPassword: '' });
const error = ref('');
const submitting = ref(false);

const isLogin = computed(() => mode.value === 'login');
const isSignup = computed(() => mode.value === 'signup');
const title = computed(() => (isLogin.value ? 'Connectez-vous à votre compte' : 'Créez votre compte'));
const submitLabel = computed(() => (isLogin.value ? 'Se connecter' : "S'inscrire"));
const switchPrompt = computed(() => (isLogin.value ? 'Pas encore de compte ?' : 'Déjà un compte ?'));
const switchLabel = computed(() => (isLogin.value ? "S'inscrire" : 'Se connecter'));

function setMode(next) {
  mode.value = next;
  error.value = '';
}
function switchMode() {
  setMode(isLogin.value ? 'signup' : 'login');
}

function validEmail(v) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

async function submit() {
  if (!validEmail(form.email)) {
    error.value = 'Adresse e-mail invalide.';
    return;
  }
  if (!form.password || form.password.length < 6) {
    error.value = 'Le mot de passe doit contenir au moins 6 caractères.';
    return;
  }
  if (isSignup.value) {
    if (!form.name) {
      error.value = 'Merci de renseigner votre nom.';
      return;
    }
    if (form.password !== form.confirmPassword) {
      error.value = 'Les mots de passe ne correspondent pas.';
      return;
    }
  }

  error.value = '';
  submitting.value = true;
  try {
    if (isSignup.value) {
      await authStore.register({ name: form.name, email: form.email, password: form.password });
    } else {
      await authStore.login(form.email, form.password);
    }
    router.push('/dashboard');
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.errors ? Object.values(e.errors).flat()[0] : e.message;
    } else {
      error.value = 'Une erreur est survenue.';
    }
  } finally {
    submitting.value = false;
  }
}
```

- [ ] **Step 2: Disable the submit button while submitting**

In both the mobile and desktop templates, the submit `<button>` currently reads:
```html
<button
  type="button"
  class="w-full bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer"
  @click="submit"
>{{ submitLabel }}</button>
```
(mobile, `rounded-[10px]`) and
```html
<button
  type="button"
  class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-3 text-sm font-semibold shadow-sm cursor-pointer"
  @click="submit"
>{{ submitLabel }}</button>
```
(desktop, `rounded-lg`). Add `:disabled="submitting"` to both:
```html
<button
  type="button"
  :disabled="submitting"
  class="w-full bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer disabled:opacity-60"
  @click="submit"
>{{ submitLabel }}</button>
```
```html
<button
  type="button"
  :disabled="submitting"
  class="w-full bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg py-3 text-sm font-semibold shadow-sm cursor-pointer disabled:opacity-60"
  @click="submit"
>{{ submitLabel }}</button>
```

- [ ] **Step 3: Manual verification**

With both servers running, open `http://localhost:5173/login`:
1. Switch to "Inscription", fill in a new name/email/password (password ≥ 8 chars — the API's `min:8` is stricter than the client-side `min 6` check; use an 8+ char password to avoid a spurious 422), submit. Expected: redirected to `/dashboard`.
2. Open the browser's dev tools → Application → Cookies for `localhost:8000`; confirm `laravel_session` and `XSRF-TOKEN` cookies are set.
3. Reload `http://localhost:5173/dashboard` directly. Expected: stays on dashboard (session persists), not bounced to `/login`.
4. Log in as `demo@comptastic.test` / `password` after logging out (logout isn't wired until Task 20 — for now, clear cookies manually via dev tools to simulate a logged-out state, or use a private/incognito window). Expected: redirected to `/dashboard`.
5. Try a wrong password. Expected: red banner shows "Identifiants invalides." (the exact string `AuthController::login` throws).

- [ ] **Step 4: Commit**

```bash
git add apps/web/src/views/LoginView.vue
git commit -m "feat(web): wire login/register to the real auth API"
```

---

## Task 6: Categories store

**Files:**
- Create: `apps/web/src/stores/categories.js`

- [ ] **Step 1: Write the store**

`GET /api/categories` returns `{ data: [{ id, name, color_hex, is_income }, ...] }`, already ordered by `sort_order`.

```js
// apps/web/src/stores/categories.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const useCategoriesStore = defineStore('categories', () => {
  const items = ref([]);
  const loaded = ref(false);

  const byId = computed(() => Object.fromEntries(items.value.map((c) => [c.id, c])));
  const expense = computed(() => items.value.filter((c) => !c.is_income));

  async function fetch() {
    if (loaded.value) return;
    const res = await apiFetch('/api/categories');
    items.value = res.data;
    loaded.value = true;
  }

  return { items, byId, expense, fetch };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/categories.js
git commit -m "feat(web): add categories store"
```

---

## Task 7: Accounts store

**Files:**
- Create: `apps/web/src/stores/accounts.js`

- [ ] **Step 1: Write the store**

`GET /api/accounts` returns `{ data: [{ id, name, bank, type, iban_last4, opening_balance, balance, pending_encours }, ...] }`. `type` is `'checking'` or `'savings'`.

```js
// apps/web/src/stores/accounts.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const ACCOUNT_TYPE_LABELS = { checking: 'Compte courant', savings: 'Épargne' };

export const useAccountsStore = defineStore('accounts', () => {
  const items = ref([]);
  const loaded = ref(false);

  const byId = computed(() => Object.fromEntries(items.value.map((a) => [a.id, a])));
  const savings = computed(() => items.value.filter((a) => a.type === 'savings'));

  async function fetch() {
    const res = await apiFetch('/api/accounts');
    items.value = res.data;
    loaded.value = true;
  }

  async function create({ name, bank, type, openingBalance }) {
    const res = await apiFetch('/api/accounts', {
      method: 'POST',
      body: { name, bank: bank || null, type, opening_balance: openingBalance },
    });
    items.value.push(res.data);
    return res.data;
  }

  return { items, byId, savings, loaded, fetch, create };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/accounts.js
git commit -m "feat(web): add accounts store"
```

---

## Task 8: Debts store

**Files:**
- Create: `apps/web/src/stores/debts.js`

- [ ] **Step 1: Write the store**

`GET /api/debts` returns `{ data: [{ id, name, original_amount, remaining_amount, monthly_payment, rate, end_date, progress_pct, months_left }, ...] }` — `progress_pct` and `months_left` are already computed server-side.

```js
// apps/web/src/stores/debts.js
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../lib/api';

export const useDebtsStore = defineStore('debts', () => {
  const items = ref([]);

  const byId = computed(() => Object.fromEntries(items.value.map((d) => [d.id, d])));

  async function fetch() {
    const res = await apiFetch('/api/debts');
    items.value = res.data;
  }

  async function create({ name, originalAmount, remainingAmount, monthlyPayment, rate, endDate }) {
    const res = await apiFetch('/api/debts', {
      method: 'POST',
      body: {
        name,
        original_amount: originalAmount,
        remaining_amount: remainingAmount,
        monthly_payment: monthlyPayment,
        rate,
        end_date: endDate,
      },
    });
    items.value.push(res.data);
    return res.data;
  }

  return { items, byId, fetch, create };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/debts.js
git commit -m "feat(web): add debts store"
```

---

## Task 9: Settings store

**Files:**
- Create: `apps/web/src/stores/settings.js`

- [ ] **Step 1: Write the store**

`GET /api/settings` / `PUT /api/settings` both return `{ data: { monthly_income, monthly_savings_contribution, annual_return_rate } }` — already euro decimals, not cents.

```js
// apps/web/src/stores/settings.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useSettingsStore = defineStore('settings', () => {
  const income = ref(0);
  const monthlySavingsContribution = ref(0);
  const annualReturnRate = ref(0);
  const loaded = ref(false);

  function apply(data) {
    income.value = data.monthly_income;
    monthlySavingsContribution.value = data.monthly_savings_contribution;
    annualReturnRate.value = data.annual_return_rate;
  }

  async function fetch() {
    const res = await apiFetch('/api/settings');
    apply(res.data);
    loaded.value = true;
  }

  async function update(partial) {
    const res = await apiFetch('/api/settings', {
      method: 'PUT',
      body: {
        monthly_income: partial.income ?? income.value,
        monthly_savings_contribution: partial.monthlySavingsContribution ?? monthlySavingsContribution.value,
        annual_return_rate: partial.annualReturnRate ?? annualReturnRate.value,
      },
    });
    apply(res.data);
  }

  return { income, monthlySavingsContribution, annualReturnRate, loaded, fetch, update };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/settings.js
git commit -m "feat(web): add settings store"
```

---

## Task 10: Budgets store

**Files:**
- Create: `apps/web/src/stores/budgets.js`

**Important:** `GET /api/budgets` is the one endpoint in this API that does *not* convert cents to euros before responding — `budget_cents` and `spent_cents` are raw integer cents (verified: a 800€ budget comes back as `"budget_cents":80000`), unlike every other endpoint in this app. Divide by 100 in this store. `PUT /api/budgets/{category_id}` (the update endpoint), by contrast, *does* return euros already (`monthly_amount`) — don't divide that one.

- [ ] **Step 1: Write the store**

```js
// apps/web/src/stores/budgets.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useBudgetsStore = defineStore('budgets', () => {
  const rows = ref([]); // [{ category_id, name, color_hex, budget, spent, pct, status }, ...]

  async function fetch(month = null) {
    const query = month ? `?month=${month}` : '';
    const res = await apiFetch(`/api/budgets${query}`);
    rows.value = res.data.map((row) => ({
      category_id: row.category_id,
      name: row.name,
      color_hex: row.color_hex,
      budget: row.budget_cents / 100,
      spent: row.spent_cents / 100,
      pct: row.pct,
      status: row.status,
    }));
  }

  async function update(categoryId, monthlyAmount) {
    const res = await apiFetch(`/api/budgets/${categoryId}`, {
      method: 'PUT',
      body: { monthly_amount: monthlyAmount },
    });
    const row = rows.value.find((r) => r.category_id === categoryId);
    if (row) row.budget = res.data.monthly_amount;
  }

  return { rows, fetch, update };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/budgets.js
git commit -m "feat(web): add budgets store"
```

---

## Task 11: Dashboard store

**Files:**
- Create: `apps/web/src/stores/dashboard.js`

`GET /api/dashboard/summary?period=current|previous|year` returns `{ data: { bars: [{label, income, expense}], categories: [{category_id, amount}] } }`. Cache per period so switching the dropdown back and forth doesn't refetch, and so the dashboard's trend badge (current vs previous) doesn't force an extra round trip when the user is already viewing "current".

- [ ] **Step 1: Write the store**

```js
// apps/web/src/stores/dashboard.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useDashboardStore = defineStore('dashboard', () => {
  const byPeriod = ref({}); // { current: {bars, categories}, previous: {...}, year: {...} }

  async function fetch(period) {
    if (byPeriod.value[period]) return byPeriod.value[period];
    const res = await apiFetch(`/api/dashboard/summary?period=${period}`);
    byPeriod.value = { ...byPeriod.value, [period]: res.data };
    return res.data;
  }

  return { byPeriod, fetch };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/dashboard.js
git commit -m "feat(web): add dashboard summary store"
```

---

## Task 12: Projection store

**Files:**
- Create: `apps/web/src/stores/projection.js`

`GET /api/savings-projection?horizon=N` returns `{ data: { history: [{month_offset, balance}], projection: [balance, ...] } }` — `history` always has 4 points (offsets -3..0), `projection` has `horizon + 1` points (index 0 = today).

- [ ] **Step 1: Write the store**

```js
// apps/web/src/stores/projection.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useProjectionStore = defineStore('projection', () => {
  const history = ref([]); // [{month_offset, balance}, ...]
  const projection = ref([]); // [balance, ...] index 0 = today

  async function fetch(horizon) {
    const res = await apiFetch(`/api/savings-projection?horizon=${horizon}`);
    history.value = res.data.history;
    projection.value = res.data.projection;
  }

  return { history, projection, fetch };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/projection.js
git commit -m "feat(web): add savings projection store"
```

---

## Task 13: Transactions store

**Files:**
- Create: `apps/web/src/stores/transactions.js`

`GET /api/transactions?period=X&account_id=Y` returns a paginated resource: `{ data: [...], links: {...}, meta: {...} }`. This plan only consumes page 1 (`data`) — no "load more" UI is added (matches the spec's scope; the mock version never paginated either since it only had 10 seed rows).

Transaction creation is a single POST — the backend (`TransactionSeriesGenerator`) expands `mode: 'installment'` / `'recurring'` into multiple rows server-side. The frontend no longer generates and submits individual dated rows itself.

- [ ] **Step 1: Write the store**

```js
// apps/web/src/stores/transactions.js
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { apiFetch } from '../lib/api';

export const useTransactionsStore = defineStore('transactions', () => {
  const items = ref([]);

  async function fetch({ period = 'current', accountId = null } = {}) {
    const params = new URLSearchParams({ period });
    if (accountId) params.set('account_id', accountId);
    const res = await apiFetch(`/api/transactions?${params.toString()}`);
    items.value = res.data;
  }

  async function create(payload) {
    const res = await apiFetch('/api/transactions', { method: 'POST', body: payload });
    return res.data;
  }

  async function toggleReconciled(id) {
    const t = items.value.find((x) => x.id === id);
    if (!t) return;
    const res = await apiFetch(`/api/transactions/${id}`, {
      method: 'PATCH',
      body: { reconciled: !t.reconciled },
    });
    Object.assign(t, res.data);
  }

  async function remove(id) {
    await apiFetch(`/api/transactions/${id}`, { method: 'DELETE' });
    items.value = items.value.filter((t) => t.id !== id);
  }

  return { items, fetch, create, toggleReconciled, remove };
});
```

- [ ] **Step 2: Commit**

```bash
git add apps/web/src/stores/transactions.js
git commit -m "feat(web): add transactions store"
```

---

## Task 14: Wire `DettesView.vue`

**Files:**
- Modify: `apps/web/src/views/DettesView.vue`

This view gets simpler: `progress_pct`/`months_left` are already computed server-side, so the client no longer derives them.

- [ ] **Step 1: Replace the script block**

Current script (lines 1–56):
```js
import { reactive, ref, computed } from 'vue';
import { useLedgerStore } from '../stores/ledger';
import { eur, fmtDateLabel } from '../lib/format';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { useIsMobile } from '../lib/useIsMobile';

const store = useLedgerStore();
const isMobile = useIsMobile();

const showForm = ref(false);
function blankForm() {
  return { name: '', originalAmount: '', remainingAmount: '', monthlyPayment: '', rate: '', endDate: '' };
}
const form = reactive(blankForm());

const debts = computed(() =>
  store.debts.map((d) => {
    const progressPct = d.originalAmount > 0 ? Math.min(((d.originalAmount - d.remainingAmount) / d.originalAmount) * 100, 100) : 0;
    const monthsLeft = d.monthlyPayment > 0 ? Math.ceil(d.remainingAmount / d.monthlyPayment) : null;
    return {
      ...d,
      remainingLabel: eur(d.remainingAmount),
      originalLabel: eur(d.originalAmount),
      monthlyLabel: eur(d.monthlyPayment),
      rateLabel: `${d.rate}%`,
      endDateLabel: fmtDateLabel(d.endDate),
      progressPct: progressPct.toFixed(1),
      progressLabel: `${Math.round(progressPct)}%`,
      monthsLeftLabel: monthsLeft !== null ? `${monthsLeft} mensualité(s) restante(s)` : '—',
    };
  })
);
const totalRemaining = computed(() => debts.value.reduce((s, d) => s + d.remainingAmount, 0));
const totalMonthly = computed(() => debts.value.reduce((s, d) => s + d.monthlyPayment, 0));

function openForm() {
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}
function submitForm() {
  if (!form.name || !form.originalAmount) return;
  store.addDebt({
    name: form.name,
    originalAmount: Number(form.originalAmount) || 0,
    remainingAmount: Number(form.remainingAmount) || Number(form.originalAmount) || 0,
    monthlyPayment: Number(form.monthlyPayment) || 0,
    rate: Number(form.rate) || 0,
    endDate: form.endDate || '2027-01-01',
  });
  Object.assign(form, blankForm());
  showForm.value = false;
}
```

Replace with:
```js
import { reactive, ref, computed, onMounted } from 'vue';
import { useDebtsStore } from '../stores/debts';
import { eur, fmtDateLabel } from '../lib/format';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { useIsMobile } from '../lib/useIsMobile';
import { ApiError } from '../lib/api';

const debtsStore = useDebtsStore();
const isMobile = useIsMobile();

onMounted(() => {
  debtsStore.fetch();
});

const showForm = ref(false);
const formError = ref('');
function blankForm() {
  return { name: '', originalAmount: '', remainingAmount: '', monthlyPayment: '', rate: '', endDate: '' };
}
const form = reactive(blankForm());

const debts = computed(() =>
  debtsStore.items.map((d) => ({
    ...d,
    remainingLabel: eur(d.remaining_amount),
    originalLabel: eur(d.original_amount),
    monthlyLabel: eur(d.monthly_payment),
    rateLabel: `${d.rate}%`,
    endDateLabel: fmtDateLabel(d.end_date),
    progressPct: d.progress_pct.toFixed(1),
    progressLabel: `${Math.round(d.progress_pct)}%`,
    monthsLeftLabel: d.months_left !== null ? `${d.months_left} mensualité(s) restante(s)` : '—',
  }))
);
const totalRemaining = computed(() => debts.value.reduce((s, d) => s + d.remaining_amount, 0));
const totalMonthly = computed(() => debts.value.reduce((s, d) => s + d.monthly_payment, 0));

function openForm() {
  formError.value = '';
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}
async function submitForm() {
  if (!form.name || !form.originalAmount) return;
  formError.value = '';
  try {
    await debtsStore.create({
      name: form.name,
      originalAmount: Number(form.originalAmount) || 0,
      remainingAmount: Number(form.remainingAmount) || Number(form.originalAmount) || 0,
      monthlyPayment: Number(form.monthlyPayment) || 0,
      rate: Number(form.rate) || 0,
      endDate: form.endDate || '2027-01-01',
    });
    Object.assign(form, blankForm());
    showForm.value = false;
  } catch (e) {
    formError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}
```

- [ ] **Step 2: Show the form error in the modal**

The modal's action row currently reads:
```html
      <div class="flex gap-3">
        <button type="button" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer" @click="submitForm">
          <Icon name="plus" :stroke-width="2" />Ajouter
        </button>
        <button type="button" class="inline-flex items-center gap-1.5 bg-transparent text-slate-600 border border-slate-200 rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50" @click="closeForm">
          <Icon name="close" :stroke-width="2" />Annuler
        </button>
      </div>
```
Add an error banner directly above it:
```html
      <div v-if="formError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">
        {{ formError }}
      </div>
      <div class="flex gap-3">
        <button type="button" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer" @click="submitForm">
          <Icon name="plus" :stroke-width="2" />Ajouter
        </button>
        <button type="button" class="inline-flex items-center gap-1.5 bg-transparent text-slate-600 border border-slate-200 rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50" @click="closeForm">
          <Icon name="close" :stroke-width="2" />Annuler
        </button>
      </div>
```

- [ ] **Step 3: Manual verification**

Log in, navigate to `/dettes`. Expected: the three seeded debts render with correct progress bars and "mensualité(s) restante(s)" text matching what `php artisan test` / the earlier curl check showed. Create a new debt via the form; expected: it appears in the list immediately without a page reload.

- [ ] **Step 4: Commit**

```bash
git add apps/web/src/views/DettesView.vue
git commit -m "feat(web): wire DettesView to the debts API"
```

---

## Task 15: Wire `ComptesView.vue`

**Files:**
- Modify: `apps/web/src/views/ComptesView.vue`

Account `type` values change from French labels (`'Compte courant'`/`'Épargne'`) to the API's enum (`'checking'`/`'savings'`); a label map converts back for display. The IBAN field goes from a fabricated full masked string to the API's real `iban_last4` (nullable — new accounts won't have one until a later feature adds IBAN collection).

- [ ] **Step 1: Replace the script block**

Current script (lines 1–44):
```js
import { reactive, ref, computed } from 'vue';
import { useLedgerStore } from '../stores/ledger';
import { eur } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';

const store = useLedgerStore();
const isMobile = useIsMobile();

const showForm = ref(false);
function blankForm() {
  return { name: '', bank: '', type: 'Compte courant', openingBalance: '' };
}
const form = reactive(blankForm());

const accounts = computed(() =>
  store.accountBalances().map((a) => ({
    ...a,
    balanceLabel: eur(a.balance),
    hasPending: Math.abs(a.pendingEncours) > 0.005,
    pendingLabel: `${a.pendingEncours >= 0 ? '+' : ''}${eur(a.pendingEncours)} non pointé`,
  }))
);
const totalBalance = computed(() => accounts.value.reduce((s, a) => s + a.balance, 0));

function openForm() {
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}
function submitForm() {
  if (!form.name) return;
  store.addAccount({
    name: form.name,
    bank: form.bank,
    type: form.type,
    openingBalance: Number(form.openingBalance) || 0,
  });
  Object.assign(form, blankForm());
  showForm.value = false;
}
```

Replace with:
```js
import { reactive, ref, computed, onMounted } from 'vue';
import { useAccountsStore, ACCOUNT_TYPE_LABELS } from '../stores/accounts';
import { eur } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { ApiError } from '../lib/api';

const accountsStore = useAccountsStore();
const isMobile = useIsMobile();

onMounted(() => {
  accountsStore.fetch();
});

const showForm = ref(false);
const formError = ref('');
function blankForm() {
  return { name: '', bank: '', type: 'checking', openingBalance: '' };
}
const form = reactive(blankForm());

const accounts = computed(() =>
  accountsStore.items.map((a) => ({
    ...a,
    typeLabel: ACCOUNT_TYPE_LABELS[a.type] || a.type,
    balanceLabel: eur(a.balance),
    hasPending: Math.abs(a.pending_encours) > 0.005,
    pendingLabel: `${a.pending_encours >= 0 ? '+' : ''}${eur(a.pending_encours)} non pointé`,
    ibanLabel: a.iban_last4 ? `IBAN se terminant par ${a.iban_last4}` : '',
  }))
);
const totalBalance = computed(() => accounts.value.reduce((s, a) => s + a.balance, 0));

function openForm() {
  formError.value = '';
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}
async function submitForm() {
  if (!form.name) return;
  formError.value = '';
  try {
    await accountsStore.create({
      name: form.name,
      bank: form.bank,
      type: form.type,
      openingBalance: Number(form.openingBalance) || 0,
    });
    Object.assign(form, blankForm());
    showForm.value = false;
  } catch (e) {
    formError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}
```

- [ ] **Step 2: Update the type `<select>` options (both mobile and desktop forms)**

Both occurrences currently read:
```html
        <select v-model="form.type" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option value="Compte courant">Compte courant</option>
          <option value="Épargne">Épargne</option>
        </select>
```
(mobile, `rounded-[10px]`) and
```html
            <select v-model="form.type" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
              <option value="Compte courant">Compte courant</option>
              <option value="Épargne">Épargne</option>
            </select>
```
(desktop, `rounded-lg`). In both, change the option values to the API enum while keeping the French display text:
```html
          <option value="checking">Compte courant</option>
          <option value="savings">Épargne</option>
```

- [ ] **Step 3: Update the type badge and IBAN line in both account card templates**

Three places render `{{ acc.type }}` as the badge text — mobile card, desktop card. Change each to `{{ acc.typeLabel }}`. Example (mobile card, unchanged surrounding markup):
```html
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-indigo-50 text-indigo-700 whitespace-nowrap">{{ acc.typeLabel }}</span>
```

The desktop card's IBAN line currently reads:
```html
          <div class="text-xs text-slate-400">{{ acc.iban }}</div>
```
Change to only render when present:
```html
          <div v-if="acc.ibanLabel" class="text-xs text-slate-400">{{ acc.ibanLabel }}</div>
```

- [ ] **Step 4: Show the form error in both modals**

Same pattern as Task 14 Step 2 — insert an error banner directly above each modal's action button row:
```html
      <div v-if="formError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">
        {{ formError }}
      </div>
```
(applies to both the mobile `ModalSheet` and the desktop `ModalSheet` action rows.)

- [ ] **Step 5: Manual verification**

Navigate to `/comptes`. Expected: the 5 seeded accounts render with correct balances and "Compte courant"/"Épargne" badges (French labels, even though the underlying value is now `checking`/`savings`). Create a new account; expected: it appears immediately with a blank IBAN line (no `ibanLabel` set).

- [ ] **Step 6: Commit**

```bash
git add apps/web/src/views/ComptesView.vue
git commit -m "feat(web): wire ComptesView to the accounts API"
```

---

## Task 16: Wire `BudgetsView.vue`

**Files:**
- Modify: `apps/web/src/views/BudgetsView.vue`

The budgets endpoint already returns `budget`/`spent`/`pct`/`status` per category (Task 10 store), so the client-side `spentByCategory` aggregation and `statusFor()` bucketing are no longer needed — `status` from the API is one of `'over' | 'warn' | 'ok'`, matching the existing `statusFor()` keys exactly.

- [ ] **Step 1: Replace the script block**

Current script (lines 1–74):
```js
import { computed } from 'vue';
import { useLedgerStore, CAT_COLORS } from '../stores/ledger';
import { eur, monthBoundsISO, TODAY } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import EditableAmount from '../components/EditableAmount.vue';

const store = useLedgerStore();
const isMobile = useIsMobile();

const monthLabel = TODAY.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

const spentByCategory = computed(() => {
  const { start, end } = monthBoundsISO();
  const spent = {};
  for (const t of store.transactions) {
    if (t.amount < 0 && t.date >= start && t.date <= end && t.category !== 'Revenus') {
      spent[t.category] = (spent[t.category] || 0) + Math.abs(t.amount);
    }
  }
  return spent;
});

function statusFor(pct) {
  if (pct >= 100) return { key: 'over', label: 'Dépassé', bar: 'bg-red-600', badge: 'bg-red-50 text-red-700' };
  if (pct >= 80) return { key: 'warn', label: 'Presque atteint', bar: 'bg-amber-600', badge: 'bg-amber-50 text-amber-700' };
  return { key: 'ok', label: 'Sous contrôle', bar: 'bg-indigo-600', badge: 'bg-indigo-50 text-indigo-700' };
}

const rows = computed(() =>
  store.expenseCategories.map((cat) => {
    const budget = store.budgets[cat] ?? 0;
    const spent = spentByCategory.value[cat] || 0;
    const pct = budget > 0 ? (spent / budget) * 100 : 0;
    const status = statusFor(pct);
    return {
      category: cat,
      color: CAT_COLORS[cat] || '#94a3b8',
      budget,
      budgetLabel: eur(budget),
      barWidthPct: Math.min(pct, 100).toFixed(1),
      barClass: status.bar,
      statusLabel: status.label,
      statusBadgeClass: status.badge,
      spentLabel: eur(spent),
      remainingLabel: budget - spent >= 0 ? `${eur(budget - spent)} restants` : `${eur(spent - budget)} de dépassement`,
    };
  })
);

const totalBudget = computed(() => Object.values(store.budgets).reduce((a, b) => a + (Number(b) || 0), 0));
const totalSpent = computed(() => Object.values(spentByCategory.value).reduce((a, b) => a + b, 0));
const overPct = computed(() => (totalBudget.value > 0 ? (totalSpent.value / totalBudget.value) * 100 : 0));
const overallStatus = computed(() => statusFor(overPct.value));

const hasIncome = computed(() => store.income > 0);
const isOverBudget = computed(() => hasIncome.value && totalBudget.value > store.income);
const budgetedPortionPct = computed(() => (hasIncome.value ? Math.min((totalBudget.value / store.income) * 100, 100) : 0));

const incomeSegments = computed(() =>
  store.expenseCategories.map((cat) => {
    const budget = store.budgets[cat] ?? 0;
    const widthPct = totalBudget.value > 0 ? (budget / totalBudget.value) * budgetedPortionPct.value : 0;
    return {
      category: cat,
      color: CAT_COLORS[cat] || '#94a3b8',
      widthPct: widthPct.toFixed(1),
      amountLabel: eur(budget),
      pctLabel: hasIncome.value ? `${Math.round((budget / store.income) * 100)}%` : '—',
    };
  })
);
const savings = computed(() => store.income - totalBudget.value);
const savingsPct = computed(() => (hasIncome.value && !isOverBudget.value ? 100 - budgetedPortionPct.value : 0));
```

Replace with:
```js
import { computed, ref, onMounted } from 'vue';
import { useBudgetsStore } from '../stores/budgets';
import { useSettingsStore } from '../stores/settings';
import { eur, TODAY } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import EditableAmount from '../components/EditableAmount.vue';
import { ApiError } from '../lib/api';

const budgetsStore = useBudgetsStore();
const settingsStore = useSettingsStore();
const isMobile = useIsMobile();

onMounted(() => {
  budgetsStore.fetch();
  settingsStore.fetch();
});

const monthLabel = TODAY.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

const STATUS_STYLES = {
  over: { label: 'Dépassé', bar: 'bg-red-600', badge: 'bg-red-50 text-red-700' },
  warn: { label: 'Presque atteint', bar: 'bg-amber-600', badge: 'bg-amber-50 text-amber-700' },
  ok: { label: 'Sous contrôle', bar: 'bg-indigo-600', badge: 'bg-indigo-50 text-indigo-700' },
};

const rows = computed(() =>
  budgetsStore.rows.map((row) => {
    const style = STATUS_STYLES[row.status];
    return {
      categoryId: row.category_id,
      category: row.name,
      color: row.color_hex,
      budget: row.budget,
      budgetLabel: eur(row.budget),
      barWidthPct: Math.min(row.pct, 100).toFixed(1),
      barClass: style.bar,
      statusLabel: style.label,
      statusBadgeClass: style.badge,
      spentLabel: eur(row.spent),
      remainingLabel: row.budget - row.spent >= 0 ? `${eur(row.budget - row.spent)} restants` : `${eur(row.spent - row.budget)} de dépassement`,
    };
  })
);

const rowError = ref('');
async function updateBudget(categoryId, value) {
  rowError.value = '';
  try {
    await budgetsStore.update(categoryId, value);
  } catch (e) {
    rowError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}

const totalBudget = computed(() => budgetsStore.rows.reduce((s, r) => s + r.budget, 0));
const totalSpent = computed(() => budgetsStore.rows.reduce((s, r) => s + r.spent, 0));
const overPct = computed(() => (totalBudget.value > 0 ? (totalSpent.value / totalBudget.value) * 100 : 0));
const overallStatus = computed(() => {
  const key = overPct.value >= 100 ? 'over' : overPct.value >= 80 ? 'warn' : 'ok';
  return { key, ...STATUS_STYLES[key] };
});

const hasIncome = computed(() => settingsStore.income > 0);
const isOverBudget = computed(() => hasIncome.value && totalBudget.value > settingsStore.income);
const budgetedPortionPct = computed(() => (hasIncome.value ? Math.min((totalBudget.value / settingsStore.income) * 100, 100) : 0));

const incomeSegments = computed(() =>
  rows.value.map((row) => {
    const widthPct = totalBudget.value > 0 ? (row.budget / totalBudget.value) * budgetedPortionPct.value : 0;
    return {
      category: row.category,
      color: row.color,
      widthPct: widthPct.toFixed(1),
      amountLabel: row.budgetLabel,
      pctLabel: hasIncome.value ? `${Math.round((row.budget / settingsStore.income) * 100)}%` : '—',
    };
  })
);
const savings = computed(() => settingsStore.income - totalBudget.value);
const savingsPct = computed(() => (hasIncome.value && !isOverBudget.value ? 100 - budgetedPortionPct.value : 0));

async function updateIncome(value) {
  rowError.value = '';
  try {
    await settingsStore.update({ income: value });
  } catch (e) {
    rowError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}
```

- [ ] **Step 2: Update template bindings for the per-category budget editor (both mobile and desktop)**

Mobile row (currently):
```html
        <div v-for="row in rows" :key="row.category" class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="flex justify-between items-center mb-2">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-sm" :style="{ background: row.color }"></span>
              <span class="text-[13px] font-bold">{{ row.category }}</span>
            </div>
            <EditableAmount
              :model-value="row.budget"
              :display="row.budgetLabel"
              variant="inline"
              compact
              :step="10"
              min="0"
              @update:model-value="(v) => (store.budgets[row.category] = v)"
            />
          </div>
```
Change the `:key` and the update handler:
```html
        <div v-for="row in rows" :key="row.categoryId" class="bg-white border border-slate-200 rounded-xl shadow-sm p-3.5">
          <div class="flex justify-between items-center mb-2">
            <div class="flex items-center gap-2">
              <span class="w-2 h-2 rounded-sm" :style="{ background: row.color }"></span>
              <span class="text-[13px] font-bold">{{ row.category }}</span>
            </div>
            <EditableAmount
              :model-value="row.budget"
              :display="row.budgetLabel"
              variant="inline"
              compact
              :step="10"
              min="0"
              @update:model-value="(v) => updateBudget(row.categoryId, v)"
            />
          </div>
```

Desktop row (currently):
```html
      <div v-for="row in rows" :key="row.category" class="px-6 py-5 border-b border-slate-100 last:border-b-0">
        <div class="flex justify-between items-center gap-4 flex-wrap mb-3">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: row.color }"></span>
            <span class="text-[15px] font-bold">{{ row.category }}</span>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="row.statusBadgeClass">{{ row.statusLabel }}</span>
          </div>
          <EditableAmount
            :model-value="row.budget"
            :display="`${row.budgetLabel} / mois`"
            variant="inline"
            suffix="€"
            :step="10"
            min="0"
            @update:model-value="(v) => (store.budgets[row.category] = v)"
          />
        </div>
```
Change the `:key` and update handler the same way:
```html
      <div v-for="row in rows" :key="row.categoryId" class="px-6 py-5 border-b border-slate-100 last:border-b-0">
        <div class="flex justify-between items-center gap-4 flex-wrap mb-3">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ background: row.color }"></span>
            <span class="text-[15px] font-bold">{{ row.category }}</span>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="row.statusBadgeClass">{{ row.statusLabel }}</span>
          </div>
          <EditableAmount
            :model-value="row.budget"
            :display="`${row.budgetLabel} / mois`"
            variant="inline"
            suffix="€"
            :step="10"
            min="0"
            @update:model-value="(v) => updateBudget(row.categoryId, v)"
          />
        </div>
```

- [ ] **Step 3: Update the income editor (desktop "Répartition du revenu" section)**

Currently:
```html
        <EditableAmount
          :model-value="store.income"
          :display="`Revenu mensuel : ${eur(store.income)}`"
          variant="inline"
          suffix="€"
          :step="50"
          min="0"
          @update:model-value="(v) => (store.income = v)"
        />
```
Change to:
```html
        <EditableAmount
          :model-value="settingsStore.income"
          :display="`Revenu mensuel : ${eur(settingsStore.income)}`"
          variant="inline"
          suffix="€"
          :step="50"
          min="0"
          @update:model-value="updateIncome"
        />
```

Also update the `v-for="seg in incomeSegments" :key="seg.category"` and `:key="'lbl-' + seg.category"` bindings — no change needed there since `incomeSegments` still exposes `category` as a string name (unchanged shape from the previous script), only its source data changed.

- [ ] **Step 4: Add an error banner for failed inline edits**

Both the mobile and desktop templates open their main scrollable area right after the header. Mobile `<main>` currently starts:
```html
    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5 flex justify-between items-center">
```
Add the error banner as the first child:
```html
    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
      <div v-if="rowError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ rowError }}</div>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5 flex justify-between items-center">
```
Desktop `<main>` currently starts:
```html
  <main v-else class="max-w-[960px] w-full mx-auto px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-2">
```
Add the error banner right after the intro paragraph, before the summary card:
```html
  <main v-else class="max-w-[960px] w-full mx-auto px-8 pt-10 pb-14">
    <div class="flex justify-between items-center gap-4 flex-wrap mb-2">
      <h1 class="m-0 text-[28px] font-bold tracking-tight">Budgets</h1>
      <div class="text-[13px] text-slate-500 capitalize">{{ monthLabel }}</div>
    </div>
    <p class="mt-0 mb-7 text-sm text-slate-500">Définissez une enveloppe mensuelle par catégorie et suivez sa consommation en direct.</p>

    <div v-if="rowError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ rowError }}</div>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
```
(this replaces the existing `mb-7` header block and the following `<div class="flex items-center gap-6...">` opening tag — the intervening lines are unchanged, only the `rowError` banner is inserted between them.)

- [ ] **Step 5: Manual verification**

Navigate to `/budgets`. Expected: 6 category rows matching the earlier curl output (`Logement` 800€ budget / 780€ spent / 97.5% "warn" status, etc.), plus a "Répartition du revenu" section reading the real settings income. Edit a budget amount inline; expected: the bar and remaining-label update immediately and persist on reload. Edit the monthly income; expected: the "over budget" warning and segment percentages recompute.

- [ ] **Step 6: Commit**

```bash
git add apps/web/src/views/BudgetsView.vue
git commit -m "feat(web): wire BudgetsView to the budgets and settings APIs"
```

---

## Task 17: Wire `TransactionsView.vue`

**Files:**
- Modify: `apps/web/src/views/TransactionsView.vue`

This is the largest view. Key changes:
- Form fields switch from name-valued (`form.category`, `form.account`) to id-valued (`form.category_id`, `form.account_id`).
- `submitForm` sends one API call with `mode`/`installment`/`recurring` params instead of generating and submitting individual dated rows client-side — the backend's `TransactionSeriesGenerator` owns date sequencing now, so the per-row date `<input type="date">` fields in the installment/recurring preview become **read-only** (they still show the generated dates for the user to review before submitting, but editing them client-side no longer has any effect once the request is expanded server-side).
- `running_balance` comes directly from the API per transaction; the client-side `runningBalances` computed is deleted.
- Category/account names for chip display come from `categoriesStore.byId` / `accountsStore.byId` lookups.

- [ ] **Step 1: Replace the script block**

Current script (lines 1–188) — replace entirely with:
```js
import { reactive, ref, computed, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useTransactionsStore } from '../stores/transactions';
import { useAccountsStore } from '../stores/accounts';
import { useCategoriesStore } from '../stores/categories';
import { useDebtsStore } from '../stores/debts';
import { eur, fmtDateLabel, addMonthsISO, addStepISO, TODAY_ISO } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';
import ModalSheet from '../components/ModalSheet.vue';
import { ApiError } from '../lib/api';

const transactionsStore = useTransactionsStore();
const accountsStore = useAccountsStore();
const categoriesStore = useCategoriesStore();
const debtsStore = useDebtsStore();
const route = useRoute();
const isMobile = useIsMobile();

const period = ref('current');
const accountFilter = ref('all');
const showForm = ref(false);
const formError = ref('');

async function loadTransactions() {
  await transactionsStore.fetch({
    period: period.value,
    accountId: accountFilter.value === 'all' ? null : accountFilter.value,
  });
}

onMounted(async () => {
  await Promise.all([categoriesStore.fetch(), accountsStore.fetch(), debtsStore.fetch(), loadTransactions()]);
});
watch([period, accountFilter], loadTransactions);

function blankForm() {
  return {
    label: '',
    amount: '',
    type: 'expense',
    category_id: categoriesStore.expense[0]?.id ?? null,
    account_id: accountsStore.items[0]?.id ?? null,
    date: TODAY_ISO,
    reconciled: false,
    installment: false,
    installmentCount: 4,
    recurring: false,
    recurringFrequency: 'monthly',
    recurringCount: 12,
    linkType: 'none',
    linkedDebtId: debtsStore.items[0]?.id ?? null,
    savingsAccountId: accountsStore.savings[0]?.id ?? null,
  };
}
const form = reactive(blankForm());

watch(
  () => route.query.new,
  (v) => {
    if (v) showForm.value = true;
  }
);

const installmentRows = computed(() => {
  const total = Number(form.amount) || 0;
  const count = Number(form.installmentCount) || 1;
  const per = total / count;
  const dates = [];
  for (let i = 0; i < count; i++) dates.push(addMonthsISO(form.date, i));
  return dates.map((d, i) => ({ index: i, indexLabel: `${i + 1}/${count}`, date: d, amountLabel: eur(per) }));
});
const recurringRows = computed(() => {
  const total = Number(form.amount) || 0;
  const count = Number(form.recurringCount) || 1;
  const dates = [];
  for (let i = 0; i < count; i++) dates.push(addStepISO(form.date, form.recurringFrequency, i));
  return dates.map((d, i) => ({ index: i, indexLabel: `#${i + 1}`, date: d, amountLabel: eur(total) }));
});

function toggleInstallment(checked) {
  form.installment = checked;
  if (checked) form.recurring = false;
}
function toggleRecurring(checked) {
  form.recurring = checked;
  if (checked) form.installment = false;
}

const transactions = computed(() =>
  transactionsStore.items.map((t) => ({
    ...t,
    categoryName: categoriesStore.byId[t.category_id]?.name ?? '—',
    accountName: accountsStore.byId[t.account_id]?.name ?? '—',
    dateLabel: fmtDateLabel(t.date, { short: isMobile.value }),
    amountLabel: `${t.amount >= 0 ? '+' : ''}${eur(t.amount)}`,
    amountColor: t.amount >= 0 ? 'text-emerald-700' : 'text-slate-900',
    runningBalanceLabel: t.running_balance !== null && t.running_balance !== undefined ? eur(t.running_balance) : '—',
    hasLink: t.link_type === 'debt' || t.link_type === 'savings',
    linkLabel: t.link_type === 'debt' ? `Dette · ${debtsStore.byId[t.linked_debt_id]?.name ?? ''}` : t.link_type === 'savings' ? 'Épargne' : '',
    linkTitle: t.link_type === 'savings' ? `Vers ${accountsStore.byId[t.linked_savings_account_id]?.name ?? ''}` : '',
  }))
);

function openForm() {
  formError.value = '';
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
}

async function submitForm() {
  const amt = Number(form.amount) || 0;
  if (!form.label || !amt) return;

  const payload = {
    label: form.label,
    amount: Math.abs(amt),
    type: form.type,
    category_id: form.category_id,
    account_id: form.account_id,
    date: form.date,
    reconciled: form.reconciled,
    link_type: form.linkType,
    linked_debt_id: form.linkType === 'debt' ? form.linkedDebtId : null,
    linked_savings_account_id: form.linkType === 'savings' ? form.savingsAccountId : null,
    mode: form.installment ? 'installment' : form.recurring ? 'recurring' : 'simple',
  };
  if (form.installment) payload.installment = { count: Number(form.installmentCount) };
  if (form.recurring) payload.recurring = { count: Number(form.recurringCount), frequency: form.recurringFrequency };

  formError.value = '';
  try {
    await transactionsStore.create(payload);
    await loadTransactions();
    Object.assign(form, blankForm());
    showForm.value = false;
  } catch (e) {
    formError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}

async function toggleReconciled(id) {
  await transactionsStore.toggleReconciled(id);
}
```

- [ ] **Step 2: Update the mobile account/period filter select**

Currently:
```html
        <select v-model="accountFilter" class="flex-1 text-xs font-semibold text-slate-700 bg-slate-100 border-none rounded-lg px-2.5 py-2">
          <option value="all">Tous les comptes</option>
          <option v-for="a in store.accountNames" :key="a" :value="a">{{ a }}</option>
        </select>
```
Change to:
```html
        <select v-model="accountFilter" class="flex-1 text-xs font-semibold text-slate-700 bg-slate-100 border-none rounded-lg px-2.5 py-2">
          <option value="all">Tous les comptes</option>
          <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
```
The desktop filter select has the same `store.accountNames` pattern (`<option v-for="a in store.accountNames" :key="a" :value="a">{{ a }}</option>` inside the `min-w-[220px]` select) — apply the identical change there.

- [ ] **Step 3: Update the mobile transaction card list**

Currently:
```html
          <div class="flex justify-between items-start gap-2.5 mb-2">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold mb-0.5">{{ t.label }}</div>
              <div class="text-[11px] text-slate-400">{{ t.dateLabel }} · {{ t.account }}</div>
            </div>
            <div class="text-right shrink-0">
              <div class="text-[15px] font-extrabold" :class="t.amountColor">{{ t.amountLabel }}</div>
            </div>
          </div>
          <div class="flex justify-between items-center">
            <div class="flex gap-1.5 flex-wrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-indigo-50 text-indigo-700">{{ t.category }}</span>
              <span v-if="t.hasLink" :title="t.linkTitle" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-yellow-100 text-yellow-800">{{ t.linkLabel }}</span>
            </div>
            <button
              type="button"
              class="w-6 h-6 rounded-[7px] border-[1.5px] text-white text-[13px] font-bold flex items-center justify-center cursor-pointer"
              :class="t.reconciled ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
              @click="store.toggleReconciled(t.id)"
            >{{ t.reconciled ? '✓' : '' }}</button>
          </div>
```
Change to:
```html
          <div class="flex justify-between items-start gap-2.5 mb-2">
            <div class="flex-1 min-w-0">
              <div class="text-sm font-bold mb-0.5">{{ t.label }}</div>
              <div class="text-[11px] text-slate-400">{{ t.dateLabel }} · {{ t.accountName }}</div>
            </div>
            <div class="text-right shrink-0">
              <div class="text-[15px] font-extrabold" :class="t.amountColor">{{ t.amountLabel }}</div>
            </div>
          </div>
          <div class="flex justify-between items-center">
            <div class="flex gap-1.5 flex-wrap">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-indigo-50 text-indigo-700">{{ t.categoryName }}</span>
              <span v-if="t.hasLink" :title="t.linkTitle" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold bg-yellow-100 text-yellow-800">{{ t.linkLabel }}</span>
            </div>
            <button
              type="button"
              class="w-6 h-6 rounded-[7px] border-[1.5px] text-white text-[13px] font-bold flex items-center justify-center cursor-pointer"
              :class="t.reconciled ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
              @click="toggleReconciled(t.id)"
            >{{ t.reconciled ? '✓' : '' }}</button>
          </div>
```

- [ ] **Step 4: Update the mobile create-transaction form fields**

Currently:
```html
        <select v-model="form.category" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="c in store.categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <select v-model="form.account" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="a in store.accountNames" :key="a" :value="a">{{ a }}</option>
        </select>
```
Change to:
```html
        <select v-model="form.category_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="c in categoriesStore.items" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="form.account_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
```
And further down:
```html
        <select v-if="form.linkType === 'debt'" v-model="form.linkedDebt" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="d in store.debtNames" :key="d" :value="d">{{ d }}</option>
        </select>
        <select v-if="form.linkType === 'savings'" v-model="form.savingsAccount" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="s in store.savingsAccountNames" :key="s" :value="s">{{ s }}</option>
        </select>
```
Change to:
```html
        <select v-if="form.linkType === 'debt'" v-model="form.linkedDebtId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="d in debtsStore.items" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
        <select v-if="form.linkType === 'savings'" v-model="form.savingsAccountId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-[10px] bg-white">
          <option v-for="s in accountsStore.savings" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
```

- [ ] **Step 5: Make the mobile installment/recurring date preview read-only**

Currently:
```html
              <input type="date" v-model="form.installmentDates[row.index]" class="flex-1 text-[12px] px-2.5 py-1.5 border border-slate-200 rounded-[7px]" />
```
and
```html
              <input type="date" v-model="form.recurringDates[row.index]" class="flex-1 text-[12px] px-2.5 py-1.5 border border-slate-200 rounded-[7px]" />
```
Change both to display-only (dates are generated server-side; editing them client-side has no effect):
```html
              <input type="date" :value="row.date" disabled class="flex-1 text-[12px] px-2.5 py-1.5 border border-slate-200 rounded-[7px] bg-slate-100 text-slate-500" />
```
```html
              <input type="date" :value="row.date" disabled class="flex-1 text-[12px] px-2.5 py-1.5 border border-slate-200 rounded-[7px] bg-slate-100 text-slate-500" />
```

- [ ] **Step 6: Add the form error banner to the mobile modal**

Currently the mobile modal's action row is:
```html
      <div class="flex gap-2.5">
        <button type="button" class="flex-1 bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer" @click="submitForm">Ajouter</button>
        <button type="button" class="flex-1 bg-slate-100 text-slate-600 rounded-[10px] py-3.5 text-sm font-bold cursor-pointer" @click="closeForm">Annuler</button>
      </div>
```
Add the error banner above it:
```html
      <div v-if="formError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">
        {{ formError }}
      </div>
      <div class="flex gap-2.5">
        <button type="button" class="flex-1 bg-indigo-600 text-white rounded-[10px] py-3.5 text-sm font-bold cursor-pointer" @click="submitForm">Ajouter</button>
        <button type="button" class="flex-1 bg-slate-100 text-slate-600 rounded-[10px] py-3.5 text-sm font-bold cursor-pointer" @click="closeForm">Annuler</button>
      </div>
```

- [ ] **Step 7: Update the desktop account filter select**

Currently:
```html
        <select v-model="accountFilter" class="min-w-[220px] bg-white text-slate-900 text-sm font-medium px-3.5 py-2.5 border border-slate-200 rounded-lg shadow-sm">
          <option value="all">Tous les comptes</option>
          <option v-for="a in store.accountNames" :key="a" :value="a">{{ a }}</option>
        </select>
```
Change to:
```html
        <select v-model="accountFilter" class="min-w-[220px] bg-white text-slate-900 text-sm font-medium px-3.5 py-2.5 border border-slate-200 rounded-lg shadow-sm">
          <option value="all">Tous les comptes</option>
          <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>
```

- [ ] **Step 8: Update the desktop create-transaction form fields**

Currently:
```html
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Catégorie</label>
          <select v-model="form.category" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="c in store.categories" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Compte</label>
          <select v-model="form.account" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="a in store.accountNames" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
```
Change to:
```html
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Catégorie</label>
          <select v-model="form.category_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="c in categoriesStore.items" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Compte</label>
          <select v-model="form.account_id" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="a in accountsStore.items" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
        </div>
```
And:
```html
        <div v-if="form.linkType === 'debt'">
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Dette concernée</label>
          <select v-model="form.linkedDebt" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="d in store.debtNames" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>
        <div v-if="form.linkType === 'savings'">
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Compte d'épargne cible</label>
          <select v-model="form.savingsAccount" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="s in store.savingsAccountNames" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
```
Change to:
```html
        <div v-if="form.linkType === 'debt'">
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Dette concernée</label>
          <select v-model="form.linkedDebtId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="d in debtsStore.items" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div v-if="form.linkType === 'savings'">
          <label class="block text-xs font-semibold text-slate-600 mb-1.5">Compte d'épargne cible</label>
          <select v-model="form.savingsAccountId" class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white">
            <option v-for="s in accountsStore.savings" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
```

- [ ] **Step 9: Make the desktop installment/recurring date preview read-only**

Same pattern as Step 5. Currently:
```html
            <input type="date" v-model="form.installmentDates[row.index]" class="flex-1 text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg" />
```
and
```html
            <input type="date" v-model="form.recurringDates[row.index]" class="flex-1 text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg" />
```
Change both to:
```html
            <input type="date" :value="row.date" disabled class="flex-1 text-[13px] px-2.5 py-1.5 border border-slate-200 rounded-lg bg-slate-100 text-slate-500" />
```

- [ ] **Step 10: Add the form error banner to the desktop modal**

Currently:
```html
      <div class="flex gap-3">
        <button type="button" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer" @click="submitForm">
          <Icon name="plus" :stroke-width="2" />Ajouter
        </button>
        <button type="button" class="inline-flex items-center gap-1.5 bg-transparent text-slate-600 border border-slate-200 rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50" @click="closeForm">
          <Icon name="close" :stroke-width="2" />Annuler
        </button>
      </div>
    </ModalSheet>
```
(this is the desktop modal's closing block — note it's followed by `</ModalSheet>`, distinguishing it from the mobile one edited in Step 6). Add the error banner:
```html
      <div v-if="formError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">
        {{ formError }}
      </div>
      <div class="flex gap-3">
        <button type="button" class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer" @click="submitForm">
          <Icon name="plus" :stroke-width="2" />Ajouter
        </button>
        <button type="button" class="inline-flex items-center gap-1.5 bg-transparent text-slate-600 border border-slate-200 rounded-lg px-4.5 py-2.5 text-sm font-semibold cursor-pointer hover:bg-slate-50" @click="closeForm">
          <Icon name="close" :stroke-width="2" />Annuler
        </button>
      </div>
    </ModalSheet>
```

- [ ] **Step 11: Update the desktop transaction table rows**

Currently:
```html
        <button
          type="button"
          class="w-[22px] h-[22px] rounded-md border-[1.5px] text-white text-[13px] font-bold flex items-center justify-center cursor-pointer p-0"
          :class="t.reconciled ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
          aria-label="Basculer pointée"
          @click="store.toggleReconciled(t.id)"
        >{{ t.reconciled ? '✓' : '' }}</button>
        <span class="text-[13px] text-slate-500">{{ t.dateLabel }}</span>
        <span class="text-sm font-semibold">{{ t.label }}</span>
        <span class="flex gap-1.5 flex-wrap">
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ t.category }}</span>
          <span v-if="t.hasLink" :title="t.linkTitle" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-yellow-100 text-yellow-800">{{ t.linkLabel }}</span>
        </span>
        <span class="text-[13px] text-slate-500">{{ t.account }}</span>
```
Change to:
```html
        <button
          type="button"
          class="w-[22px] h-[22px] rounded-md border-[1.5px] text-white text-[13px] font-bold flex items-center justify-center cursor-pointer p-0"
          :class="t.reconciled ? 'border-indigo-600 bg-indigo-600' : 'border-slate-300 bg-white'"
          aria-label="Basculer pointée"
          @click="toggleReconciled(t.id)"
        >{{ t.reconciled ? '✓' : '' }}</button>
        <span class="text-[13px] text-slate-500">{{ t.dateLabel }}</span>
        <span class="text-sm font-semibold">{{ t.label }}</span>
        <span class="flex gap-1.5 flex-wrap">
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700">{{ t.categoryName }}</span>
          <span v-if="t.hasLink" :title="t.linkTitle" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-yellow-100 text-yellow-800">{{ t.linkLabel }}</span>
        </span>
        <span class="text-[13px] text-slate-500">{{ t.accountName }}</span>
```

- [ ] **Step 12: Manual verification**

Navigate to `/transactions`. Expected: the 10 seeded transactions render for the current period with correct running balances matching what `TransactionListTest` verifies server-side. Create a simple expense; expected: it appears at the top of the list (newest first) after the store refetches. Create an installment transaction (e.g. 3x) and check `php artisan tinker --execute="echo App\Models\Transaction::where('series_kind', 'installment')->count();"` from `apps/api` afterward — expected: 3 new rows exist with a shared `series_id`. Toggle reconciled on a row; expected: the checkbox and running balances for that account update after the API round-trip.

- [ ] **Step 13: Commit**

```bash
git add apps/web/src/views/TransactionsView.vue
git commit -m "feat(web): wire TransactionsView to the transactions API"
```

---

## Task 18: Wire `DashboardView.vue`

**Files:**
- Modify: `apps/web/src/views/DashboardView.vue`

Replaces the hardcoded `PERIODS` object with real `dashboard`/`accounts` store data. The dashboard always needs both `current` and `previous` period summaries for the trend badge, regardless of which period the dropdown is showing — the `dashboard` store's per-period cache (Task 11) makes that cheap.

- [ ] **Step 1: Replace the script block**

Current script (lines 1–174) — replace entirely with:
```js
import { ref, computed, onMounted, watch } from 'vue';
import { useDashboardStore } from '../stores/dashboard';
import { useAccountsStore } from '../stores/accounts';
import { useCategoriesStore } from '../stores/categories';
import { eur } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import Icon from '../components/Icon.vue';

const dashboardStore = useDashboardStore();
const accountsStore = useAccountsStore();
const categoriesStore = useCategoriesStore();
const isMobile = useIsMobile();

const period = ref('current');

onMounted(async () => {
  await Promise.all([
    accountsStore.fetch(),
    categoriesStore.fetch(),
    dashboardStore.fetch('current'),
    dashboardStore.fetch('previous'),
  ]);
});
watch(period, (p) => dashboardStore.fetch(p));

const data = computed(() => dashboardStore.byPeriod[period.value] || { bars: [], categories: [] });

function namedCategories(categories) {
  return categories.map((c) => ({
    name: categoriesStore.byId[c.category_id]?.name ?? '—',
    color: categoriesStore.byId[c.category_id]?.color_hex ?? '#94a3b8',
    amount: c.amount,
  }));
}

const accountsWithLabel = computed(() =>
  accountsStore.items.map((a) => ({
    ...a,
    typeLabel: a.type === 'savings' ? 'Épargne' : 'Compte courant',
    balanceLabel: eur(a.balance),
    hasPending: Math.abs(a.pending_encours) > 0.005,
    pendingLabel: `${a.pending_encours >= 0 ? '+' : ''}${eur(a.pending_encours)} non pointé`,
  }))
);
const totalBalance = computed(() => accountsWithLabel.value.reduce((s, a) => s + a.balance, 0));

const showAll = ref(false);
const visibleAccounts = computed(() => (showAll.value ? accountsWithLabel.value : accountsWithLabel.value.slice(0, 3)));
const hasMoreAccounts = computed(() => accountsWithLabel.value.length > 3);
const toggleLabel = computed(() => (showAll.value ? 'Voir moins' : `Voir plus (${accountsWithLabel.value.length - 3})`));

// Desktop bar + net-line chart
const chart = computed(() => {
  const bars = data.value.bars;
  if (!bars.length) return { bars: [], netPolylinePoints: '' };
  const maxTotal = Math.max(...bars.map((b) => b.income + b.expense), 1);
  const scale = 260 / maxTotal;
  const points = bars.map((b, i) => {
    const net = b.income - b.expense;
    return {
      label: b.label,
      incomeH: Math.round(b.income * scale),
      expenseH: Math.round(b.expense * scale),
      leftPct: ((i + 0.5) / bars.length) * 100,
      netDotTop: Math.round(260 - net * scale) - 4,
    };
  });
  const netPolylinePoints = bars
    .map((b, i) => {
      const net = b.income - b.expense;
      const x = ((i + 0.5) / bars.length) * 100;
      const y = 260 - net * scale;
      return `${x},${y}`;
    })
    .join(' ');
  return { bars: points, netPolylinePoints };
});

// Desktop category donut (all categories, with legend)
const categoryChart = computed(() => {
  const named = namedCategories(data.value.categories);
  const catTotal = named.reduce((s, c) => s + c.amount, 0);
  const sorted = [...named].sort((a, b) => b.amount - a.amount);
  let cum = 0;
  const gradientParts = [];
  const categories = sorted.map((c) => {
    const pct = catTotal > 0 ? (c.amount / catTotal) * 100 : 0;
    const start = cum;
    cum += pct;
    gradientParts.push(`${c.color} ${start.toFixed(2)}% ${cum.toFixed(2)}%`);
    return { name: c.name, color: c.color, amountLabel: eur(c.amount, 0), pctLabel: `${Math.round(pct)}%` };
  });
  return { categories, donutGradient: `conic-gradient(${gradientParts.join(', ')})`, categoryTotalLabel: eur(catTotal, 0) };
});

// Desktop trend badge: current vs previous month spending, independent of the selected period
const trend = computed(() => {
  const cur = dashboardStore.byPeriod.current;
  const prev = dashboardStore.byPeriod.previous;
  if (!cur || !prev) return { label: '', positive: true };
  const curExpense = cur.categories.reduce((s, c) => s + c.amount, 0);
  const prevExpense = prev.categories.reduce((s, c) => s + c.amount, 0);
  if (prevExpense === 0) return { label: '', positive: true };
  const trendPct = ((prevExpense - curExpense) / prevExpense) * 100;
  const positive = trendPct >= 0;
  return { label: `${positive ? '-' : '+'}${Math.abs(trendPct).toFixed(1)}% de dépenses vs mois dernier`, positive };
});

// Mobile summary: totals + top categories for the selected period
const mobileSummary = computed(() => {
  const bars = data.value.bars;
  const income = bars.reduce((s, b) => s + b.income, 0);
  const expense = bars.reduce((s, b) => s + b.expense, 0);
  const maxIE = Math.max(income, expense, 1);
  const named = namedCategories(data.value.categories);
  const catTotal = named.reduce((s, c) => s + c.amount, 0);
  const sorted = [...named].sort((a, b) => b.amount - a.amount);
  let cum = 0;
  const gradientParts = [];
  const topCategories = sorted.slice(0, 4).map((c) => {
    const pct = catTotal > 0 ? (c.amount / catTotal) * 100 : 0;
    const start = cum;
    cum += pct;
    gradientParts.push(`${c.color} ${start.toFixed(1)}% ${cum.toFixed(1)}%`);
    return { name: c.name, color: c.color, pctLabel: `${Math.round(pct)}%` };
  });
  if (cum < 100) gradientParts.push(`#e2e8f0 ${cum.toFixed(1)}% 100%`);
  const surplusPositive = income >= expense;
  return {
    incomeLabel: eur(income, 0),
    expenseLabel: eur(expense, 0),
    incomeBarPct: ((income / maxIE) * 100).toFixed(1),
    expenseBarPct: ((expense / maxIE) * 100).toFixed(1),
    donutGradient: `conic-gradient(${gradientParts.join(', ')})`,
    topCategories,
    trendLabelShort: surplusPositive ? 'Épargne +' : 'Déficit',
    trendPositive: surplusPositive,
  };
});
```

- [ ] **Step 2: Update the trend badge bindings**

The mobile header currently reads:
```html
        <span
          class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
          :class="mobileSummary.trendPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
        >{{ mobileSummary.trendLabelShort }}</span>
```
This one is unaffected (it already reads from `mobileSummary`, unchanged). The desktop trend badge currently reads:
```html
      <span
        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
        :class="trendPositive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
      >{{ trendLabel }}</span>
```
Change to read from the new `trend` computed:
```html
      <span
        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
        :class="trend.positive ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
      >{{ trend.label }}</span>
```

- [ ] **Step 3: Update the account type badge in both card templates**

Mobile and desktop account cards both currently render `{{ acc.type }}` in the badge — for example the desktop one:
```html
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-indigo-50 text-indigo-700 whitespace-nowrap">{{ acc.type }}</span>
```
Change both occurrences to `{{ acc.typeLabel }}`.

- [ ] **Step 4: Remove the IBAN line (no longer a full fake string) in the desktop account card**

Currently:
```html
            <div class="text-xs text-slate-400">{{ acc.iban }}</div>
```
Change to (same pattern as `ComptesView`, Task 15 Step 3):
```html
            <div v-if="acc.iban_last4" class="text-xs text-slate-400">IBAN se terminant par {{ acc.iban_last4 }}</div>
```

- [ ] **Step 5: Manual verification**

Navigate to `/dashboard`. Expected: total balance and account cards match `/comptes`. Bar chart and category donut match the `dashboard/summary` JSON verified earlier via curl (Logement 780€, Alimentation 64.20€, etc. for the current period). Switch the period dropdown to "Mois dernier" / "Cette année"; expected: chart and donut update (the "Personnalisé" option has no corresponding API period and will fall back to whatever `data` resolves to when `byPeriod['custom']` is undefined — `{ bars: [], categories: [] }` — this is acceptable since the original mock also aliased `custom` to `current`'s data; if this is confusing during manual testing, it's a known limitation, not a regression to fix in this task).

- [ ] **Step 6: Commit**

```bash
git add apps/web/src/views/DashboardView.vue
git commit -m "feat(web): wire DashboardView to the dashboard summary API"
```

---

## Task 19: Wire `ProjectionView.vue`

**Files:**
- Modify: `apps/web/src/views/ProjectionView.vue`

The API's `history`/`projection` response already matches what this view computed client-side (same 3-month lookback, same compound-interest formula) — the client-side compounding loop is deleted entirely.

- [ ] **Step 1: Replace the script block**

Current script (lines 1–67) — replace entirely with:
```js
import { ref, computed, onMounted, watch } from 'vue';
import { useProjectionStore } from '../stores/projection';
import { useSettingsStore } from '../stores/settings';
import { eur, TODAY } from '../lib/format';
import { useIsMobile } from '../lib/useIsMobile';
import EditableAmount from '../components/EditableAmount.vue';
import { ApiError } from '../lib/api';

const projectionStore = useProjectionStore();
const settingsStore = useSettingsStore();
const isMobile = useIsMobile();

const horizon = ref(12);
const settingsError = ref('');

onMounted(async () => {
  await Promise.all([settingsStore.fetch(), projectionStore.fetch(horizon.value)]);
});
watch(horizon, (h) => projectionStore.fetch(h));

const historyPoints = computed(() => projectionStore.history.map((p) => ({ monthOffset: p.month_offset, value: p.balance })));
const currentSavings = computed(() => (historyPoints.value.length ? historyPoints.value[historyPoints.value.length - 1].value : 0));
const projected = computed(() => projectionStore.projection);

const svgDims = computed(() => (isMobile.value ? { w: 320, h: 150, pad: 6 } : { w: 600, h: 260, pad: 8 }));

const chart = computed(() => {
  const { w, h, pad } = svgDims.value;
  const history = historyPoints.value;
  if (!history.length || !projected.value.length) return { w, h, historyLinePoints: '', projectionLinePoints: '', areaPoints: '', todayX: '0', axisLabels: [] };
  const historyStart = history[0].monthOffset;
  const totalSpanMonths = horizon.value - historyStart;
  const allValues = [...history.map((p) => p.value), ...projected.value];
  const maxVal = Math.max(...allValues, 1);
  const minVal = Math.min(...allValues, 0);
  const range = maxVal - minVal || 1;
  const xFor = (mo) => ((mo - historyStart) / totalSpanMonths) * w;
  const yFor = (v) => h - pad - ((v - minVal) / range) * (h - 2 * pad);

  const historyXY = history.map((p) => [xFor(p.monthOffset), yFor(p.value)]);
  const historyLinePoints = historyXY.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const projectionXY = projected.value.map((v, i) => [xFor(i), yFor(v)]);
  const projectionLinePoints = projectionXY.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ');
  const fullXY = [...historyXY, ...projectionXY];
  const areaPoints = `0,${h} ` + fullXY.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(' ') + ` ${w},${h}`;
  const todayX = xFor(0).toFixed(1);

  const axisLabels = [historyStart, Math.round(historyStart / 2), 0, Math.round(horizon.value / 2), horizon.value].map((off) => {
    const d = new Date(TODAY.getFullYear(), TODAY.getMonth() + off, 1);
    return d.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
  });

  return { w, h, historyLinePoints, projectionLinePoints, areaPoints, todayX, axisLabels };
});

const finalAmountLabel = computed(() => (projected.value.length ? eur(projected.value[horizon.value], 0) : eur(0, 0)));
const milestone6Label = computed(() => (projected.value.length ? eur(projected.value[Math.min(6, horizon.value)], 0) : eur(0, 0)));
const milestone12Label = computed(() => (projected.value.length ? eur(projected.value[Math.min(12, horizon.value)], 0) : eur(0, 0)));
const totalContributedLabel = computed(() => eur(settingsStore.monthlySavingsContribution * horizon.value, 0));
const contributionColor = computed(() => (settingsStore.monthlySavingsContribution >= 0 ? 'text-slate-900' : 'text-red-600'));

async function updateContribution(value) {
  settingsError.value = '';
  try {
    await settingsStore.update({ monthlySavingsContribution: value });
    await projectionStore.fetch(horizon.value);
  } catch (e) {
    settingsError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}
async function updateRate(value) {
  settingsError.value = '';
  try {
    await settingsStore.update({ annualReturnRate: value });
    await projectionStore.fetch(horizon.value);
  } catch (e) {
    settingsError.value = e instanceof ApiError ? (e.errors ? Object.values(e.errors).flat()[0] : e.message) : 'Une erreur est survenue.';
  }
}
```

- [ ] **Step 2: Update the mobile contribution/rate editors**

Currently:
```html
            <EditableAmount
              :model-value="store.monthlyContribution"
              :display="`${store.monthlyContribution >= 0 ? '+' : ''}${eur(store.monthlyContribution, 0)}/m`"
              compact
              :step="10"
              @update:model-value="(v) => (store.monthlyContribution = v)"
            />
          </div>
          <div class="flex-1">
            <div class="text-[10px] text-slate-500 mb-1">Taux annuel</div>
            <EditableAmount
              :model-value="store.annualRate"
              :display="`${store.annualRate}%`"
              compact
              :step="0.1"
              min="0"
              @update:model-value="(v) => (store.annualRate = v)"
            />
```
Change to:
```html
            <EditableAmount
              :model-value="settingsStore.monthlySavingsContribution"
              :display="`${settingsStore.monthlySavingsContribution >= 0 ? '+' : ''}${eur(settingsStore.monthlySavingsContribution, 0)}/m`"
              compact
              :step="10"
              @update:model-value="updateContribution"
            />
          </div>
          <div class="flex-1">
            <div class="text-[10px] text-slate-500 mb-1">Taux annuel</div>
            <EditableAmount
              :model-value="settingsStore.annualReturnRate"
              :display="`${settingsStore.annualReturnRate}%`"
              compact
              :step="0.1"
              min="0"
              @update:model-value="updateRate"
            />
```

- [ ] **Step 3: Update the desktop contribution/rate editors**

Currently:
```html
        <EditableAmount
          :model-value="store.monthlyContribution"
          :display="`${store.monthlyContribution >= 0 ? '+' : ''}${eur(store.monthlyContribution, 0)} / mois`"
          suffix="€ / mois"
          :step="10"
          :value-class="contributionColor"
          @update:model-value="(v) => (store.monthlyContribution = v)"
        />
      </div>
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Taux de rendement annuel</div>
        <EditableAmount
          :model-value="store.annualRate"
          :display="`${store.annualRate}%`"
          suffix="%"
          :step="0.1"
          min="0"
          @update:model-value="(v) => (store.annualRate = v)"
        />
```
Change to:
```html
        <EditableAmount
          :model-value="settingsStore.monthlySavingsContribution"
          :display="`${settingsStore.monthlySavingsContribution >= 0 ? '+' : ''}${eur(settingsStore.monthlySavingsContribution, 0)} / mois`"
          suffix="€ / mois"
          :step="10"
          :value-class="contributionColor"
          @update:model-value="updateContribution"
        />
      </div>
      <div>
        <div class="text-[13px] font-semibold text-slate-500 mb-1.5">Taux de rendement annuel</div>
        <EditableAmount
          :model-value="settingsStore.annualReturnRate"
          :display="`${settingsStore.annualReturnRate}%`"
          suffix="%"
          :step="0.1"
          min="0"
          @update:model-value="updateRate"
        />
```

- [ ] **Step 4: Update the bottom contribution editor (mobile-shared block near the bottom of the template)**

There's a third `EditableAmount` for `monthlyContribution`/`annualRate` further down the template (lines ~148–166 in the original), used in a shared summary block. It currently reads:
```html
        <EditableAmount
          :model-value="store.monthlyContribution"
          :display="`${store.monthlyContribution >= 0 ? '+' : ''}${eur(store.monthlyContribution, 0)} / mois`"
          suffix="€ / mois"
          :step="10"
          :value-class="contributionColor"
          @update:model-value="(v) => (store.monthlyContribution = v)"
        />
```
This is the same block already handled in Step 3 — check the file after Step 3's edit; if a duplicate block remains unconverted (the original file has one instance in the mobile section and one in the desktop `<main>` section, both edited by Steps 2 and 3 respectively), no further action is needed. Confirm with `grep -n "store\." apps/web/src/views/ProjectionView.vue` — expected: no matches remain.

- [ ] **Step 5: Add an error banner for failed inline edits**

Mobile header currently reads:
```html
    <header class="px-5 pt-4 pb-3 bg-white border-b border-slate-200">
      <div class="text-[19px] font-extrabold tracking-tight mb-1">Projection</div>
      <div class="text-xs text-slate-500">Épargne actuelle : {{ eur(currentSavings, 0) }}</div>
    </header>
```
Add the error banner as the first child of `<main>` right after it:
```html
    <main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">
      <div v-if="settingsError" class="bg-red-50 text-red-700 text-xs font-semibold rounded-lg px-2.5 py-2 mb-3.5">{{ settingsError }}</div>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5">
```
(the existing `<main class="flex-1 overflow-y-auto px-4 pt-3.5 pb-6">` opening tag and its first child `<div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 mb-3.5">` are unchanged except for the inserted banner between them.)

Desktop intro currently reads:
```html
    <h1 class="m-0 mb-2 text-[28px] font-bold tracking-tight">Projection d'épargne</h1>
    <p class="mt-0 mb-7 text-sm text-slate-500">Historique réel des soldes d'épargne (tous comptes de type Épargne) et projection selon votre effort mensuel et un taux de rendement.</p>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
```
Insert the banner between the paragraph and the summary card:
```html
    <h1 class="m-0 mb-2 text-[28px] font-bold tracking-tight">Projection d'épargne</h1>
    <p class="mt-0 mb-7 text-sm text-slate-500">Historique réel des soldes d'épargne (tous comptes de type Épargne) et projection selon votre effort mensuel et un taux de rendement.</p>

    <div v-if="settingsError" class="bg-red-50 text-red-700 text-[13px] font-semibold rounded-lg px-3 py-2.5 mb-4">{{ settingsError }}</div>

    <div class="flex items-center gap-6 flex-wrap bg-white border border-slate-200 rounded-2xl shadow-sm px-7 py-6 mb-7">
```

- [ ] **Step 6: Manual verification**

Navigate to `/projection`. Expected: "Épargne actuelle" matches the `savings-projection` curl output from the earlier session (11 170,75 €), and the 12-month projection curve matches too. Edit the monthly contribution or annual rate; expected: the chart redraws with the new compounding curve after the settings update + projection refetch. Change the horizon dropdown to 24 months; expected: the curve extends and `finalAmountLabel` updates.

- [ ] **Step 7: Commit**

```bash
git add apps/web/src/views/ProjectionView.vue
git commit -m "feat(web): wire ProjectionView to the projection and settings APIs"
```

---

## Task 20: Add logout to `AppShell.vue`

**Files:**
- Modify: `apps/web/src/components/AppShell.vue`

AppShell only renders a persistent header on desktop (`only-desktop` nav); mobile views each render their own header with no shared chrome. This task adds a logout affordance to the desktop nav only — sufficient for the manual verification pass, and consistent with not inventing new mobile chrome beyond what this task needs.

- [ ] **Step 1: Add the auth store and a logout handler**

Current script block:
```js
import { useRoute, useRouter } from 'vue-router';
import Icon from './Icon.vue';

const route = useRoute();
const router = useRouter();

const NAV_ITEMS = [
  { to: '/dashboard', icon: 'home', label: 'Tableau de bord', mobileLabel: 'Accueil' },
  { to: '/transactions', icon: 'swap', label: 'Transactions', mobileLabel: 'Trans.' },
  { to: '/budgets', icon: 'pie', label: 'Budgets', mobileLabel: 'Budgets' },
  { to: '/comptes', icon: 'wallet', label: 'Comptes', mobileLabel: 'Comptes' },
  { to: '/dettes', icon: 'debt', label: 'Dettes', desktopOnly: true },
  { to: '/projection', icon: 'projection', label: 'Projection', mobileLabel: 'Projet.' },
];
const mobileItems = NAV_ITEMS.filter((i) => !i.desktopOnly);

function isActive(to) {
  return route.path === to;
}

function openNewTransaction() {
  router.push({ path: '/transactions', query: { new: String(Date.now()) } });
}
```
Replace with:
```js
import { useRoute, useRouter } from 'vue-router';
import Icon from './Icon.vue';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const NAV_ITEMS = [
  { to: '/dashboard', icon: 'home', label: 'Tableau de bord', mobileLabel: 'Accueil' },
  { to: '/transactions', icon: 'swap', label: 'Transactions', mobileLabel: 'Trans.' },
  { to: '/budgets', icon: 'pie', label: 'Budgets', mobileLabel: 'Budgets' },
  { to: '/comptes', icon: 'wallet', label: 'Comptes', mobileLabel: 'Comptes' },
  { to: '/dettes', icon: 'debt', label: 'Dettes', desktopOnly: true },
  { to: '/projection', icon: 'projection', label: 'Projection', mobileLabel: 'Projet.' },
];
const mobileItems = NAV_ITEMS.filter((i) => !i.desktopOnly);

function isActive(to) {
  return route.path === to;
}

function openNewTransaction() {
  router.push({ path: '/transactions', query: { new: String(Date.now()) } });
}

async function logout() {
  await authStore.logout();
  router.push({ name: 'login' });
}
```

- [ ] **Step 2: Add the logout button to the desktop nav**

Currently:
```html
      <button
        type="button"
        class="ml-auto inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm cursor-pointer"
        @click="openNewTransaction"
      >
        <Icon name="plus" :stroke-width="2" />Nouvelle transaction
      </button>
    </nav>
```
Change to add a logout link before the "Nouvelle transaction" button:
```html
      <button
        type="button"
        class="ml-auto inline-flex items-center gap-1.5 bg-transparent text-slate-500 hover:text-slate-900 text-sm font-medium cursor-pointer"
        @click="logout"
      >
        Déconnexion
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm cursor-pointer"
        @click="openNewTransaction"
      >
        <Icon name="plus" :stroke-width="2" />Nouvelle transaction
      </button>
    </nav>
```
(Note `ml-auto` moves from the "Nouvelle transaction" button to the new "Déconnexion" button, since it's now the first of the two right-aligned items.)

- [ ] **Step 3: Manual verification**

On desktop viewport, log in, click "Déconnexion" in the top nav. Expected: redirected to `/login`. Try navigating directly to `http://localhost:5173/dashboard` afterward. Expected: bounced back to `/login` (router guard catches the missing session).

- [ ] **Step 4: Commit**

```bash
git add apps/web/src/components/AppShell.vue
git commit -m "feat(web): add logout to the desktop nav"
```

---

## Task 21: Delete the mock store

**Files:**
- Delete: `apps/web/src/stores/ledger.js`

By this point no view imports `useLedgerStore` — verify before deleting.

- [ ] **Step 1: Confirm no remaining references**

Run: `cd apps/web && grep -rn "stores/ledger\|useLedgerStore" src`
Expected: no output.

- [ ] **Step 2: Delete the file**

```bash
git rm apps/web/src/stores/ledger.js
```

- [ ] **Step 3: Commit**

```bash
git commit -m "chore(web): remove mock ledger store"
```

---

## Task 22: Full manual end-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Backend regression check**

```bash
cd apps/api
php artisan test
```
Expected: `41 passed` (no backend files changed in this plan, so this should be unaffected — this step catches any accidental backend edit).

- [ ] **Step 2: Fresh-state walkthrough**

```bash
cd apps/api
php artisan migrate:fresh --seed
php artisan serve --port=8000   # separate terminal, leave running
```
```bash
cd apps/web
npm run dev   # separate terminal, leave running
```

Open `http://localhost:5173` in a browser (Chrome DevTools MCP or manual) and walk through:
1. Land on `/login` (not logged in yet).
2. Register a new account (name/email/password ≥ 8 chars) → redirected to `/dashboard`, showing an empty state (new user has no seed data — `DemoDataSeeder` only ever ran for `demo@comptastic.test`).
3. Log out via the desktop nav → redirected to `/login`.
4. Log in as `demo@comptastic.test` / `password` → redirected to `/dashboard`, now showing the seeded data (5 accounts, 10 transactions, 3 debts, 6 budget rows).
5. `/comptes`: create a new account. Confirm it appears immediately and reload the page to confirm it persisted server-side.
6. `/transactions`: create a simple expense, then an installment (3x) transaction. Confirm both appear; confirm the installment created 3 rows (spot-check via `php artisan tinker` as in Task 17 Step 12). Toggle a row's reconciled checkbox and confirm the account's running balance shifts accordingly.
7. `/dettes`: create a new debt. Confirm progress bar and "mensualité(s) restante(s)" render correctly.
8. `/budgets`: edit a category's budget amount and the monthly income. Confirm the bar/status update and the "Répartition du revenu" section recomputes.
9. `/projection`: edit the monthly contribution and annual rate. Confirm the chart curve changes. Switch the horizon dropdown and confirm the curve extends.
10. Reload the browser at each of the above routes directly (not via in-app navigation) to confirm the session persists and each view refetches its own data rather than relying on stale client state.

- [ ] **Step 3: Confirm no stray references to the old mock exports remain**

Run: `cd apps/web && grep -rn "CAT_COLORS\|CAT_COLOR_LIST\|SEED_" src`
Expected: no output (both were only ever defined in the now-deleted `stores/ledger.js`).

- [ ] **Step 4: Final commit (if any cleanup was needed)**

If Steps 1–3 required fixes, commit them individually with descriptive messages as you go, following the same `git add <specific files> && git commit -m "fix(web): ..."` pattern used throughout this plan — do not batch unrelated fixes into one commit.
