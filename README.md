# District 8 Travel League Manager

A PHP/MySQL web application for operating a youth travel baseball league. Provides a public-facing site, a coach portal, and a full admin console — all server-rendered, running on shared hosting.

**Live environments:**
- Production: `http://district8travelleague.com` (main branch)
- Staging: `http://staging.district8travelleague.com` (staging branch)

---

## What's Built

### Public Site
- Home page with Today's Games and Next 7 Days widgets
- Schedule browser with division/team filters
- Standings table (auto-calculated from submitted scores)
- About, Privacy Policy, Terms pages

### Coach Portal (`/coaches/`)
| Feature | Description |
|---------|-------------|
| Score input | Submit game scores with 24-hour edit window |
| Schedule change requests | Submit, track, and receive approval notifications |
| Game postponement | Coach-initiated postponement workflow |
| Team contacts | View and manage team contact directory |
| Team registration | Self-service team registration with email confirmation |
| Profile & password | Per-coach account management |
| Forgot/reset password | Email-based password reset flow |

### Admin Console (`/admin/`)
| Module | Routes |
|--------|--------|
| Programs | Create and manage sport programs (Baseball, Softball) |
| Seasons | Time-bound seasons within programs |
| Divisions | Organizational groupings within seasons |
| Teams | Team roster and assignment management |
| Games | Game records with status tracking; bulk CSV import |
| Schedules | Versioned schedule management with approval workflow |
| Locations | Centralized field/location management |
| Users | Per-user accounts with invitation-based onboarding |
| Settings | App-wide configuration |
| Logs | Activity log viewer |
| League List | Published league contact directory management |
| AI Assistant | Embedded AI chat for admin queries |

### Email Notifications
Automated emails (via PHPMailer/SMTP) for:
- Score submission confirmations
- Schedule change request and approval
- Game postponement and cancellation
- User invitations and email verification
- Password reset

### Authentication
- **Public**: no login required
- **Coaches**: per-user accounts with session auth, email verification, invitation-based signup, role-based team scope
- **Admins**: session auth with role guard; separate admin-side user management

---

## Technology Stack

| Layer | Details |
|-------|---------|
| Runtime | PHP 8.1+ (`ea-php81` on cPanel) |
| Data | MariaDB/MySQL via PDO (`includes/database.php`, prepared statements throughout) |
| Email | PHPMailer 6.8+ (SMTP) |
| Frontend | Server-rendered PHP/HTML, Bootstrap 5, vanilla JS, jQuery |
| Tests | Playwright e2e suite (`playwright.config.ts`) |
| Deployment | Git + cPanel (`.cpanel.yml`), staging + production branches |

> The codebase uses **PDO exclusively** — some older docs show MySQLi examples; ignore them.

---

## Local Development

### Prerequisites
- PHP 8.1+
- MySQL/MariaDB
- Composer
- Node.js + npm (for Playwright tests)
- Git

### Setup

```bash
git clone git@github.com:mocatmlb/league-manager.git
cd league-manager

# PHP dependencies
composer install

# Node dependencies (for tests)
npm install

# Configure local environment
cp includes/config.example.php includes/config.php
# Edit config.php: DB host, name, user, password, SMTP settings

# Create database and import schema
mysql -u root -p -e "CREATE DATABASE d8tl_local;"
mysql -u root -p d8tl_local < database/schema.sql

# Run migrations
php database/migrate.php

# Start dev server (docroot is public/)
php -S localhost:8000 -t public/
```

### Local URLs
| URL | Area |
|-----|------|
| http://localhost:8000 | Public site |
| http://localhost:8000/admin/ | Admin console |
| http://localhost:8000/coaches/ | Coach portal |

### Default Dev Credentials
- **Admin password**: `admin` (change before any shared use)
- **Coach accounts**: created via admin invitation flow or seeded via `database/seeds/`

### Running Tests
```bash
# Playwright e2e tests (requires dev server running)
npx playwright test

# PHP unit tests
php tests/unit/run-unit-tests.php
```

---

## Directory Structure

```
league-manager/
├── public/                  # Web root (Apache docroot)
│   ├── index.php            # Public home page
│   ├── schedule.php         # Public schedule
│   ├── standings.php        # Public standings
│   ├── login.php            # Unified login entry
│   ├── admin/               # Admin console pages
│   ├── coaches/             # Coach portal pages
│   ├── ajax/                # AJAX endpoint handlers
│   └── assets/              # CSS, JS, images
├── includes/                # Shared PHP — loaded via bootstrap.php
│   ├── bootstrap.php        # Common bootstrap (DB, session, auth)
│   ├── database.php         # PDO singleton (Database::getInstance())
│   ├── AuthService.php      # User auth: login, session, roles
│   ├── EmailService.php     # PHPMailer wrapper
│   ├── ScoreService.php     # Score submission logic
│   ├── RescheduleService.php# Schedule change request handling
│   ├── ActivityLogger.php   # Audit log
│   ├── [other *Service.php] # One service class per domain
│   ├── config.php           # Local config (gitignored)
│   ├── config.prod.php      # Production config (gitignored)
│   └── config.staging.php   # Staging config (gitignored)
├── database/
│   ├── schema.sql           # Full schema (source of truth)
│   ├── migrations/          # Incremental migration scripts
│   ├── migrate.php          # Migration runner
│   └── seeds/               # Dev/test seed data
├── tests/
│   ├── unit/                # PHP unit tests
│   └── e2e/                 # Playwright e2e tests
├── docs/                    # All project documentation (see below)
├── scripts/                 # Maintenance and utility scripts
├── logs/                    # Runtime logs (gitignored)
├── backups/                 # DB backups (gitignored)
├── vendor/                  # Composer packages (gitignored)
├── .cpanel.yml              # cPanel Git deployment config
└── composer.json
```

---

## Documentation

All docs live in [`docs/`](./docs/). Start here:

| Document | What it covers |
|----------|---------------|
| [docs/project-overview.md](./docs/project-overview.md) | Purpose, entry points, brownfield notes |
| [docs/architecture.md](./docs/architecture.md) | Layers, auth model, services, data flow |
| [docs/data-models.md](./docs/data-models.md) | DB tables, relationships, migration strategy |
| [docs/api-contracts.md](./docs/api-contracts.md) | HTTP routes, form contracts, AJAX endpoints |
| [docs/component-inventory.md](./docs/component-inventory.md) | Reusable PHP/HTML/JS components |
| [docs/development-guide.md](./docs/development-guide.md) | Local setup, workflow, test details |
| [docs/deployment-guide.md](./docs/deployment-guide.md) | cPanel/Git deployment walkthrough |
| [docs/tech.md](./docs/tech.md) | Hosting stack, PHP selector, SMTP config |
| [docs/SECURITY.md](./docs/SECURITY.md) | Security practices and hardening notes |
| [docs/Features/user-accounts/](./docs/Features/user-accounts/) | User account system design |

---

## Deployment

The app deploys via cPanel Git Version Control using `.cpanel.yml`.

- **Production**: push to `main` → auto-deploys to `district8travelleague.com`
- **Staging**: push to `staging` → auto-deploys to `staging.district8travelleague.com`

Config files (`config.prod.php`, `config.staging.php`) hold environment credentials and are **not committed to version control**.

See [docs/deployment-guide.md](./docs/deployment-guide.md) for the full walkthrough.

### SSH Clone (avoids 403 errors)
```bash
git clone git@github.com:mocatmlb/league-manager.git
```

---

## Security

- All DB access uses PDO prepared statements — no raw query interpolation
- Sessions use hardened configuration (see `includes/security_bootstrap.php`)
- CSRF protection on all state-changing forms
- Credentials are never committed — use `config.*.php` files excluded by `.gitignore`

See [docs/SECURITY.md](./docs/SECURITY.md) for details.

---

## License 123

Proprietary — developed for the District 8 Travel League. All rights reserved.
