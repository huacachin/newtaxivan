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
