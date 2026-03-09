# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Yii 2** PHP application that integrates idosell (v3) with the Samba.ai platform. It manages XML/CSV feed generation, order/product/customer synchronization, and OAuth2-based API access. The app name is "Samba.ai idosell v3 connector".

## Common Commands

```bash
# Install dependencies
composer install

# Run database migrations
php yii migrate

# Start development server (localhost:8080)
composer start

# Run console commands (examples)
php yii xml-generator/generate-orders
php yii xml-generator/generate-products
php yii xml-generator/generate-customers
php yii xml-generator/generate-categories
php yii xml-generator/prepare-queue

# Run tests
./vendor/bin/codecept run
./vendor/bin/codecept run unit
./vendor/bin/codecept run functional
```

## Architecture

### Entry Points
- **Web**: `web/index.php` → config loaded from `config/web.php`
- **Console (CLI)**: `yii` → config from `config/console.php`

### Module System
Modules are in `modules/` and auto-registered via `ModuleBootstrap.php`, which loads routes from `modules/{name}/config/_routes.php`.

Active modules:
- **xml_generator** — core integration engine; generates XML/CSV feeds; also has console commands in `commands/`
- **api** — REST API with OAuth2/JWT-based auth; controllers for external integration and admin panel
- **iai** — internal application/authorization submodules
- **idosellv3** — idosell v3 API connector models

### Integration Queue
Queue-based sync system:
- Queue status: `0`=pending, `1`=running, `2`=completed, `99`=error
- Queue model: `models/Queue.php`
- Bash scripts in the project root (`integration-bash-*.sh`) wrap console commands with file locking to prevent parallel execution

### Cron Jobs (see `crontablist.txt`)
```
* * * * *    yii xml-generator/generate-countries
1 23 * * *   yii xml-generator/prepare-queue
10 * * * *   yii xml-generator/subscriber-sync

* * * * *    integration-bash.sh
* * * * *    integration-bash-orders.sh
*/10 * * * * integration-bash-ordersparalel.sh
*/10 * * * * integration-bash-customers.sh
*/10 * * * * integration-bash-customersparalel.sh
* * * * *    integration-bash-products.sh
* * * * *    integration-bash-productsparalel.sh
*/10 * * * * integration-bash-subscribers.sh
*/10 * * * * integration-bash-subscribersparalel.sh
*/10 * * * * integration-bash-phonesubscribers.sh
*/10 * * * * integration-bash-phonesubscribersparalel.sh
```

### Key Models
- `models/User.php` — Yii2 identity class
- `models/Queue.php` — integration job queue
- `models/Accesstokens.php` — OAuth2/API tokens
- `models/Orders.php`, `Ordersv2.php` — order data
- `models/Product.php`, `IdiosellProduct.php` — product data

### Config
- `config/web.php` — web app config (modules, URL rules, session settings)
- `config/console.php` — CLI app config
- `config/db.php` — database connection (MySQL, db: `samba_idosell`)
- `config/params.php` — application parameters

### Authentication
- Web: session-based via `app\models\User` identity
- API: OAuth2/OpenID Connect (`steverhoades/oauth2-openid-connect-client`) + JWT (`lcobucci/jwt 3.3`)
- RBAC: file-based PhpManager, rules in `rbac/`

### Database
- MySQL, database name: `samba_idosell`
- Migrations in `migrations/`
- Adminer available at `/adminer.php` in dev

### Testing
- Framework: Codeception (config: `codeception.yml`)
- Test config: `config/test.php` (CSRF disabled, uses `app\models\UserTest`)
- Test suites: `tests/unit`, `tests/functional`, `tests/acceptance`
