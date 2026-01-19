# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# O projekcie 

Aplikacja służy do przeszukiwania przepisów na podstawie zawartości lodówki, konkretnych składników.
Dodatkowo śledzi czy są wyrzucane jakieś rzeczy, śledzi wydatki na jedzenie

## Project Overview

This is a Laravel 12 application with a React 19 frontend using Inertia.js for SPA-style navigation. It uses the Laravel React Starter Kit with Laravel Fortify for authentication.

## Common Commands

### Development
```bash
composer dev              # Start all dev servers (Laravel, queue, Vite) concurrently
composer dev:ssr          # Start with SSR enabled
```

### Testing
```bash
composer test             # Run linting + all tests
php artisan test          # Run tests only
php artisan test --filter=TestName  # Run specific test
```

### Linting & Formatting
```bash
composer lint             # PHP linting with Pint
composer test:lint        # Check PHP lint without fixing
npm run lint              # ESLint (JS/TS) with auto-fix
npm run format            # Prettier formatting
npm run format:check      # Check formatting without fixing
npm run types             # TypeScript type checking
```

### Build
```bash
npm run build             # Production build
npm run build:ssr         # Production build with SSR
```

### Setup
```bash
composer setup            # Full project setup (install, migrate, build)
```

### QUEUE
```bash
php artisan queue:work --queue=scrap_categories           # Scrap recipes
php artisan queue:work --queue=prepare_ingredients           # prepare ingredients
```

## Architecture

### Backend (Laravel)
- **Routes**: `routes/web.php` (main), `routes/settings.php` (user settings)
- **Controllers**: `app/Http/Controllers/`
- **Actions**: `app/Actions/Fortify/` - Authentication actions (CreateNewUser, ResetUserPassword)
- **Models**: `app/Models/`

### Frontend (React + Inertia)
- **Entry point**: `resources/js/app.tsx`
- **Pages**: `resources/js/pages/` - Inertia pages (auto-resolved from route names)
- **Layouts**: `resources/js/layouts/` - Page layouts (app, auth, settings)
- **Components**: `resources/js/components/` - Reusable components
- **UI Components**: `resources/js/components/ui/` - shadcn/ui components (new-york style)
- **Hooks**: `resources/js/hooks/`
- **Types**: `resources/js/types/`

### Path Aliases
TypeScript uses `@/*` to resolve to `resources/js/*`.

### Key Integrations
- **Inertia.js**: Server-driven SPA with `@inertiajs/react`
- **Wayfinder**: Type-safe Laravel route generation (`@laravel/vite-plugin-wayfinder`)
- **shadcn/ui**: Component library with Radix UI primitives
- **Tailwind CSS v4**: Styling via `@tailwindcss/vite`
- **React Compiler**: Enabled via `babel-plugin-react-compiler`

### Testing
- Uses Pest PHP for testing
- Feature tests use `RefreshDatabase` trait (configured in `tests/Pest.php`)
- Test suites: `tests/Unit/`, `tests/Feature/`
