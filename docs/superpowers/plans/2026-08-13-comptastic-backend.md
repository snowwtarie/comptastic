# Comptastic Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel + PostgreSQL API described in `docs/backend-spec.md`, replacing the mocked Pinia store (`apps/web/src/stores/ledger.js`) with a real, per-user persisted backend.

**Architecture:** A monorepo with the existing Vue app moved to `apps/web/` and a new Laravel 12 app in `apps/api/`, talking over a session-based Sanctum SPA API. Every business table (`accounts`, `transactions`, `debts`, `budgets`, `user_settings`) is scoped to the authenticated user via a shared Eloquent trait + global scope, so every controller gets per-user isolation for free. Money is stored as integer cents everywhere in the database and only converted to euro floats at the API Resource boundary.

**Tech Stack:** Laravel 12, PHP 8.3+, PostgreSQL 16, Laravel Sanctum (SPA/cookie mode), Pest for tests, Eloquent + Form Requests + API Resources.

---

## Prerequisites

- PHP 8.3+, Composer, and a local PostgreSQL 16 instance reachable with a database you can create (e.g. `createdb comptastic`).
- The existing repo at `/Users/marius/Code/comptastic` (the Vue app currently lives at its root).
- All commands below assume you run them from the repo root unless a step says `cd apps/api` first — once you `cd`, stay there for the rest of that task's steps.

## File Structure

```
comptastic/
├── apps/
│   ├── web/                          # existing Vue app, moved here in Task 1
│   └── api/                          # new Laravel app, created in Task 2
│       ├── app/
│       │   ├── Http/
│       │   │   ├── Controllers/Api/
│       │   │   │   ├── AuthController.php
│       │   │   │   ├── CategoryController.php
│       │   │   │   ├── AccountController.php
│       │   │   │   ├── DebtController.php
│       │   │   │   ├── TransactionController.php
│       │   │   │   ├── BudgetController.php
│       │   │   │   ├── SettingController.php
│       │   │   │   ├── SavingsProjectionController.php
│       │   │   │   └── DashboardController.php
│       │   │   ├── Requests/
│       │   │   │   ├── StoreAccountRequest.php
│       │   │   │   ├── StoreDebtRequest.php
│       │   │   │   ├── UpdateDebtRequest.php
│       │   │   │   ├── StoreTransactionRequest.php
│       │   │   │   ├── UpdateTransactionRequest.php
│       │   │   │   ├── UpdateBudgetRequest.php
│       │   │   │   └── UpdateSettingsRequest.php
│       │   │   └── Resources/
│       │   │       ├── CategoryResource.php
│       │   │       ├── AccountResource.php
│       │   │       ├── DebtResource.php
│       │   │       ├── TransactionResource.php
│       │   │       └── SettingsResource.php
│       │   ├── Models/
│       │   │   ├── Concerns/BelongsToUser.php
│       │   │   ├── Scopes/ForCurrentUser.php
│       │   │   ├── User.php                  # modified incrementally
│       │   │   ├── Category.php
│       │   │   ├── Account.php
│       │   │   ├── Debt.php
│       │   │   ├── Transaction.php
│       │   │   ├── Budget.php
│       │   │   └── UserSetting.php
│       │   └── Services/
│       │       ├── AccountBalanceCalculator.php
│       │       ├── TransactionRunningBalanceCalculator.php
│       │       ├── TransactionSeriesGenerator.php
│       │       ├── BudgetAggregator.php
│       │       ├── SavingsProjectionCalculator.php
│       │       └── DashboardSummaryBuilder.php
│       ├── database/
│       │   ├── migrations/*
│       │   ├── factories/{Category,Account,Debt,Transaction}Factory.php
│       │   └── seeders/{CategorySeeder,DemoDataSeeder,DatabaseSeeder}.php
│       ├── routes/api.php
│       └── tests/Feature/*, tests/Unit/*
```

---

### Task 1: Monorepo restructure — move the Vue app to `apps/web`

**Files:**
- Move: everything currently at repo root (`src/`, `index.html`, `package.json`, `vite.config.js`, `bun.lock`, `.gitignore`, `README.md`, `docs/`) except `.git`
- Create: `apps/web/` (destination)

- [ ] **Step 1: Create the target directory and move files with git so history is preserved**

```bash
mkdir -p apps/web
git mv src apps/web/src
git mv index.html apps/web/index.html
git mv package.json apps/web/package.json
git mv vite.config.js apps/web/vite.config.js
git mv bun.lock apps/web/bun.lock
git mv .gitignore apps/web/.gitignore
```

`docs/` and `README.md` stay at the repo root — they already describe the whole
project, not just the frontend.

- [ ] **Step 2: Verify the app still builds from its new location**

```bash
cd apps/web && bun install && bun run build && cd ../..
```

Expected: build succeeds, `apps/web/dist/` is produced.

- [ ] **Step 3: Update README.md references to the old root paths**

Open `README.md` and replace any `bun install` / `bun run dev` instructions
that assumed the repo root with instructions that `cd apps/web` first. Add a
one-line pointer to `docs/backend-spec.md` for the API side.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: move frontend into apps/web for monorepo layout"
```

---

### Task 2: Bootstrap the Laravel API skeleton

**Files:**
- Create: `apps/api/` (full Laravel 12 skeleton via Composer)
- Modify: `apps/api/.env`, `apps/api/config/cors.php`, `apps/api/bootstrap/app.php`

- [ ] **Step 1: Create the Laravel project**

```bash
composer create-project laravel/laravel apps/api "^12.0"
cd apps/api
composer require laravel/sanctum
```

- [ ] **Step 2: Point `.env` at PostgreSQL and configure Sanctum SPA settings**

Edit `apps/api/.env`, replacing the `DB_*` block and adding the lines below:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=comptastic
DB_USERNAME=postgres
DB_PASSWORD=

SESSION_DRIVER=cookie
SESSION_DOMAIN=localhost
SANCTUM_STATEFUL_DOMAINS=localhost:5173
FRONTEND_URL=http://localhost:5173
```

- [ ] **Step 3: Allow the Vue dev server to call the API with credentials**

Edit `apps/api/config/cors.php`:

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

- [ ] **Step 4: Register Sanctum's stateful-request middleware**

Edit `apps/api/bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

- [ ] **Step 5: Create the database and verify connectivity**

```bash
createdb comptastic
php artisan migrate
```

Expected: the default Laravel migrations (`users`, `cache`, `jobs`, ...) run
without error — this confirms the PostgreSQL connection works before any
Comptastic-specific tables are added.

- [ ] **Step 6: Commit**

```bash
cd ../..
git add apps/api
git commit -m "chore: bootstrap Laravel API skeleton with Sanctum SPA + PostgreSQL"
```

Note for later frontend integration (not part of this backend plan): the SPA
must `GET /sanctum/csrf-cookie` once before calling `/api/login`, per Sanctum's
standard SPA flow.

---

### Task 3: Categories — read-only global reference data

**Files:**
- Create: `apps/api/database/migrations/xxxx_create_categories_table.php`
- Create: `apps/api/app/Models/Category.php`
- Create: `apps/api/database/factories/CategoryFactory.php`
- Create: `apps/api/database/seeders/CategorySeeder.php`
- Create: `apps/api/app/Http/Resources/CategoryResource.php`
- Create: `apps/api/app/Http/Controllers/Api/CategoryController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/CategoryTest.php`

- [ ] **Step 1: Create the migration**

```bash
cd apps/api
php artisan make:migration create_categories_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color_hex', 7);
            $table->boolean('is_income')->default(false);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 2: Create the model and factory**

```bash
php artisan make:model Category
php artisan make:factory CategoryFactory --model=Category
```

`app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color_hex', 'is_income', 'sort_order'];

    protected $casts = [
        'is_income' => 'boolean',
    ];
}
```

`database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'color_hex' => '#4f46e5',
            'is_income' => false,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
```

- [ ] **Step 3: Run the migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Write the failing feature test**

`tests/Feature/CategoryTest.php`:

```php
<?php

use App\Models\Category;
use App\Models\User;

it('lists categories ordered by sort_order', function () {
    Category::factory()->create(['name' => 'B', 'sort_order' => 1]);
    Category::factory()->create(['name' => 'A', 'sort_order' => 0]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/categories');

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('A');
    expect($response->json('data.1.name'))->toBe('B');
});
```

- [ ] **Step 5: Run the test, verify it fails**

```bash
php artisan test --filter=CategoryTest
```

Expected: FAIL — route `/api/categories` does not exist (404).

- [ ] **Step 6: Implement the resource, controller and route**

`app/Http/Resources/CategoryResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color_hex' => $this->color_hex,
            'is_income' => $this->is_income,
        ];
    }
}
```

`app/Http/Controllers/Api/CategoryController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(Category::orderBy('sort_order')->get());
    }
}
```

`routes/api.php` (create the file with this content — it will grow in later tasks):

```php
<?php

use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
});
```

- [ ] **Step 7: Run the test, verify it passes**

```bash
php artisan test --filter=CategoryTest
```

Expected: PASS.

- [ ] **Step 8: Create the seeder (data, not yet wired to `DatabaseSeeder`)**

`database/seeders/CategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Colors reproduce CAT_COLORS from apps/web/src/stores/ledger.js.
        // "Revenus" has no color in the frontend (it's never charted by
        // category color) — #16a34a picked here as a neutral placeholder.
        $categories = [
            ['name' => 'Revenus', 'color_hex' => '#16a34a', 'is_income' => true],
            ['name' => 'Logement', 'color_hex' => '#4338ca', 'is_income' => false],
            ['name' => 'Alimentation', 'color_hex' => '#4f46e5', 'is_income' => false],
            ['name' => 'Transport', 'color_hex' => '#6366f1', 'is_income' => false],
            ['name' => 'Loisirs', 'color_hex' => '#818cf8', 'is_income' => false],
            ['name' => 'Santé', 'color_hex' => '#a5b4fc', 'is_income' => false],
            ['name' => 'Autres', 'color_hex' => '#cbd5e1', 'is_income' => false],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [...$category, 'sort_order' => $index],
            );
        }
    }
}
```

- [ ] **Step 9: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add categories resource"
```

---

### Task 4: User scoping infrastructure + Accounts

**Files:**
- Create: `apps/api/app/Models/Scopes/ForCurrentUser.php`
- Create: `apps/api/app/Models/Concerns/BelongsToUser.php`
- Create: `apps/api/database/migrations/xxxx_create_accounts_table.php`
- Create: `apps/api/app/Models/Account.php`
- Create: `apps/api/database/factories/AccountFactory.php`
- Create: `apps/api/app/Services/AccountBalanceCalculator.php`
- Create: `apps/api/app/Http/Requests/StoreAccountRequest.php`
- Create: `apps/api/app/Http/Resources/AccountResource.php`
- Create: `apps/api/app/Http/Controllers/Api/AccountController.php`
- Modify: `apps/api/app/Models/User.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/AccountTest.php`
- Test: `apps/api/tests/Unit/AccountBalanceCalculatorTest.php`

- [ ] **Step 1: Create the global scope and trait every user-owned model will use**

`app/Models/Scopes/ForCurrentUser.php`:

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ForCurrentUser implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where($model->getTable().'.user_id', auth()->id());
        }
    }
}
```

`app/Models/Concerns/BelongsToUser.php`:

```php
<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ForCurrentUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes every query to the authenticated user's own rows and stamps
 * user_id automatically on create. Because implicit route-model binding
 * (e.g. `Debt $debt` in a controller method) runs through the model's
 * default query, a record belonging to another user resolves to a 404,
 * not a 403 — this is intentional (see docs/backend-spec.md §4).
 */
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope(new ForCurrentUser());

        static::creating(function (Model $model) {
            if (auth()->check() && ! $model->user_id) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 2: Create the accounts migration**

```bash
cd apps/api
php artisan make:migration create_accounts_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('bank')->nullable();
            $table->enum('type', ['checking', 'savings']);
            $table->string('iban_last4', 4)->nullable();
            $table->bigInteger('opening_balance_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

```bash
php artisan migrate
```

- [ ] **Step 3: Create the model, factory, and add the relation to `User`**

```bash
php artisan make:model Account
php artisan make:factory AccountFactory --model=Account
```

`app/Models/Account.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = ['name', 'bank', 'type', 'iban_last4', 'opening_balance_cents'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
```

`database/factories/AccountFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->company().' - Compte courant',
            'bank' => $this->faker->company(),
            'type' => 'checking',
            'iban_last4' => $this->faker->numerify('####'),
            'opening_balance_cents' => 0,
        ];
    }
}
```

Add to `app/Models/User.php` (inside the class, alongside existing methods):

```php
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
```

Add the matching `use App\Models\Account;`... not needed since `Account` is in
the same `App\Models` namespace — no import required.

- [ ] **Step 4: Write the failing unit test for the balance calculator**

`tests/Unit/AccountBalanceCalculatorTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use Carbon\Carbon;

it('sums only reconciled transactions up to the given date into the balance', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_balance_cents' => 10000]);
    $category = Category::factory()->create();

    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount_cents' => -2000, 'date' => '2026-08-01', 'reconciled' => true,
    ]);
    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount_cents' => -500, 'date' => '2026-08-02', 'reconciled' => false,
    ]);
    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount_cents' => 100000, 'date' => '2099-01-01', 'reconciled' => true,
    ]);

    $calculator = new AccountBalanceCalculator();

    expect($calculator->balanceAt($account, Carbon::parse('2026-08-06')))->toBe(8000);
    expect($calculator->pendingEncoursAt($account, Carbon::parse('2026-08-06')))->toBe(-500);
});
```

This test references `Transaction` and its factory, which don't exist yet —
that's expected; Task 6 creates them. Skip running this test for now and
come back to it at the end of Task 6 Step 4, where it will actually compile
and run. For this task, focus on Steps 5-9 below (the account CRUD endpoints,
which don't depend on transactions existing).

- [ ] **Step 5: Write the failing feature test for the account endpoints**

`tests/Feature/AccountTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\User;

it('lists only the authenticated user\'s accounts with balance fields', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Mine', 'opening_balance_cents' => 5000]);
    Account::factory()->for($otherUser)->create(['name' => 'Not mine']);

    $response = $this->actingAs($user)->getJson('/api/accounts');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Mine');
    expect($response->json('data.0.balance'))->toBe(50.0);
});

it('creates an account owned by the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/accounts', [
        'name' => 'Nouveau compte',
        'bank' => 'BNP Paribas',
        'type' => 'checking',
        'opening_balance' => 123.45,
    ]);

    $response->assertCreated();
    expect($response->json('data.opening_balance'))->toBe(123.45);
    expect(Account::first()->user_id)->toBe($user->id);
});
```

- [ ] **Step 6: Run the tests, verify they fail**

```bash
php artisan test --filter=AccountTest
```

Expected: FAIL — routes don't exist yet.

- [ ] **Step 7: Implement the service, request, resource, and controller**

`app/Services/AccountBalanceCalculator.php`:

```php
<?php

namespace App\Services;

use App\Models\Account;
use Carbon\CarbonInterface;

class AccountBalanceCalculator
{
    public function balanceAt(Account $account, CarbonInterface $asOf): int
    {
        $reconciledSum = $account->transactions()
            ->where('reconciled', true)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');

        return $account->opening_balance_cents + (int) $reconciledSum;
    }

    public function pendingEncoursAt(Account $account, CarbonInterface $asOf): int
    {
        return (int) $account->transactions()
            ->where('reconciled', false)
            ->whereDate('date', '<=', $asOf)
            ->sum('amount_cents');
    }
}
```

`app/Http/Requests/StoreAccountRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:checking,savings'],
            'iban_last4' => ['nullable', 'string', 'size:4'],
            'opening_balance' => ['required', 'numeric'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $data['opening_balance_cents'] = (int) round($data['opening_balance'] * 100);
        unset($data['opening_balance']);

        return $data;
    }
}
```

`app/Http/Resources/AccountResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bank' => $this->bank,
            'type' => $this->type,
            'iban_last4' => $this->iban_last4,
            'opening_balance' => $this->opening_balance_cents / 100,
            'balance' => $this->balance_cents / 100,
            'pending_encours' => $this->pending_encours_cents / 100,
        ];
    }
}
```

`app/Http/Controllers/Api/AccountController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\AccountBalanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AccountController extends Controller
{
    public function __construct(private AccountBalanceCalculator $balances)
    {
    }

    public function index(Request $request)
    {
        $today = Carbon::today();

        $accounts = $request->user()->accounts()->get()->each(function (Account $account) use ($today) {
            $account->balance_cents = $this->balances->balanceAt($account, $today);
            $account->pending_encours_cents = $this->balances->pendingEncoursAt($account, $today);
        });

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request)
    {
        $account = $request->user()->accounts()->create($request->validated());
        $account->balance_cents = $account->opening_balance_cents;
        $account->pending_encours_cents = 0;

        return (new AccountResource($account))->response()->setStatusCode(201);
    }
}
```

`routes/api.php` (add inside the existing `auth:sanctum` group):

```php
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
```

Add the matching `use App\Http\Controllers\Api\AccountController;` at the top
of `routes/api.php`.

- [ ] **Step 8: Run the tests, verify they pass**

```bash
php artisan test --filter=AccountTest
```

Expected: PASS (2 tests).

- [ ] **Step 9: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add user-scoping infrastructure and accounts resource"
```

---

### Task 5: Debts

**Files:**
- Create: `apps/api/database/migrations/xxxx_create_debts_table.php`
- Create: `apps/api/app/Models/Debt.php`
- Create: `apps/api/database/factories/DebtFactory.php`
- Create: `apps/api/app/Http/Requests/StoreDebtRequest.php`
- Create: `apps/api/app/Http/Requests/UpdateDebtRequest.php`
- Create: `apps/api/app/Http/Resources/DebtResource.php`
- Create: `apps/api/app/Http/Controllers/Api/DebtController.php`
- Modify: `apps/api/app/Models/User.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/DebtTest.php`

Debts are created before transactions (Task 6) because `transactions.linked_debt_id`
will reference this table.

- [ ] **Step 1: Create the migration**

```bash
cd apps/api
php artisan make:migration create_debts_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('original_amount_cents');
            $table->bigInteger('remaining_amount_cents');
            $table->bigInteger('monthly_payment_cents');
            $table->integer('rate_bps');
            $table->date('end_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
```

```bash
php artisan migrate
```

- [ ] **Step 2: Create the model, factory, and `User` relation**

```bash
php artisan make:model Debt
php artisan make:factory DebtFactory --model=Debt
```

`app/Models/Debt.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Debt extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'name', 'original_amount_cents', 'remaining_amount_cents',
        'monthly_payment_cents', 'rate_bps', 'end_date',
    ];

    protected $casts = [
        'end_date' => 'date',
    ];
}
```

`database/factories/DebtFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DebtFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Prêt '.$this->faker->word(),
            'original_amount_cents' => 1000000,
            'remaining_amount_cents' => 500000,
            'monthly_payment_cents' => 20000,
            'rate_bps' => 390,
            'end_date' => '2029-01-01',
        ];
    }
}
```

Add to `app/Models/User.php`:

```php
    public function debts()
    {
        return $this->hasMany(Debt::class);
    }
```

- [ ] **Step 3: Write the failing feature test**

`tests/Feature/DebtTest.php`:

```php
<?php

use App\Models\Debt;
use App\Models\User;

it('lists debts with derived progress and months-left fields', function () {
    $user = User::factory()->create();
    Debt::factory()->for($user)->create([
        'original_amount_cents' => 100000,
        'remaining_amount_cents' => 40000,
        'monthly_payment_cents' => 10000,
    ]);

    $response = $this->actingAs($user)->getJson('/api/debts');

    $response->assertOk();
    expect($response->json('data.0.progress_pct'))->toBe(60.0);
    expect($response->json('data.0.months_left'))->toBe(4);
});

it('creates a debt from euro amounts and a percentage rate', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/debts', [
        'name' => 'Prêt auto',
        'original_amount' => 18000,
        'remaining_amount' => 11200,
        'monthly_payment' => 320,
        'rate' => 3.9,
        'end_date' => '2029-06-15',
    ]);

    $response->assertCreated();
    expect(Debt::first()->rate_bps)->toBe(390);
});

it('updates a debt belonging to the authenticated user only', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $debt = Debt::factory()->for($owner)->create();

    $this->actingAs($intruder)->patchJson("/api/debts/{$debt->id}", [
        'name' => 'Hacked', 'original_amount' => 1, 'remaining_amount' => 1,
        'monthly_payment' => 1, 'rate' => 0, 'end_date' => '2030-01-01',
    ])->assertNotFound();
});
```

- [ ] **Step 4: Run the tests, verify they fail**

```bash
php artisan test --filter=DebtTest
```

Expected: FAIL — routes don't exist.

- [ ] **Step 5: Implement requests, resource, and controller**

`app/Http/Requests/StoreDebtRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'original_amount' => ['required', 'numeric', 'min:0'],
            'remaining_amount' => ['required', 'numeric', 'min:0'],
            'monthly_payment' => ['required', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'end_date' => ['required', 'date'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'name' => $data['name'],
            'original_amount_cents' => (int) round($data['original_amount'] * 100),
            'remaining_amount_cents' => (int) round($data['remaining_amount'] * 100),
            'monthly_payment_cents' => (int) round($data['monthly_payment'] * 100),
            'rate_bps' => (int) round($data['rate'] * 100),
            'end_date' => $data['end_date'],
        ];
    }
}
```

`app/Http/Requests/UpdateDebtRequest.php`:

```php
<?php

namespace App\Http\Requests;

// The MVP has no partial-update screen for debts (the design only wires up
// creation) — reuse the same full-payload validation for the PATCH endpoint.
class UpdateDebtRequest extends StoreDebtRequest
{
}
```

`app/Http/Resources/DebtResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray($request): array
    {
        $progressPct = $this->original_amount_cents > 0
            ? min((($this->original_amount_cents - $this->remaining_amount_cents) / $this->original_amount_cents) * 100, 100)
            : 0.0;

        $monthsLeft = $this->monthly_payment_cents > 0
            ? (int) ceil($this->remaining_amount_cents / $this->monthly_payment_cents)
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'original_amount' => $this->original_amount_cents / 100,
            'remaining_amount' => $this->remaining_amount_cents / 100,
            'monthly_payment' => $this->monthly_payment_cents / 100,
            'rate' => $this->rate_bps / 100,
            'end_date' => $this->end_date->toDateString(),
            'progress_pct' => round($progressPct, 1),
            'months_left' => $monthsLeft,
        ];
    }
}
```

`app/Http/Controllers/Api/DebtController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDebtRequest;
use App\Http\Requests\UpdateDebtRequest;
use App\Http\Resources\DebtResource;
use App\Models\Debt;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function index(Request $request)
    {
        return DebtResource::collection($request->user()->debts()->get());
    }

    public function store(StoreDebtRequest $request)
    {
        $debt = $request->user()->debts()->create($request->validated());

        return (new DebtResource($debt))->response()->setStatusCode(201);
    }

    public function update(UpdateDebtRequest $request, Debt $debt)
    {
        $debt->update($request->validated());

        return new DebtResource($debt);
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group, plus the `use` import):

```php
    Route::get('/debts', [DebtController::class, 'index']);
    Route::post('/debts', [DebtController::class, 'store']);
    Route::patch('/debts/{debt}', [DebtController::class, 'update']);
```

- [ ] **Step 6: Run the tests, verify they pass**

```bash
php artisan test --filter=DebtTest
```

Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add debts resource"
```

---

### Task 6: Transactions — model, factory, and filtered list with running balance

**Files:**
- Create: `apps/api/database/migrations/xxxx_create_transactions_table.php`
- Create: `apps/api/app/Models/Transaction.php`
- Create: `apps/api/database/factories/TransactionFactory.php`
- Create: `apps/api/app/Services/TransactionRunningBalanceCalculator.php`
- Create: `apps/api/app/Http/Resources/TransactionResource.php`
- Create: `apps/api/app/Http/Controllers/Api/TransactionController.php`
- Modify: `apps/api/app/Models/User.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Unit/AccountBalanceCalculatorTest.php` (already written in Task 4 Step 4 — now runnable)
- Test: `apps/api/tests/Unit/TransactionRunningBalanceCalculatorTest.php`
- Test: `apps/api/tests/Feature/TransactionListTest.php`

- [ ] **Step 1: Create the migration**

```bash
cd apps/api
php artisan make:migration create_transactions_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('label');
            $table->bigInteger('amount_cents');
            $table->date('date');
            $table->boolean('reconciled')->default(false);
            $table->enum('link_type', ['none', 'debt', 'savings'])->default('none');
            $table->foreignId('linked_debt_id')->nullable()->constrained('debts')->nullOnDelete();
            $table->foreignId('linked_savings_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->uuid('series_id')->nullable();
            $table->enum('series_kind', ['installment', 'recurring'])->nullable();
            $table->unsignedSmallInteger('series_index')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['account_id', 'date']);
            $table->index(['user_id', 'category_id', 'date']);
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

```bash
php artisan migrate
```

- [ ] **Step 2: Create the model, factory, and `User` relation**

```bash
php artisan make:model Transaction
php artisan make:factory TransactionFactory --model=Transaction
```

`app/Models/Transaction.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use BelongsToUser, HasFactory;

    protected $fillable = [
        'account_id', 'category_id', 'label', 'amount_cents', 'date',
        'reconciled', 'link_type', 'linked_debt_id', 'linked_savings_account_id',
        'series_id', 'series_kind', 'series_index',
    ];

    protected $casts = [
        'date' => 'date',
        'reconciled' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

`database/factories/TransactionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'label' => $this->faker->words(3, true),
            'amount_cents' => -1000,
            'date' => $this->faker->date(),
            'reconciled' => true,
            'link_type' => 'none',
        ];
    }
}
```

Add to `app/Models/User.php`:

```php
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
```

- [ ] **Step 3: Run the Task 4 balance calculator unit test — now runnable**

```bash
php artisan test --filter=AccountBalanceCalculatorTest
```

Expected: PASS. This test was written in Task 4 Step 4 but couldn't execute
until the `Transaction` model existed.

- [ ] **Step 4: Write the failing running-balance unit test**

`tests/Unit/TransactionRunningBalanceCalculatorTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionRunningBalanceCalculator;

it('accumulates every transaction on the account regardless of reconciled status', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_balance_cents' => 1000]);
    $category = Category::factory()->create();

    $t1 = Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount_cents' => 500, 'date' => '2026-08-01', 'reconciled' => true,
    ]);
    $t2 = Transaction::factory()->for($user)->for($account)->for($category)->create([
        'amount_cents' => -200, 'date' => '2026-08-02', 'reconciled' => false,
    ]);

    $calculator = new TransactionRunningBalanceCalculator();
    $balances = $calculator->forAccount($account->fresh());

    expect($balances[$t1->id])->toBe(1500);
    expect($balances[$t2->id])->toBe(1300);
});
```

- [ ] **Step 5: Run it, verify it fails**

```bash
php artisan test --filter=TransactionRunningBalanceCalculatorTest
```

Expected: FAIL — class doesn't exist.

- [ ] **Step 6: Implement the running-balance service**

`app/Services/TransactionRunningBalanceCalculator.php`:

```php
<?php

namespace App\Services;

use App\Models\Account;

class TransactionRunningBalanceCalculator
{
    /** @return array<int,int> transaction id => running balance in cents */
    public function forAccount(Account $account): array
    {
        $running = $account->opening_balance_cents;
        $balances = [];

        $account->transactions()
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'amount_cents'])
            ->each(function ($transaction) use (&$running, &$balances) {
                $running += $transaction->amount_cents;
                $balances[$transaction->id] = $running;
            });

        return $balances;
    }
}
```

- [ ] **Step 7: Run it, verify it passes**

```bash
php artisan test --filter=TransactionRunningBalanceCalculatorTest
```

Expected: PASS.

- [ ] **Step 8: Write the failing feature test for the list endpoint**

`tests/Feature/TransactionListTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

it('lists only the current month by default, newest first, with running balance', function () {
    Carbon::setTestNow('2026-08-15');
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['opening_balance_cents' => 0]);
    $category = Category::factory()->create();

    $inMonth = Transaction::factory()->for($user)->for($account)->for($category)->create([
        'date' => '2026-08-05', 'amount_cents' => -1000,
    ]);
    Transaction::factory()->for($user)->for($account)->for($category)->create([
        'date' => '2026-07-31', 'amount_cents' => -500,
    ]);

    $response = $this->actingAs($user)->getJson('/api/transactions');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($inMonth->id);
    expect($response->json('data.0.running_balance'))->toBe(-10.0);

    Carbon::setTestNow();
});

it('filters by account_id when provided', function () {
    Carbon::setTestNow('2026-08-15');
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();
    $category = Category::factory()->create();
    Transaction::factory()->for($user)->for($accountA)->for($category)->create(['date' => '2026-08-05']);
    Transaction::factory()->for($user)->for($accountB)->for($category)->create(['date' => '2026-08-06']);

    $response = $this->actingAs($user)->getJson("/api/transactions?account_id={$accountA->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.account_id'))->toBe($accountA->id);

    Carbon::setTestNow();
});
```

- [ ] **Step 9: Run the tests, verify they fail**

```bash
php artisan test --filter=TransactionListTest
```

Expected: FAIL — route doesn't exist.

- [ ] **Step 10: Implement the resource and the index action**

`app/Http/Resources/TransactionResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'category_id' => $this->category_id,
            'label' => $this->label,
            'amount' => $this->amount_cents / 100,
            'date' => $this->date->toDateString(),
            'reconciled' => $this->reconciled,
            'link_type' => $this->link_type,
            'linked_debt_id' => $this->linked_debt_id,
            'linked_savings_account_id' => $this->linked_savings_account_id,
            'series_id' => $this->series_id,
            'series_kind' => $this->series_kind,
            'series_index' => $this->series_index,
            'running_balance' => $this->when(
                isset($this->running_balance_cents),
                fn () => $this->running_balance_cents !== null ? $this->running_balance_cents / 100 : null,
            ),
        ];
    }
}
```

`app/Http/Controllers/Api/TransactionController.php` (index action only for now —
`store`/`update`/`destroy` are added in Tasks 7-8):

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionRunningBalanceCalculator;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private TransactionRunningBalanceCalculator $runningBalances)
    {
    }

    public function index(Request $request)
    {
        [$start, $end] = $this->periodRange($request->string('period', 'current')->toString());

        $query = $request->user()->transactions()
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        $transactions = $query->paginate(50);

        $balancesByAccount = [];
        $transactions->getCollection()->each(function (Transaction $transaction) use (&$balancesByAccount) {
            $accountId = $transaction->account_id;
            if (! isset($balancesByAccount[$accountId])) {
                $balancesByAccount[$accountId] = $this->runningBalances->forAccount($transaction->account);
            }
            $transaction->running_balance_cents = $balancesByAccount[$accountId][$transaction->id] ?? null;
        });

        return TransactionResource::collection($transactions);
    }

    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'previous' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'year' => [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ],
            default => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
        };
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group, plus the `use` import):

```php
    Route::get('/transactions', [TransactionController::class, 'index']);
```

- [ ] **Step 11: Run the tests, verify they pass**

```bash
php artisan test --filter=TransactionListTest
```

Expected: PASS (2 tests).

- [ ] **Step 12: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add transactions model and filtered list with running balance"
```

---

### Task 7: Transactions — creation (simple, installment, recurring)

**Files:**
- Create: `apps/api/app/Services/TransactionSeriesGenerator.php`
- Create: `apps/api/app/Http/Requests/StoreTransactionRequest.php`
- Modify: `apps/api/app/Http/Controllers/Api/TransactionController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Unit/TransactionSeriesGeneratorTest.php`
- Test: `apps/api/tests/Feature/TransactionCreateTest.php`

- [ ] **Step 1: Write the failing unit test for the series generator**

`tests/Unit/TransactionSeriesGeneratorTest.php`:

```php
<?php

use App\Services\TransactionSeriesGenerator;

it('splits an installment total into equal shares, one month apart, remainder on the last row', function () {
    $generator = new TransactionSeriesGenerator();

    $rows = $generator->installments('Canapé', -10000, '2026-08-01', 3, true);

    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 'amount_cents'))->toBe([-3333, -3333, -3334]);
    expect(array_sum(array_column($rows, 'amount_cents')))->toBe(-10000);
    expect($rows[0]['date'])->toBe('2026-08-01');
    expect($rows[1]['date'])->toBe('2026-09-01');
    expect($rows[2]['date'])->toBe('2026-10-01');
    expect($rows[0]['label'])->toBe('Canapé (1/3)');
    expect($rows[0]['reconciled'])->toBeTrue();
    expect($rows[1]['reconciled'])->toBeFalse();
    expect($rows[0]['series_id'])->toBe($rows[1]['series_id']);
});

it('repeats the full amount for each recurring occurrence at the given frequency', function () {
    $generator = new TransactionSeriesGenerator();

    $rows = $generator->recurring('Netflix', -1599, '2026-08-01', 3, 'monthly', true);

    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 'amount_cents'))->toBe([-1599, -1599, -1599]);
    expect($rows[2]['date'])->toBe('2026-10-01');
    expect($rows[1]['reconciled'])->toBeFalse();
});
```

- [ ] **Step 2: Run it, verify it fails**

```bash
cd apps/api
php artisan test --filter=TransactionSeriesGeneratorTest
```

Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Implement the generator**

`app/Services/TransactionSeriesGenerator.php`:

```php
<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class TransactionSeriesGenerator
{
    /**
     * Splits $totalAmountCents into $count equal monthly installments.
     * Integer cents can't always divide evenly — the rounding remainder is
     * absorbed by the last installment so the series always sums exactly to
     * the requested total (the frontend prototype uses float division and
     * doesn't have this problem; the API must, since it stores integers).
     *
     * @return array<int, array{date: string, label: string, amount_cents: int, reconciled: bool, series_id: string, series_kind: string, series_index: int}>
     */
    public function installments(string $label, int $totalAmountCents, string $startDate, int $count, bool $firstReconciled): array
    {
        $per = intdiv($totalAmountCents, $count);
        $remainder = $totalAmountCents - ($per * $count);
        $seriesId = (string) Str::uuid();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'date' => Carbon::parse($startDate)->addMonthsNoOverflow($i)->toDateString(),
                'label' => sprintf('%s (%d/%d)', $label, $i + 1, $count),
                'amount_cents' => $per + ($i === $count - 1 ? $remainder : 0),
                'reconciled' => $i === 0 && $firstReconciled,
                'series_id' => $seriesId,
                'series_kind' => 'installment',
                'series_index' => $i + 1,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{date: string, label: string, amount_cents: int, reconciled: bool, series_id: string, series_kind: string, series_index: int}>
     */
    public function recurring(string $label, int $amountCents, string $startDate, int $count, string $frequency, bool $firstReconciled): array
    {
        $seriesId = (string) Str::uuid();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $date = match ($frequency) {
                'weekly' => Carbon::parse($startDate)->addWeeks($i),
                'yearly' => Carbon::parse($startDate)->addYearsNoOverflow($i),
                default => Carbon::parse($startDate)->addMonthsNoOverflow($i),
            };

            $rows[] = [
                'date' => $date->toDateString(),
                'label' => $label,
                'amount_cents' => $amountCents,
                'reconciled' => $i === 0 && $firstReconciled,
                'series_id' => $seriesId,
                'series_kind' => 'recurring',
                'series_index' => $i + 1,
            ];
        }

        return $rows;
    }
}
```

- [ ] **Step 4: Run it, verify it passes**

```bash
php artisan test --filter=TransactionSeriesGeneratorTest
```

Expected: PASS.

- [ ] **Step 5: Write the failing feature tests for creation**

`tests/Feature/TransactionCreateTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;

function makeAccountAndCategory(User $user, string $accountType = 'checking'): array
{
    return [
        Account::factory()->for($user)->create(['type' => $accountType]),
        Category::factory()->create(['is_income' => false]),
    ];
}

it('creates a single expense transaction with a negative signed amount', function () {
    $user = User::factory()->create();
    [$account, $category] = makeAccountAndCategory($user);

    $response = $this->actingAs($user)->postJson('/api/transactions', [
        'label' => 'Supermarché', 'amount' => 64.20, 'type' => 'expense',
        'category_id' => $category->id, 'account_id' => $account->id,
        'date' => '2026-08-03', 'reconciled' => true, 'link_type' => 'none', 'mode' => 'simple',
    ]);

    $response->assertCreated();
    expect(Transaction::first()->amount_cents)->toBe(-6420);
});

it('creates an installment series that sums exactly to the total', function () {
    $user = User::factory()->create();
    [$account, $category] = makeAccountAndCategory($user);

    $this->actingAs($user)->postJson('/api/transactions', [
        'label' => 'Canapé', 'amount' => 100, 'type' => 'expense',
        'category_id' => $category->id, 'account_id' => $account->id,
        'date' => '2026-08-01', 'reconciled' => true, 'link_type' => 'none',
        'mode' => 'installment', 'installment' => ['count' => 3],
    ])->assertCreated();

    expect(Transaction::count())->toBe(3);
    expect(Transaction::sum('amount_cents'))->toBe(-10000);
});

it('requires a linked debt when link_type is debt', function () {
    $user = User::factory()->create();
    [$account, $category] = makeAccountAndCategory($user);

    $this->actingAs($user)->postJson('/api/transactions', [
        'label' => 'Remboursement', 'amount' => 100, 'type' => 'expense',
        'category_id' => $category->id, 'account_id' => $account->id,
        'date' => '2026-08-01', 'reconciled' => true, 'link_type' => 'debt', 'mode' => 'simple',
    ])->assertStatus(422);
});

it('rejects a savings link that points at a checking account', function () {
    $user = User::factory()->create();
    [$account, $category] = makeAccountAndCategory($user);
    $checkingTarget = Account::factory()->for($user)->create(['type' => 'checking']);

    $this->actingAs($user)->postJson('/api/transactions', [
        'label' => 'Virement', 'amount' => 100, 'type' => 'expense',
        'category_id' => $category->id, 'account_id' => $account->id,
        'date' => '2026-08-01', 'reconciled' => true, 'link_type' => 'savings',
        'linked_savings_account_id' => $checkingTarget->id, 'mode' => 'simple',
    ])->assertStatus(422);
});
```

- [ ] **Step 6: Run the tests, verify they fail**

```bash
php artisan test --filter=TransactionCreateTest
```

Expected: FAIL — `store` route doesn't exist.

- [ ] **Step 7: Implement the request and the controller's `store` action**

`app/Http/Requests/StoreTransactionRequest.php`. Note that `linked_debt_id`
and `linked_savings_account_id` are checked with closures rather than plain
`exists:` rules — `exists:accounts,id` alone would accept another user's
account, since it doesn't know about the `BelongsToUser` scope; running the
lookup through `Account::find()`/`Debt::find()` reuses that scope, so a
non-owned or wrong-type id fails validation:

```php
<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'type' => ['required', 'in:expense,income'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'reconciled' => ['boolean'],
            'link_type' => ['required', 'in:none,debt,savings'],
            'linked_debt_id' => [
                'required_if:link_type,debt', 'nullable', 'integer',
                function ($attribute, $value, $fail) {
                    if ($value && ! Debt::find($value)) {
                        $fail('Dette introuvable.');
                    }
                },
            ],
            'linked_savings_account_id' => [
                'required_if:link_type,savings', 'nullable', 'integer',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }
                    $account = Account::find($value);
                    if (! $account) {
                        $fail('Compte introuvable.');
                    } elseif ($account->type !== 'savings') {
                        $fail("Le compte lié doit être un compte d'épargne.");
                    }
                },
            ],
            'mode' => ['required', 'in:simple,installment,recurring'],
            'installment.count' => ['required_if:mode,installment', 'integer', 'in:2,3,4,6,12'],
            'recurring.count' => ['required_if:mode,recurring', 'integer', 'in:3,6,12,24'],
            'recurring.frequency' => ['required_if:mode,recurring', 'in:weekly,monthly,yearly'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $amountCents = (int) round(abs($data['amount']) * 100);
        $data['amount_cents'] = $data['type'] === 'expense' ? -$amountCents : $amountCents;
        $data['reconciled'] = $data['reconciled'] ?? false;

        return $data;
    }
}
```

`app/Http/Controllers/Api/TransactionController.php` (add to the existing
class from Task 6 — constructor now takes both services, and a `store`
method is added):

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionRunningBalanceCalculator;
use App\Services\TransactionSeriesGenerator;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private TransactionRunningBalanceCalculator $runningBalances,
        private TransactionSeriesGenerator $seriesGenerator,
    ) {
    }

    public function index(Request $request)
    {
        [$start, $end] = $this->periodRange($request->string('period', 'current')->toString());

        $query = $request->user()->transactions()
            ->whereBetween('date', [$start, $end])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        $transactions = $query->paginate(50);

        $balancesByAccount = [];
        $transactions->getCollection()->each(function (Transaction $transaction) use (&$balancesByAccount) {
            $accountId = $transaction->account_id;
            if (! isset($balancesByAccount[$accountId])) {
                $balancesByAccount[$accountId] = $this->runningBalances->forAccount($transaction->account);
            }
            $transaction->running_balance_cents = $balancesByAccount[$accountId][$transaction->id] ?? null;
        });

        return TransactionResource::collection($transactions);
    }

    public function store(StoreTransactionRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $rows = match ($data['mode']) {
            'installment' => $this->seriesGenerator->installments(
                $data['label'], $data['amount_cents'], $data['date'],
                $data['installment']['count'], $data['reconciled'],
            ),
            'recurring' => $this->seriesGenerator->recurring(
                $data['label'], $data['amount_cents'], $data['date'],
                $data['recurring']['count'], $data['recurring']['frequency'], $data['reconciled'],
            ),
            default => [[
                'date' => $data['date'], 'label' => $data['label'], 'amount_cents' => $data['amount_cents'],
                'reconciled' => $data['reconciled'], 'series_id' => null, 'series_kind' => null, 'series_index' => null,
            ]],
        };

        $created = collect($rows)->map(fn (array $row) => $user->transactions()->create([
            ...$row,
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'],
            'link_type' => $data['link_type'],
            'linked_debt_id' => $data['linked_debt_id'] ?? null,
            'linked_savings_account_id' => $data['linked_savings_account_id'] ?? null,
        ]));

        return TransactionResource::collection($created)->response()->setStatusCode(201);
    }

    private function periodRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'previous' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'year' => [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ],
            default => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
        };
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group):

```php
    Route::post('/transactions', [TransactionController::class, 'store']);
```

- [ ] **Step 8: Run the tests, verify they pass**

```bash
php artisan test --filter=TransactionCreateTest
```

Expected: PASS (4 tests).

- [ ] **Step 9: Run the full test suite to check for regressions**

```bash
php artisan test
```

Expected: all tests still pass.

- [ ] **Step 10: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add transaction creation with installment/recurring series generation"
```

---

### Task 8: Transactions — toggle reconciled and delete

**Files:**
- Create: `apps/api/app/Http/Requests/UpdateTransactionRequest.php`
- Modify: `apps/api/app/Http/Controllers/Api/TransactionController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/TransactionUpdateTest.php`

- [ ] **Step 1: Write the failing feature tests**

`tests/Feature/TransactionUpdateTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

it('toggles reconciled on the authenticated user\'s own transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->create();
    $transaction = Transaction::factory()->for($user)->for($account)->for($category)->create(['reconciled' => false]);

    $response = $this->actingAs($user)->patchJson("/api/transactions/{$transaction->id}", [
        'reconciled' => true,
    ]);

    $response->assertOk();
    expect($transaction->fresh()->reconciled)->toBeTrue();
});

it('returns 404 when patching another user\'s transaction', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $category = Category::factory()->create();
    $transaction = Transaction::factory()->for($owner)->for($account)->for($category)->create();

    $this->actingAs($intruder)
        ->patchJson("/api/transactions/{$transaction->id}", ['reconciled' => true])
        ->assertNotFound();
});

it('deletes a transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->create();
    $transaction = Transaction::factory()->for($user)->for($account)->for($category)->create();

    $this->actingAs($user)->deleteJson("/api/transactions/{$transaction->id}")->assertNoContent();

    expect(Transaction::find($transaction->id))->toBeNull();
});
```

- [ ] **Step 2: Run them, verify they fail**

```bash
cd apps/api
php artisan test --filter=TransactionUpdateTest
```

Expected: FAIL — routes don't exist.

- [ ] **Step 3: Implement the request and the controller actions**

`app/Http/Requests/UpdateTransactionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reconciled' => ['required', 'boolean'],
        ];
    }
}
```

Add to `app/Http/Controllers/Api/TransactionController.php` (two new methods,
plus the `UpdateTransactionRequest` import at the top):

```php
use App\Http\Requests\UpdateTransactionRequest;
```

```php
    public function update(UpdateTransactionRequest $request, Transaction $transaction)
    {
        $transaction->update($request->validated());

        return new TransactionResource($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return response()->noContent();
    }
```

`routes/api.php` (add inside the `auth:sanctum` group):

```php
    Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);
```

- [ ] **Step 4: Run the tests, verify they pass**

```bash
php artisan test --filter=TransactionUpdateTest
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add transaction reconciled toggle and delete"
```

---

### Task 9: Budgets

**Files:**
- Create: `apps/api/database/migrations/xxxx_create_budgets_table.php`
- Create: `apps/api/app/Models/Budget.php`
- Create: `apps/api/app/Services/BudgetAggregator.php`
- Create: `apps/api/app/Http/Requests/UpdateBudgetRequest.php`
- Create: `apps/api/app/Http/Controllers/Api/BudgetController.php`
- Modify: `apps/api/app/Models/User.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Unit/BudgetAggregatorTest.php`
- Test: `apps/api/tests/Feature/BudgetTest.php`

- [ ] **Step 1: Create the migration**

```bash
cd apps/api
php artisan make:migration create_budgets_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->bigInteger('monthly_amount_cents');
            $table->timestamps();
            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
```

```bash
php artisan migrate
```

- [ ] **Step 2: Create the model and `User` relation**

```bash
php artisan make:model Budget
```

`app/Models/Budget.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use BelongsToUser;

    protected $fillable = ['category_id', 'monthly_amount_cents'];
}
```

Add to `app/Models/User.php`:

```php
    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
```

- [ ] **Step 3: Write the failing unit test for the aggregator**

`tests/Unit/BudgetAggregatorTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetAggregator;
use Illuminate\Support\Carbon;

it('computes spent, pct, and status per expense category for the given month', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $logement = Category::factory()->create(['name' => 'Logement', 'is_income' => false, 'sort_order' => 0]);
    $revenus = Category::factory()->create(['name' => 'Revenus', 'is_income' => true, 'sort_order' => 1]);

    $user->budgets()->create(['category_id' => $logement->id, 'monthly_amount_cents' => 80000]);

    Transaction::factory()->for($user)->for($account)->for($logement)->create([
        'amount_cents' => -85000, 'date' => '2026-08-04',
    ]);
    Transaction::factory()->for($user)->for($account)->for($revenus)->create([
        'amount_cents' => 220000, 'date' => '2026-08-05',
    ]);

    $rows = (new BudgetAggregator())->forMonth($user, Carbon::parse('2026-08-01'));

    $logementRow = collect($rows)->firstWhere('name', 'Logement');
    expect($logementRow['spent_cents'])->toBe(85000);
    expect($logementRow['pct'])->toBe(106.3);
    expect($logementRow['status'])->toBe('over');
    expect(collect($rows)->pluck('name'))->not->toContain('Revenus');
});
```

- [ ] **Step 4: Run it, verify it fails**

```bash
php artisan test --filter=BudgetAggregatorTest
```

Expected: FAIL — class doesn't exist.

- [ ] **Step 5: Implement the aggregator**

`app/Services/BudgetAggregator.php`:

```php
<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Carbon;

class BudgetAggregator
{
    /** @return array<int, array{category_id:int, name:string, color_hex:string, budget_cents:int, spent_cents:int, pct: float, status:string}> */
    public function forMonth(User $user, Carbon $monthStart): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        $budgets = $user->budgets()->pluck('monthly_amount_cents', 'category_id');

        $spent = $user->transactions()
            ->where('amount_cents', '<', 0)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('category_id, SUM(-amount_cents) as spent_cents')
            ->groupBy('category_id')
            ->pluck('spent_cents', 'category_id');

        return Category::where('is_income', false)
            ->orderBy('sort_order')
            ->get()
            ->map(function (Category $category) use ($budgets, $spent) {
                $budgetCents = (int) ($budgets[$category->id] ?? 0);
                $spentCents = (int) ($spent[$category->id] ?? 0);
                $pct = $budgetCents > 0 ? ($spentCents / $budgetCents) * 100 : 0.0;

                return [
                    'category_id' => $category->id,
                    'name' => $category->name,
                    'color_hex' => $category->color_hex,
                    'budget_cents' => $budgetCents,
                    'spent_cents' => $spentCents,
                    'pct' => round($pct, 1),
                    'status' => match (true) {
                        $pct >= 100 => 'over',
                        $pct >= 80 => 'warn',
                        default => 'ok',
                    },
                ];
            })
            ->all();
    }
}
```

- [ ] **Step 6: Run it, verify it passes**

```bash
php artisan test --filter=BudgetAggregatorTest
```

Expected: PASS.

- [ ] **Step 7: Write the failing feature tests for the endpoints**

`tests/Feature/BudgetTest.php`:

```php
<?php

use App\Models\Category;
use App\Models\User;

it('returns aggregated budgets for the requested month', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_income' => false, 'sort_order' => 0]);
    $user->budgets()->create(['category_id' => $category->id, 'monthly_amount_cents' => 50000]);

    $response = $this->actingAs($user)->getJson('/api/budgets?month=2026-08');

    $response->assertOk();
    expect($response->json('data.0.budget_cents'))->toBe(50000);
});

it('upserts a budget amount for a category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['is_income' => false]);

    $response = $this->actingAs($user)->putJson("/api/budgets/{$category->id}", [
        'monthly_amount' => 500,
    ]);

    $response->assertOk();
    expect($user->budgets()->first()->monthly_amount_cents)->toBe(50000);
});
```

- [ ] **Step 8: Run them, verify they fail**

```bash
php artisan test --filter=BudgetTest
```

Expected: FAIL — routes don't exist.

- [ ] **Step 9: Implement the request and controller**

`app/Http/Requests/UpdateBudgetRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        return [
            'monthly_amount_cents' => (int) round($this->input('monthly_amount') * 100),
        ];
    }
}
```

`app/Http/Controllers/Api/BudgetController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Category;
use App\Services\BudgetAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BudgetController extends Controller
{
    public function __construct(private BudgetAggregator $aggregator)
    {
    }

    public function index(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::parse($request->string('month').'-01')
            : Carbon::now()->startOfMonth();

        return response()->json([
            'data' => $this->aggregator->forMonth($request->user(), $month),
        ]);
    }

    public function update(UpdateBudgetRequest $request, Category $category)
    {
        $budget = $request->user()->budgets()->updateOrCreate(
            ['category_id' => $category->id],
            ['monthly_amount_cents' => $request->validated()['monthly_amount_cents']],
        );

        return response()->json(['data' => [
            'category_id' => $category->id,
            'monthly_amount' => $budget->monthly_amount_cents / 100,
        ]]);
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group):

```php
    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::put('/budgets/{category}', [BudgetController::class, 'update']);
```

- [ ] **Step 10: Run the tests, verify they pass**

```bash
php artisan test --filter=BudgetTest
```

Expected: PASS (2 tests).

- [ ] **Step 11: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add budgets aggregation and update endpoints"
```

---

### Task 10: User settings (income, savings effort, return rate)

**Files:**
- Create: `apps/api/database/migrations/xxxx_create_user_settings_table.php`
- Create: `apps/api/app/Models/UserSetting.php`
- Create: `apps/api/app/Http/Requests/UpdateSettingsRequest.php`
- Create: `apps/api/app/Http/Resources/SettingsResource.php`
- Create: `apps/api/app/Http/Controllers/Api/SettingController.php`
- Modify: `apps/api/app/Models/User.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/SettingTest.php`

- [ ] **Step 1: Create the migration**

```bash
cd apps/api
php artisan make:migration create_user_settings_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->bigInteger('monthly_income_cents')->default(0);
            $table->bigInteger('monthly_savings_contribution_cents')->default(0);
            $table->integer('annual_return_rate_bps')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
```

```bash
php artisan migrate
```

- [ ] **Step 2: Create the model and `User` relation**

```bash
php artisan make:model UserSetting
```

`app/Models/UserSetting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'monthly_income_cents', 'monthly_savings_contribution_cents', 'annual_return_rate_bps',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

`UserSetting` is intentionally **not** using `BelongsToUser` — it's a
one-to-one row keyed directly by `user_id` as its primary key, not a
collection that needs a "list mine" scope.

Add to `app/Models/User.php`:

```php
    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }
```

- [ ] **Step 3: Write the failing feature tests**

`tests/Feature/SettingTest.php`:

```php
<?php

use App\Models\User;

it('creates default settings on demand when reading for the first time', function () {
    $user = User::factory()->create();
    $user->settings()->create([
        'monthly_income_cents' => 0, 'monthly_savings_contribution_cents' => 0, 'annual_return_rate_bps' => 0,
    ]);

    $response = $this->actingAs($user)->getJson('/api/settings');

    $response->assertOk();
    expect($response->json('data.monthly_income'))->toBe(0.0);
});

it('updates settings from euro/percent input', function () {
    $user = User::factory()->create();
    $user->settings()->create([
        'monthly_income_cents' => 0, 'monthly_savings_contribution_cents' => 0, 'annual_return_rate_bps' => 0,
    ]);

    $response = $this->actingAs($user)->putJson('/api/settings', [
        'monthly_income' => 2200, 'monthly_savings_contribution' => 1000, 'annual_return_rate' => 2,
    ]);

    $response->assertOk();
    expect($user->settings->fresh()->annual_return_rate_bps)->toBe(200);
});
```

- [ ] **Step 4: Run them, verify they fail**

```bash
php artisan test --filter=SettingTest
```

Expected: FAIL — routes don't exist.

- [ ] **Step 5: Implement the request, resource, and controller**

`app/Http/Requests/UpdateSettingsRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'monthly_savings_contribution' => ['required', 'numeric'],
            'annual_return_rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return [
            'monthly_income_cents' => (int) round($data['monthly_income'] * 100),
            'monthly_savings_contribution_cents' => (int) round($data['monthly_savings_contribution'] * 100),
            'annual_return_rate_bps' => (int) round($data['annual_return_rate'] * 100),
        ];
    }
}
```

`app/Http/Resources/SettingsResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'monthly_income' => $this->monthly_income_cents / 100,
            'monthly_savings_contribution' => $this->monthly_savings_contribution_cents / 100,
            'annual_return_rate' => $this->annual_return_rate_bps / 100,
        ];
    }
}
```

`app/Http/Controllers/Api/SettingController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Resources\SettingsResource;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function show(Request $request)
    {
        return new SettingsResource($request->user()->settings);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $request->user()->settings()->update($request->validated());

        return new SettingsResource($request->user()->settings->fresh());
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group):

```php
    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);
```

- [ ] **Step 6: Run the tests, verify they pass**

```bash
php artisan test --filter=SettingTest
```

Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add user settings endpoints"
```

---

### Task 11: Savings projection

**Files:**
- Create: `apps/api/app/Services/SavingsProjectionCalculator.php`
- Create: `apps/api/app/Http/Controllers/Api/SavingsProjectionController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Unit/SavingsProjectionCalculatorTest.php`
- Test: `apps/api/tests/Feature/SavingsProjectionTest.php`

- [ ] **Step 1: Write the failing unit test**

`tests/Unit/SavingsProjectionCalculatorTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\User;
use App\Services\AccountBalanceCalculator;
use App\Services\SavingsProjectionCalculator;
use Illuminate\Support\Carbon;

it('compounds monthly from the current savings balance using the stored rate and contribution', function () {
    Carbon::setTestNow('2026-08-06');
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['type' => 'savings', 'opening_balance_cents' => 100000]);
    $user->settings()->create([
        'monthly_income_cents' => 0, 'monthly_savings_contribution_cents' => 10000, 'annual_return_rate_bps' => 1200,
    ]);

    $result = (new SavingsProjectionCalculator(new AccountBalanceCalculator()))->build($user, 2);

    // monthly rate = 1200 bps / 10000 / 12 = 0.01
    expect($result['history'])->toHaveCount(4);
    expect($result['projection'][0])->toBe(100000);
    expect($result['projection'][1])->toBe(111000); // 100000 * 1.01 + 10000
    expect($result['projection'][2])->toBe(122110); // 111000 * 1.01 + 10000

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run it, verify it fails**

```bash
cd apps/api
php artisan test --filter=SavingsProjectionCalculatorTest
```

Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Implement the calculator**

`app/Services/SavingsProjectionCalculator.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class SavingsProjectionCalculator
{
    public function __construct(private AccountBalanceCalculator $balanceCalculator)
    {
    }

    /** @return array{history: array<int, array{month_offset:int, balance_cents:int}>, projection: array<int, int>} */
    public function build(User $user, int $horizonMonths): array
    {
        $today = Carbon::today();
        $history = [];

        for ($i = 3; $i >= 0; $i--) {
            $asOf = $i === 0 ? $today : $today->copy()->subMonthsNoOverflow($i - 1)->endOfMonth();
            $history[] = [
                'month_offset' => -$i,
                'balance_cents' => $this->savingsBalanceAt($user, $asOf),
            ];
        }

        $settings = $user->settings;
        $contribution = $settings->monthly_savings_contribution_cents;
        $monthlyRate = $settings->annual_return_rate_bps / 10000 / 12;

        $projection = [end($history)['balance_cents']];
        for ($i = 1; $i <= $horizonMonths; $i++) {
            $projection[] = (int) round($projection[$i - 1] * (1 + $monthlyRate) + $contribution);
        }

        return ['history' => $history, 'projection' => $projection];
    }

    private function savingsBalanceAt(User $user, Carbon $asOf): int
    {
        return $user->accounts()
            ->where('type', 'savings')
            ->get()
            ->sum(fn ($account) => $this->balanceCalculator->balanceAt($account, $asOf));
    }
}
```

- [ ] **Step 4: Run it, verify it passes**

```bash
php artisan test --filter=SavingsProjectionCalculatorTest
```

Expected: PASS.

- [ ] **Step 5: Write the failing feature test**

`tests/Feature/SavingsProjectionTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\User;

it('returns history and projection points', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['type' => 'savings', 'opening_balance_cents' => 100000]);
    $user->settings()->create([
        'monthly_income_cents' => 0, 'monthly_savings_contribution_cents' => 10000, 'annual_return_rate_bps' => 0,
    ]);

    $response = $this->actingAs($user)->getJson('/api/savings-projection?horizon=6');

    $response->assertOk();
    expect($response->json('data.history'))->toHaveCount(4);
    expect($response->json('data.projection'))->toHaveCount(7);
});
```

- [ ] **Step 6: Run it, verify it fails**

```bash
php artisan test --filter=SavingsProjectionTest
```

Expected: FAIL — route doesn't exist.

- [ ] **Step 7: Implement the controller**

`app/Http/Controllers/Api/SavingsProjectionController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SavingsProjectionCalculator;
use Illuminate\Http\Request;

class SavingsProjectionController extends Controller
{
    public function __construct(private SavingsProjectionCalculator $calculator)
    {
    }

    public function __invoke(Request $request)
    {
        $horizon = $request->integer('horizon', 12);
        $result = $this->calculator->build($request->user(), $horizon);

        return response()->json([
            'data' => [
                'history' => collect($result['history'])->map(fn ($p) => [
                    'month_offset' => $p['month_offset'],
                    'balance' => $p['balance_cents'] / 100,
                ]),
                'projection' => collect($result['projection'])->map(fn ($cents) => $cents / 100),
            ],
        ]);
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group):

```php
    Route::get('/savings-projection', SavingsProjectionController::class);
```

- [ ] **Step 8: Run the tests, verify they pass**

```bash
php artisan test --filter=SavingsProjectionTest
```

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add savings projection endpoint"
```

---

### Task 12: Dashboard summary (replaces the frontend's hardcoded `PERIODS`)

**Files:**
- Create: `apps/api/app/Services/DashboardSummaryBuilder.php`
- Create: `apps/api/app/Http/Controllers/Api/DashboardController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/DashboardSummaryTest.php`

- [ ] **Step 1: Write the failing feature test**

`tests/Feature/DashboardSummaryTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

it('returns weekly bars and category totals for the current month', function () {
    Carbon::setTestNow('2026-08-15');
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $logement = Category::factory()->create(['name' => 'Logement', 'is_income' => false]);
    $revenus = Category::factory()->create(['name' => 'Revenus', 'is_income' => true]);

    Transaction::factory()->for($user)->for($account)->for($logement)->create([
        'amount_cents' => -78000, 'date' => '2026-08-04',
    ]);
    Transaction::factory()->for($user)->for($account)->for($revenus)->create([
        'amount_cents' => 220000, 'date' => '2026-08-05',
    ]);

    $response = $this->actingAs($user)->getJson('/api/dashboard/summary');

    $response->assertOk();
    expect($response->json('data.bars'))->not->toBeEmpty();
    $logementTotal = collect($response->json('data.categories'))->firstWhere('category_id', $logement->id);
    expect($logementTotal['amount'])->toBe(780.0);

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Run it, verify it fails**

```bash
cd apps/api
php artisan test --filter=DashboardSummaryTest
```

Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Implement the builder**

`app/Services/DashboardSummaryBuilder.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardSummaryBuilder
{
    public function build(User $user, string $period): array
    {
        return match ($period) {
            'previous' => $this->weeklyBars($user, Carbon::now()->subMonthNoOverflow()),
            'year' => $this->monthlyBars($user),
            default => $this->weeklyBars($user, Carbon::now()),
        };
    }

    private function weeklyBars(User $user, Carbon $monthReference): array
    {
        $start = $monthReference->copy()->startOfMonth();
        $end = $monthReference->copy()->endOfMonth();

        $transactions = $user->transactions()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'amount_cents', 'category_id']);

        $bars = [];
        $weekIndex = 1;
        for ($weekStart = $start->copy(); $weekStart->lte($end); $weekStart->addWeek()) {
            $weekEnd = $weekStart->copy()->addDays(6)->min($end);
            $weekTxns = $transactions->filter(fn ($t) => $t->date->betweenIncluded($weekStart, $weekEnd));

            $bars[] = [
                'label' => "Sem. {$weekIndex}",
                'income_cents' => (int) $weekTxns->where('amount_cents', '>', 0)->sum('amount_cents'),
                'expense_cents' => (int) $weekTxns->where('amount_cents', '<', 0)->sum('amount_cents') * -1,
            ];
            $weekIndex++;
        }

        return ['bars' => $bars, 'categories' => $this->categoryTotals($transactions)];
    }

    private function monthlyBars(User $user): array
    {
        $year = Carbon::now()->year;
        $transactions = $user->transactions()
            ->whereBetween('date', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['date', 'amount_cents', 'category_id']);

        $bars = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthTxns = $transactions->filter(fn ($t) => $t->date->month === $month);
            $bars[] = [
                'label' => Carbon::create($year, $month, 1)->translatedFormat('M'),
                'income_cents' => (int) $monthTxns->where('amount_cents', '>', 0)->sum('amount_cents'),
                'expense_cents' => (int) $monthTxns->where('amount_cents', '<', 0)->sum('amount_cents') * -1,
            ];
        }

        return ['bars' => $bars, 'categories' => $this->categoryTotals($transactions)];
    }

    private function categoryTotals(Collection $transactions): array
    {
        return $transactions
            ->where('amount_cents', '<', 0)
            ->groupBy('category_id')
            ->map(fn ($group, $categoryId) => [
                'category_id' => (int) $categoryId,
                'amount_cents' => (int) $group->sum('amount_cents') * -1,
            ])
            ->values()
            ->all();
    }
}
```

`Carbon::betweenIncluded()` doesn't exist on the `date` cast value by
default — Laravel casts `date` columns to `Illuminate\Support\Carbon`
instances, which do have `betweenIncluded()` (alias `between()` with
`equal: true`) since Carbon 2.x. If your installed Carbon version doesn't
expose it, replace the filter with
`fn ($t) => $t->date->gte($weekStart) && $t->date->lte($weekEnd)`.

- [ ] **Step 4: Implement the controller**

`app/Http/Controllers/Api/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardSummaryBuilder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardSummaryBuilder $builder)
    {
    }

    public function summary(Request $request)
    {
        $period = $request->string('period', 'current')->toString();
        $summary = $this->builder->build($request->user(), $period);

        return response()->json([
            'data' => [
                'bars' => collect($summary['bars'])->map(fn ($bar) => [
                    'label' => $bar['label'],
                    'income' => $bar['income_cents'] / 100,
                    'expense' => $bar['expense_cents'] / 100,
                ]),
                'categories' => collect($summary['categories'])->map(fn ($cat) => [
                    'category_id' => $cat['category_id'],
                    'amount' => $cat['amount_cents'] / 100,
                ]),
            ],
        ]);
    }
}
```

`routes/api.php` (add inside the `auth:sanctum` group):

```php
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
```

- [ ] **Step 5: Run the test, verify it passes**

```bash
php artisan test --filter=DashboardSummaryTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add dashboard summary endpoint backed by real transactions"
```

---

### Task 13: Authentication (register, login, logout, current user)

**Files:**
- Create: `apps/api/app/Http/Controllers/Api/AuthController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/AuthTest.php`

- [ ] **Step 1: Write the failing feature tests**

`tests/Feature/AuthTest.php`:

```php
<?php

use App\Models\User;

it('registers a new user and creates default settings', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Marius', 'email' => 'marius@example.com', 'password' => 'password123',
    ]);

    $response->assertCreated();
    expect(User::where('email', 'marius@example.com')->exists())->toBeTrue();
    expect(User::first()->settings)->not->toBeNull();
});

it('logs a user in and starts a session', function () {
    $user = User::factory()->create(['password' => bcrypt('password123')]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email, 'password' => 'password123',
    ]);

    $response->assertNoContent();
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'a@a.com', 'password' => bcrypt('correct')]);

    $this->postJson('/api/login', ['email' => 'a@a.com', 'password' => 'wrong'])
        ->assertStatus(422);
});

it('logs out and invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/logout')->assertNoContent();
    $this->assertGuest();
});

it('returns the authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/user');

    $response->assertOk();
    expect($response->json('data.email'))->toBe($user->email);
});
```

- [ ] **Step 2: Run them, verify they fail**

```bash
cd apps/api
php artisan test --filter=AuthTest
```

Expected: FAIL — routes don't exist.

- [ ] **Step 3: Implement the controller**

`app/Http/Controllers/Api/AuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->settings()->create([
            'monthly_income_cents' => 0,
            'monthly_savings_contribution_cents' => 0,
            'annual_return_rate_bps' => 0,
        ]);

        Auth::login($user);

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => ['Identifiants invalides.']]);
        }

        $request->session()->regenerate();

        return response()->noContent();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json(['data' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]]);
    }
}
```

`routes/api.php` — final assembly, replace the whole file with:

```php
<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\SavingsProjectionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/categories', [CategoryController::class, 'index']);

    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    Route::get('/debts', [DebtController::class, 'index']);
    Route::post('/debts', [DebtController::class, 'store']);
    Route::patch('/debts/{debt}', [DebtController::class, 'update']);

    Route::get('/budgets', [BudgetController::class, 'index']);
    Route::put('/budgets/{category}', [BudgetController::class, 'update']);

    Route::get('/settings', [SettingController::class, 'show']);
    Route::put('/settings', [SettingController::class, 'update']);

    Route::get('/savings-projection', SavingsProjectionController::class);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
});
```

- [ ] **Step 4: Run the tests, verify they pass**

```bash
php artisan test --filter=AuthTest
```

Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
cd ../..
git add apps/api
git commit -m "feat(api): add register/login/logout/user endpoints and finalize routes"
```

---

### Task 14: Demo data seeder

**Files:**
- Create: `apps/api/database/seeders/DemoDataSeeder.php`
- Modify: `apps/api/database/seeders/DatabaseSeeder.php`
- Test: `apps/api/tests/Feature/DemoDataSeederTest.php`

- [ ] **Step 1: Write the failing feature test**

`tests/Feature/DemoDataSeederTest.php`:

```php
<?php

use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoDataSeeder;

it('seeds a demo user with accounts, transactions, debts and budgets', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(DemoDataSeeder::class);

    $user = User::where('email', 'demo@comptastic.test')->firstOrFail();

    expect($user->accounts)->toHaveCount(5);
    expect($user->transactions)->toHaveCount(10);
    expect($user->debts)->toHaveCount(3);
    expect($user->budgets)->toHaveCount(6);
    expect($user->settings)->not->toBeNull();
});
```

- [ ] **Step 2: Run it, verify it fails**

```bash
cd apps/api
php artisan test --filter=DemoDataSeederTest
```

Expected: FAIL — `DemoDataSeeder` doesn't exist.

- [ ] **Step 3: Implement the seeder**

Reproduces `SEED_ACCOUNTS`, `SEED_TRANSACTIONS`, `SEED_DEBTS`, and
`DEFAULT_BUDGETS` from `apps/web/src/stores/ledger.js`.

`database/seeders/DemoDataSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@comptastic.test'],
            ['name' => 'Demo', 'password' => Hash::make('password')],
        );

        $user->settings()->updateOrCreate([], [
            'monthly_income_cents' => 220000,
            'monthly_savings_contribution_cents' => 100000,
            'annual_return_rate_bps' => 200,
        ]);

        $accounts = [
            ['name' => 'Compte courant BNP Paribas', 'bank' => 'BNP Paribas', 'type' => 'checking', 'iban_last4' => '1234', 'opening_balance_cents' => -119902],
            ['name' => 'Compte courant Boursorama', 'bank' => 'Boursorama Banque', 'type' => 'checking', 'iban_last4' => '5678', 'opening_balance_cents' => 100272],
            ['name' => 'Compte courant Revolut', 'bank' => 'Revolut', 'type' => 'checking', 'iban_last4' => '7890', 'opening_balance_cents' => 41230],
            ['name' => 'Livret A (Crédit Agricole)', 'bank' => 'Crédit Agricole', 'type' => 'savings', 'iban_last4' => '9012', 'opening_balance_cents' => 795000],
            ['name' => 'LDDS (Crédit Agricole)', 'bank' => 'Crédit Agricole', 'type' => 'savings', 'iban_last4' => '3456', 'opening_balance_cents' => 302075],
        ];

        $accountsByName = collect($accounts)->mapWithKeys(function (array $attrs) use ($user) {
            $account = $user->accounts()->updateOrCreate(['name' => $attrs['name']], $attrs);

            return [$attrs['name'] => $account];
        });

        $categoriesByName = Category::pluck('id', 'name');

        $transactions = [
            ['date' => '2026-08-05', 'label' => 'Salaire Août', 'category' => 'Revenus', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => 220000, 'reconciled' => true],
            ['date' => '2026-08-04', 'label' => 'Loyer août', 'category' => 'Logement', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -78000, 'reconciled' => true],
            ['date' => '2026-08-03', 'label' => 'Supermarché Carrefour', 'category' => 'Alimentation', 'account' => 'Compte courant Boursorama', 'amount_cents' => -6420, 'reconciled' => true],
            ['date' => '2026-08-02', 'label' => 'Abonnement Navigo', 'category' => 'Transport', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -7520, 'reconciled' => false],
            ['date' => '2026-08-01', 'label' => 'Netflix', 'category' => 'Loisirs', 'account' => 'Compte courant Revolut', 'amount_cents' => -1599, 'reconciled' => false],
            ['date' => '2026-07-31', 'label' => 'Pharmacie', 'category' => 'Santé', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -2250, 'reconciled' => true],
            ['date' => '2026-07-28', 'label' => 'Restaurant Le Petit Zinc', 'category' => 'Alimentation', 'account' => 'Compte courant Boursorama', 'amount_cents' => -4800, 'reconciled' => true],
            ['date' => '2026-07-15', 'label' => 'Virement épargne', 'category' => 'Autres', 'account' => 'Livret A (Crédit Agricole)', 'amount_cents' => 20000, 'reconciled' => true],
            ['date' => '2026-07-10', 'label' => 'Essence', 'category' => 'Transport', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => -5830, 'reconciled' => true],
            ['date' => '2026-07-05', 'label' => 'Salaire Juillet', 'category' => 'Revenus', 'account' => 'Compte courant BNP Paribas', 'amount_cents' => 220000, 'reconciled' => true],
        ];

        foreach ($transactions as $txn) {
            $user->transactions()->updateOrCreate(
                ['label' => $txn['label'], 'date' => $txn['date']],
                [
                    'account_id' => $accountsByName[$txn['account']]->id,
                    'category_id' => $categoriesByName[$txn['category']],
                    'amount_cents' => $txn['amount_cents'],
                    'reconciled' => $txn['reconciled'],
                    'link_type' => 'none',
                ],
            );
        }

        $debts = [
            ['name' => 'Prêt automobile', 'original_amount_cents' => 1800000, 'remaining_amount_cents' => 1120000, 'monthly_payment_cents' => 32000, 'rate_bps' => 390, 'end_date' => '2029-06-15'],
            ['name' => 'Crédit conso — travaux', 'original_amount_cents' => 600000, 'remaining_amount_cents' => 245000, 'monthly_payment_cents' => 18000, 'rate_bps' => 520, 'end_date' => '2027-11-01'],
            ['name' => 'Smartphone en 4 fois', 'original_amount_cents' => 80000, 'remaining_amount_cents' => 20000, 'monthly_payment_cents' => 20000, 'rate_bps' => 0, 'end_date' => '2026-11-05'],
        ];

        foreach ($debts as $debt) {
            $user->debts()->updateOrCreate(['name' => $debt['name']], $debt);
        }

        $budgets = [
            'Logement' => 80000, 'Alimentation' => 50000, 'Transport' => 15000,
            'Loisirs' => 10000, 'Santé' => 10000, 'Autres' => 15000,
        ];

        foreach ($budgets as $name => $cents) {
            $user->budgets()->updateOrCreate(
                ['category_id' => $categoriesByName[$name]],
                ['monthly_amount_cents' => $cents],
            );
        }
    }
}
```

`database/seeders/DatabaseSeeder.php` (replace its `run` method):

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
```

- [ ] **Step 4: Run the test, verify it passes**

```bash
php artisan test --filter=DemoDataSeederTest
```

Expected: PASS.

- [ ] **Step 5: Seed the local dev database and commit**

```bash
php artisan db:seed
cd ../..
git add apps/api
git commit -m "feat(api): add demo data seeder reproducing the frontend's seed dataset"
```

---

### Task 15: Full regression pass

**Files:** none (verification only)

- [ ] **Step 1: Run the entire backend test suite**

```bash
cd apps/api
php artisan test
```

Expected: every test from Tasks 3-14 passes, no failures.

- [ ] **Step 2: Run a manual smoke test against a running server**

```bash
php artisan serve &
curl -s -c /tmp/cj.txt http://127.0.0.1:8000/sanctum/csrf-cookie
curl -s -b /tmp/cj.txt -c /tmp/cj.txt -X POST http://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -H "X-XSRF-TOKEN: $(grep XSRF-TOKEN /tmp/cj.txt | awk '{print $NF}')" \
  -d '{"email":"demo@comptastic.test","password":"password"}'
curl -s -b /tmp/cj.txt http://127.0.0.1:8000/api/accounts | head -c 500
kill %1
```

Expected: the login call returns 204, and the `/api/accounts` call returns
the 5 demo accounts with computed `balance`/`pending_encours` fields.

- [ ] **Step 3: Commit any leftover fixes found during the smoke test**

```bash
cd ..
git status
```

If the smoke test surfaced a bug, fix it, re-run the relevant `php artisan
test --filter=...`, and commit with a `fix(api): ...` message before
considering this plan complete.

---

## Explicitly out of scope for this plan

Per `docs/backend-spec.md` §8: CSV import, editing/deleting accounts or
debts beyond what's listed above, bulk-editing an existing recurring series,
notifications, exports, multi-currency. Also out of scope: wiring the Vue
frontend's `useLedgerStore` to actually call this API — that's a follow-up
plan once this backend is merged and deployed locally.
