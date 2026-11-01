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
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

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

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

# Code Quality Gate

This section lives outside the `<laravel-boost-guidelines>` block so `php artisan boost:update` keeps it. Keep it identical in `AGENTS.md` and `CLAUDE.md`.

CI (`.github/workflows/quality.yml`) runs the same checks on every push to `main` and every pull request; a change is not done until they pass locally:

```bash
composer quality      # Pint check, Rector dead-code check, PHPStan, Pest
npm run lint:check    # frontend changes: ESLint errors, including unused imports and variables
npm run typecheck     # frontend changes: vue-tsc
```

Single checks: `composer format:check` (fix: `composer format`), `composer refactor:check` (fix: `composer refactor`), `composer analyse`, `composer test`.

## What is enforced

- PHPStan level 9 with Larastan over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/` and `tests/`, plus phpstan-strict-rules, deprecation rules, the Pest plugin, `shipmonk/dead-code-detector` (unused methods, properties, constants and enum cases, including public ones) and cognitive complexity (at most 15 per method and 80 per class).
- Rector's dead-code set: unused variable assignments, unused private methods and parameters, dead branches.
- Laravel Pint formatting (it also removes unused imports) and the Pest suite.
- Frontend: ESLint errors (`@typescript-eslint/no-unused-vars` is an error) and `vue-tsc`.

## Rules for every change

- Name variables, methods and classes after what they mean in the business (`$outstandingBalance`, `$leaseToBill`, `lockPayableChargesForRenter()`), not `$data`, `$result`, `$c` or `$x`.
- Leave no unused variables, imports, parameters, methods, classes or dead branches. Before deleting, check the framework entry points the analyzers cannot see: routes, policies, the service container, scheduled jobs and commands, Eloquent accessors and scopes, model trait boot methods, Blade and Inertia props.
- Keep each method to one purpose. Split long methods by intent (validate, calculate, persist, present), not into one-line forwarders, and avoid layers (interfaces, repositories, factories) that add no behavior.
- Reuse instead of copying: the domain services (`FinancialObligationService`, `SecurityDepositService`, `MaintenanceExpenseService`, ...), enum values and labels in `app/Enums`, model accessors and helpers (`Lease::contract_number`, `Lease::hasSecurityDeposit()`, `Workspace::currencySymbol()`), `DocumentStorageService` for every file, and `App\Support\UnitTree` for in-memory unit hierarchies. Do not copy label maps, money calculations or queries into controllers.
- Declare precise types: parameter and return types, generics (relations as `@return BelongsTo<Unit, $this>`, `Collection<int, Lease>`), array shapes. Fix the type rather than silencing the analyzer with a cast, an inline `@var` or an `assert()`.
- Larastan types model attributes from the migrations, but unsaved or unrefreshed models still hold raw input (strings, `null`). Keep `(int)`, `(float)` and `(bool)` casts on model attributes and do not trust "always true/false" reports about database-defaulted columns without checking.
- Follow Laravel conventions: `env()` only inside `config/`, Form Requests for validation, thin controllers that delegate to services and actions, `$this->authenticatedUser($request)` for the signed-in user, `instanceof` checks (or `ResolvesRouteLease`) for route parameters.
- Do not change business behavior (calculations, authorization, financial rules, API and Inertia props, database semantics) without approval. Every bug fix and behavior change comes with a test; behavior-preserving refactors of untested code get a characterization test first.

## Suppressions and the baseline

- No `@phpstan-ignore*` comments. A new `ignoreErrors` entry in `phpstan.neon` must be narrowed by identifier, message and path and carry a comment explaining why the analyzer is wrong; the existing entries document theirs.
- `phpstan-baseline.neon` only holds level-9 findings that existed before the gate (`mixed` values at request and payload boundaries). It may only shrink: never add entries to it. After fixing a baselined error, run `composer analyse:baseline` and commit the smaller file. New and modified code must be clean at level 9.
