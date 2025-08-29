# Repository Guidelines

## Project Structure & Modules
- `app/`: Laravel application code (Controllers, Models, Services, V5 modules).
- `routes/`: API and web route definitions.
- `database/`: migrations, seeders, factories.
- `tests/`: PHPUnit tests (`Unit`, `Feature`, `V5/*`).
- `resources/` + `public/`: views, assets; Vite config in `vite.config.js`.
- `front/`, `front-legacy/`: frontend assets and legacy UI code.
- `config/`, `scripts/`, `docs/`: configuration, helper scripts, and documentation.

## Build, Test, and Development
- Install: `composer install` and `npm ci` (for assets).
- Configure: `cp .env.example .env` then `php artisan key:generate`.
- Migrate (optional): `php artisan migrate --seed`.
- Serve API: `php artisan serve`.
- Assets (Vite): `npm run dev` (watch) or `npm run build`.
- Tests: `vendor/bin/phpunit` (uses `phpunit.xml`). For CI smoke: `vendor\bin\phpunit -c phpunit.ci.xml`.
- DB schema dump: `composer run db:schema:dump` or `npm run db:schema:dump` (calls `scripts/db/dump-mysql-schema.sh`).

## Coding Style & Naming
- PHP: PSR-12; 4-space indent; strict types where applicable.
- Formatter: run `./vendor/bin/pint` (check: `./vendor/bin/pint --test`).
- Naming: Controllers in `App\Http\Controllers`, Requests in `App\Http\Requests`, Services in `App\Services`, Repositories in `App\Repositories`, V5 modules in `App\V5\Modules`.
- Tests: `SomethingTest.php`, mirror namespace of target class.

## Testing Guidelines
- Framework: PHPUnit 10; suites in `phpunit.xml` (`Unit`, `Feature`, `V5`).
- Write fast, isolated unit tests; cover happy-path and failure cases.
- Use factories/seeders for data; prefer in-memory or dedicated test DB.

## Commits & Pull Requests
- Commits follow Conventional Commits seen in history: `feat:`, `fix:`, `refactor:`, `style:`, with optional scope, e.g., `feat(front): add shared styles`.
- Branch names: `feat/…`, `fix/…`, `chore/…`.
- PRs: clear description, linked issues, steps to test, and screenshots for UI changes; update `docs/` when behavior or API changes.

## Security & Config
- Never commit secrets; use `.env` (examples provided).
- Sanctum/API keys: rotate regularly; limit permissions.
- Swagger (`l5-swagger`) and log viewer should be disabled in production.

