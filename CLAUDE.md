# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

TaxiVan is a Laravel 11 taxi fleet management system for tracking vehicles, drivers, owners, departures, payments, debts, and cash operations. The app is fully localized in Spanish (locale: `es`, timezone: `America/Lima`).

## Development Commands

```bash
# Full dev stack (server + queue + logs + Vite) — preferred
composer run dev

# Individual services
php artisan serve       # PHP server on :8000
npm run dev             # Vite dev server (hot reload)
npm run build           # Production asset build

# Database
php artisan migrate
php artisan db:seed

# Code formatting
./vendor/bin/pint

# Testing
php artisan test
php artisan test --filter=TestName      # Single test
php artisan test tests/Unit             # Unit tests only
./vendor/bin/phpunit tests/Unit/SomeTest.php
```

## Architecture

### Stack
- **Backend**: Laravel 11, Livewire 3, Spatie Laravel Permission (RBAC)
- **Frontend**: Tailwind CSS 3, Vite 5, jQuery UI Datepicker (Spanish locale), SweetAlert
- **Exports**: Maatwebsite Excel 3.1 (24 dedicated export classes in `app/Exports/`)

### Request Flow
Routes (`routes/web.php`) → Controllers (`app/Http/Controllers/`) → Livewire components (`app/Livewire/`) → Blade views (`resources/views/livewire/`).

Controllers are thin — they render views and handle Excel downloads. Business logic lives in Livewire components and, for complex operations, in `app/Services/`.

### Livewire Pattern
All interactive UI uses Livewire 3. Components:
- Use `public function rules()` for validation
- Communicate via `$this->dispatch('eventName', [...])` and `#[On('eventName')]` attributes
- Alert pattern: `$this->dispatch('successAlert', ['message' => '...'])`

### Export Pattern
Each export route maps to a dedicated class in `app/Exports/` implementing `FromCollection`. Controllers call `Excel::download(new SomeExport(), $filename)`.

### RBAC
Routes are protected via middleware: `['auth', 'permission:some-permission']` or `['auth', 'role:admin']`. Managed through Spatie Laravel Permission.

### Key Domain Models
| Model | Purpose |
|---|---|
| `Vehicle` | Taxi plates; includes badge logic for SOAT/tech review/certificate expiry |
| `Driver` / `Owner` | Personnel linked to vehicles |
| `Departure` | Trip records with pricing and passengers |
| `Payment` | Driver/owner payment records |
| `Expense` / `Income` | Cash operations with image support |
| `DebtDay` / `DebtDayDetail` | Daily and detailed debt tracking |
| `CostPerPlate` / `CostPerPlateDay` | Dynamic per-vehicle pricing |
| `Concept` | Predefined expense/income categories |

### Vehicle Model Conventions
- `plate` setter normalizes to uppercase, strips non-alphanumeric (except `-` and space)
- `getBadgesAttribute()` returns color-coded expiry status for SOAT, technical revision, and certificate
- Scopes: `active()`, `byPlate()`, `byCondition()`

### Departure/Payment Scopes
Common query scopes: `betweenDates($from, $to)`, `excludeHQ()`, `support()`

## Asset Pipeline

Vite processes three entry points:
1. `resources/css/app.css` (Tailwind)
2. `resources/js/app.js`
3. `public/assets/scss/style.scss` (custom SCSS, SASS deprecation warnings suppressed)

### Asset cache-busting

Two strategies coexist:

- **Vite assets** (CSS via SCSS, JS via `app.js`): Vite injects a hash into the filename (`style-{hash}.css`). Cache-busting is automatic — never manual.
- **Static `public/assets/js/*.js` files** (`custom.js`, `script.js`, `customizer.js`): NOT compiled by Vite. They use the `versioned_asset()` helper which appends `?v={filemtime}` so the URL changes when the file is edited.

**When editing a JS in `public/assets/js/`** (e.g., `custom.js`), do NOT add a manual version query string. The helper handles it — just commit the file change, deploy, and `filemtime` updates the URL automatically so browsers (including stubborn mobile caches) fetch the fresh file.

**When loading a new static asset from `public/`** in a Blade template, use `versioned_asset('assets/...')` instead of `asset('assets/...')`. Vendor assets (Bootstrap, jQuery, Tabler icons) stay with `asset()` because they don't change.

Helper lives in `app/Helpers/assets.php` and is autoloaded via `composer.json`'s `autoload.files`.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== laravel/v11 rules ===

# Laravel 11

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project uses the streamlined Laravel 11+ structure: register middleware, exceptions, and routing in `bootstrap/app.php` and service providers in `bootstrap/providers.php`. There is no `app/Http/Kernel.php` or `app/Console/Kernel.php`, and commands in `app/Console/Commands/` auto-register.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

## New Artisan Commands

- List Artisan commands using Boost's MCP tool, if available. New commands available in Laravel 11:
    - `php artisan make:enum`
    - `php artisan make:class`
    - `php artisan make:interface`

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
