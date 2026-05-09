---
project_name: league-manager
user_name: Mike
date: '2026-04-26'
sections_completed:
  - technology_stack
  - language_rules
  - framework_rules
  - testing_rules
  - quality_rules
  - workflow_rules
  - anti_patterns
status: complete
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

| Layer | Source of truth | Notes |
|--------|-----------------|--------|
| PHP | **8.1** in production (`ea-php81` in cPanel); `composer.json` requires `>=8.1` | Match local/staging PHP selector to production. |
| Composer | `district8/travel-league-mvp` | PSR-4: `D8TL\\` → `includes/`; always-autoloaded: `includes/env-loader.php`. |
| Mail | `phpmailer/phpmailer` ^6.8 | SMTP; align with admin email settings. |
| DB | MariaDB / MySQL (shared hosting) | **App code uses PDO** via `includes/database.php` (`Database::getInstance()`, prepared statements). |
| Web | Apache; docroot **`public/`** | Local: `php -S localhost:8000 -t public/` (see `docs/tech.md`). |
| Frontend | Bootstrap 5+, jQuery, vanilla JS | No Node bundler in stack; CDN/local assets. |

**Hosting / deploy:** Shared hosting (A Small Orange–style), cPanel Git, `.cpanel.yml`, env configs `includes/config.prod.php` / `config.staging.php`. Deeper detail: `docs/tech.md`, deployment guides under `docs/`.

---

## Critical Implementation Rules

### Language-specific rules (PHP)

- Define `D8TL_APP` before including app files that guard on it (see `includes/database.php` and similar).
- Prefer **`Database` (PDO)** for all new DB access; do not introduce parallel MySQLi stacks even if older snippets in docs show MySQLi.
- Follow existing CSRF, session, and validation patterns in `includes/` rather than new ad-hoc mechanisms.
- Newer library-style code: **`D8TL\` namespace** under `includes/`; legacy includes remain mixed (`auth.php`, `functions.php`, etc.).

### “Framework” / app architecture (PHP + pages)

- **Entry points:** `public/` (admin under `public/admin/`, coaches under `public/coaches/`, public pages like `schedule.php`, `standings.php`).
- **Shared logic:** `includes/` — services/managers as PascalCase PHP classes; procedural/legacy as lower/snake includes.
- **Auth:** Dual-path user accounts + legacy flows are documented in `docs/Features/user-accounts/user-accounts-implementation.md` (`AuthService`, `LegacyAuthManager`, `UserAccountManager`). Preserve coach/admin behavior when changing login, roles, or permissions.
- **Security baseline:** Prepared statements, session hardening, input handling — align with `docs/tech.md` and `docs/SECURITY.md` and existing `includes/` usage.

### Testing rules

- **Unit-style tests:** `tests/unit/` — lightweight runner `php tests/unit/run-unit-tests.php` (registers tests in `$GLOBALS['__tests']` via `test-helpers.php`; see `AuthTest.php`).
- **Broader checks:** `tests/test-web-functionality.php`, `tests/test-phase1-functionality.php` — use when changes touch end-to-end behavior.
- **`Database::setInstance()`** exists for tests to inject a fake DB connection — use instead of hitting a real DB when adding unit coverage.

### Code quality and style rules

- **Workspace:** `.cursorrules` mandates QA for every change and UI verification when behavior is user-visible; do not claim completion without running relevant checks.
- **Naming:** Mixed legacy is OK inside existing areas; new files under `includes/` should match neighboring style (PascalCase for classes).
- **Do not delete** unrelated comments or working code paths while fixing something else — keep diffs focused.

### Development workflow rules

- Prefer **`docs/`** as product/ops truth; when implementing, **reconcile specs with actual `public/` routes** (MVP docs may describe paths or phases not yet built).
- Deployment, HTTPS, `.htaccess`, and migration steps: use the specific guide under `docs/` that matches the change.

### Critical don’t-miss rules

- **`docs/tech.md` vs code:** Tech doc sometimes shows MySQLi in examples — **canonical implementation is PDO** in `includes/database.php`.
- **Feature docs vs repo:** `docs/tech.md` may link feature paths under `docs/Features/...` that **do not all exist**; only some (e.g. `Features/user-accounts/`) are guaranteed in-tree — verify paths before citing or building to them.
- **Production constraints:** Shared hosting (memory, `mod_php`, no long-running workers) — avoid patterns that assume CLI daemons, Redis, or arbitrary shell.

---

## Usage guidelines

**For AI agents**

- Read this file before implementing non-trivial changes.
- When rules conflict with a one-off request, flag the conflict; default to **security and existing patterns** in `includes/`.
- After stack or auth changes, update this file if new “gotchas” appear.

**For humans**

- Keep this file short and specific; remove rules that become obvious.
- Update when PHP version, hosting, or auth architecture changes.

Last updated: 2026-04-26
