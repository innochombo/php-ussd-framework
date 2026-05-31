# Changelog

All notable changes to PhpUSSD are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.1.0] — 2026-05-31

### Added

#### `JsonDriver` — JSON gateway for the simulator and REST clients

A new built-in gateway driver that speaks a JSON request/response protocol,
making it possible to connect the [USSD Phone Simulator](https://ussd-phone-simulator.vercel.app/)
(or any HTTP client) to a live backend without the Africa's Talking wire format.

**Request** (`POST`, `application/json`):
```json
{ "sessionId": "...", "serviceCode": "*123#", "msisdn": "265888000001", "input": "1" }
```

**Response** (`application/json`):
```json
{ "type": "CON", "message": "...", "sessionId": "..." }
```

Switch between drivers in `config/app.php`:
```php
// Local dev / simulator
'gateway' => \PhpUssd\Gateway\JsonDriver::class,

// Africa's Talking production
'gateway' => \PhpUssd\Gateway\AfricasTalkingDriver::class,
```

#### JSON request body auto-detection in `index.php`

The entry point now reads `php://input` when `Content-Type: application/json` is
present, so browser and REST clients can POST JSON without any change to menu code.
Form-encoded requests (Africa's Talking, Nalo) continue to work via `$_POST`.

#### `CorsMiddleware` — configurable CORS for browser clients

A built-in middleware that handles `OPTIONS` preflights and sets
`Access-Control-Allow-*` headers for allowed origins. Configured once in
`app.php`; no CORS logic needed in `index.php`.

```php
'middleware' => [
    [
        'class'   => \PhpUssd\Http\CorsMiddleware::class,
        'options' => [
            'allow_origins' => [
                'https://ussd-phone-simulator.vercel.app',
                'http://localhost:5173',
            ],
            'allow_methods' => ['GET', 'POST', 'OPTIONS'],
            'allow_headers' => ['Content-Type', 'X-Requested-With'],
            'max_age'       => 600,
        ],
    ],
],
```

The middleware pipeline runs outermost-first. `OPTIONS` preflights are
short-circuited before the gateway, session, or any menu is touched.

#### Documentation overhaul

- **README.md** — complete rewrite with a full **Features** table covering core
  state machine, menu abstractions, gateway drivers, session drivers,
  internationalisation, HTTP client, middleware, and simulator integration.
  Quick Start now shows JSON body parsing. Gateway and directory structure
  sections updated to include `JsonDriver`.

- **docs/guidelines-usage.md** — two new sections: **Middleware** (interface,
  registration forms, execution order diagram) and **CORS — CorsMiddleware**
  (how it works, all config options, dev vs production examples, custom
  middleware recipe).  Gateway drivers table expanded with a Response format
  column and `JsonDriver` row.  Entry point example updated with JSON body
  detection.

- **docs/contributing-and-prompts.md** — AI agent description updated to
  include `JsonDriver` and its JSON contract.

### Fixed

#### PSR-4 filename mismatch causing boot failure on every request

`AppOverviewMenu` was declared inside `example/menus/OverviewMenus.php`.
The PSR-4 autoloader looks for `AppOverviewMenu.php`; when it found nothing,
`is_a()` silently returned `false` and `MenuRouter::register()` threw
`InvalidArgumentException` before any request was processed.

**Fix:** renamed `OverviewMenus.php` → `AppOverviewMenu.php` and ran
`composer dump-autoload`. All seven example menus now pass the `is_a()` check.

#### Stale PHP dev-server processes blocking updated code

Multiple PHP server processes from previous sessions were still listening on
port 8000 (one started the previous day). New code was unreachable because curl
and the browser were hitting the old processes.

**Fix:** kill all `php` processes before starting a fresh server instance.

#### Early CORS handler removed from `index.php`

An emergency CORS handler was temporarily added to `index.php` as a workaround
while the boot failure above was being diagnosed. Now that the boot is stable,
`CorsMiddleware` handles all CORS concerns and the inline handler has been
removed. `index.php` is back to its documented minimal form.

### Changed

- `example/config/app.php` — default gateway switched to `JsonDriver` for
  local development; `CorsMiddleware` origin list expanded to include both
  `https://ussd-phone-simulator.vercel.app` and `http://localhost:5173`.

---

## [1.0.0] — prior release

Initial release: Application kernel, state machine navigator, Africa's Talking
and Nalo gateway drivers, FileSessionManager, RedisSessionManager,
ArraySessionManager, LanguageManager, HttpClient with retry, MultiStepMenu
trait, PaginatedListMenu, MenuGuardInterface, and 75 passing tests.
