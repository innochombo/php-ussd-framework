# Guidelines & Usage

A practical guide to building USSD applications with PhpUSSD. Start with the [framework philosophy](framework-philosophy.md) if you want to understand the design reasoning first.

---

## Installation

```bash
composer require phpussd/phpussd
```

PHP 8.1 or higher is required. No other runtime dependencies.

---

## Project layout

There is no required directory structure. The convention used by the example app is:

```
your-app/
  config/
    app.php          ← Framework configuration (gateway, session, menus, languages)
    MenuIds.php      ← Menu ID constants (a plain PHP file, not autoloaded by default)
  menus/
    LanguageMenu.php
    HomeMenu.php
    ...
  lang/
    English.php
    Chichewa.php
  guards/
    AuthGuard.php
  storage/
    sessions/        ← File session storage (if using FileSessionManager)
  index.php          ← Entry point
```

---

## Entry point

```php
<?php
// index.php
require 'vendor/autoload.php';
require 'config/MenuIds.php'; // not autoloaded — must be required explicitly

$app = new \PhpUssd\Core\Application(require 'config/app.php');
echo $app->run($_POST ?: $_GET);
```

`Application::run()` accepts an array of raw gateway parameters (typically `$_POST`). It parses the request, loads the session, runs the navigator, saves the session, and returns the serialized response string ready to echo.

---

## Configuration reference

`config/app.php` returns a plain PHP array:

```php
<?php
use PhpUssd\Gateway\AfricasTalkingDriver;
use PhpUssd\Session\FileSessionManager;

return [
    // ── Gateway ──────────────────────────────────────────────────────────────
    'gateway' => AfricasTalkingDriver::class,
    // Available: AfricasTalkingDriver::class, NaloDriver::class
    // Custom: any class implementing GatewayDriverInterface

    // ── Session ──────────────────────────────────────────────────────────────
    'session' => [
        'driver' => FileSessionManager::class,
        'path'   => __DIR__ . '/../storage/sessions', // required for FileSessionManager
        'ttl'    => 300,                               // seconds; 0 = no expiry
    ],

    // ── Languages ────────────────────────────────────────────────────────────
    'default_language' => 'en',
    'languages' => [
        'en' => \App\Lang\English::class,
        'ny' => \App\Lang\Chichewa::class,
    ],

    // ── API / HTTP client ─────────────────────────────────────────────────────
    'api' => [
        'base_url'       => 'https://api.yourapp.com/v1',
        'timeout'        => 30,   // seconds
        'retries'        => 1,    // retry count on network failure
        'throw_on_error' => false, // if true, throws on 4xx/5xx
        'headers'        => [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ],
    ],

    // ── Navigation ────────────────────────────────────────────────────────────
    'default_menu' => MenuIds::LANGUAGE,  // First menu shown on new session
    'main_menu'    => MenuIds::HOME,      // Target of "00" (main menu shortcut)

    // ── Menu registry ─────────────────────────────────────────────────────────
    'menus' => [
        MenuIds::LANGUAGE => \App\Menus\LanguageMenu::class,
        MenuIds::HOME     => \App\Menus\HomeMenu::class,
        MenuIds::PROFILE  => \App\Menus\ProfileMenu::class,
        // ... every menu in the application
    ],
];
```

---

## Menu ID constants

Define all menu IDs as constants in `config/MenuIds.php`:

```php
<?php
final class MenuIds
{
    const LANGUAGE = 'MENU_LANGUAGE';
    const HOME     = 'MENU_HOME';
    const PROFILE  = 'MENU_PROFILE';
}
```

Using constants instead of bare strings means typos become compile-time errors, and IDE tooling can navigate between menus via references.

---

## Writing menus

### AbstractMenu — the base class

Every menu extends `AbstractMenu` and implements three methods:

```php
<?php
namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;

class HomeMenu extends AbstractMenu
{
    public function display(): UssdResponse
    {
        // Build and return the screen shown to the user.
        return $this->formatMenu($this->t('home_title'), [
            '1' => $this->t('crop_advice'),
            '2' => $this->t('weather_tips'),
            '3' => $this->t('market_prices'),
        ]);
    }

    public function handleInput(): string|UssdResponse
    {
        // Return a menu ID to navigate, or a UssdResponse to display directly.
        return match ($this->lastInput) {
            '1' => \MenuIds::CROP_ADVICE,
            '2' => \MenuIds::WEATHER_TIPS,
            '3' => \MenuIds::MARKET_PRICES,
            default => $this->errorThen($this->t('invalid_input'), \MenuIds::HOME),
        };
    }

    public function getParentMenu(): ?string
    {
        // Return the menu ID for "0. Back", or null if this is a root menu.
        return null;
    }
}
```

### Properties and helpers available in every menu

| Symbol | Type | Description |
|---|---|---|
| `$this->lastInput` | `string` | The user's current input segment |
| `$this->session` | `SessionManagerInterface` | Read/write session data |
| `$this->lang` | `LanguageManager` | Active language and translations |
| `$this->http` | `HttpClient` | Preconfigured HTTP client |
| `$this->t('key')` | `string` | Translate a key |
| `$this->tf('key', ...$args)` | `string` | Translate with `sprintf` formatting |
| `$this->respond($body)` | `UssdResponse` | CON response (session continues) |
| `$this->end($body)` | `UssdResponse` | END response (session terminates) |
| `$this->formatMenu($title, $options)` | `UssdResponse` | Numbered menu with title |
| `$this->navOptions()` | `array` | `['0' => 'Back', '00' => 'Main Menu']` |
| `$this->errorThen($msg, $menuId)` | `string` | Store error in session, return menu ID |
| `$this->consumeError()` | `?string` | Get and clear pending error message |

### Displaying errors

Use `errorThen()` to show an error on the next display without losing the user's position:

```php
public function handleInput(): string|UssdResponse
{
    if (!preg_match('/^\d{9,10}$/', $this->lastInput)) {
        return $this->errorThen($this->t('invalid_phone'), \MenuIds::PHONE);
    }
    // ...
}

public function display(): UssdResponse
{
    $error = $this->consumeError();
    $title = $error ? "{$error}\n\n{$this->t('enter_phone')}" : $this->t('enter_phone');
    return $this->formatMenu($title, $this->navOptions());
}
```

### Lifecycle hooks

Menus can optionally implement:

```php
public function onEnter(): void
{
    // Called when the navigator enters this menu for the first time.
}

public function onLeave(): void
{
    // Called when the navigator navigates away from this menu.
}
```

Use `onLeave()` for cleanup (clear partial form data) and `onEnter()` for pre-loading data into session.

---

## Multi-step forms — MultiStepMenu

The `MultiStepMenu` trait eliminates manual step-counter management for flows that capture multiple inputs in sequence.

```php
<?php
namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\AbstractMenu;
use PhpUssd\Menu\MultiStepMenu;

class AiHelperMenu extends AbstractMenu
{
    use MultiStepMenu;

    protected function steps(): array
    {
        return ['select_topic', 'show_advice'];
    }

    public function display(): UssdResponse
    {
        return match ($this->currentStep()) {
            'select_topic' => $this->showTopics(),
            'show_advice'  => $this->showAdvice(),
            default        => $this->showTopics(),
        };
    }

    public function handleInput(): string|UssdResponse
    {
        if (!in_array($this->lastInput, ['1', '2', '3', '4'], true)) {
            return $this->errorThen($this->t('invalid_input'), \MenuIds::AI_HELPER);
        }
        $this->captureAndAdvance(); // stores lastInput, moves to next step
        return \MenuIds::AI_HELPER;
    }

    private function showAdvice(): UssdResponse
    {
        $topic = $this->getStepValue('select_topic');
        $this->clearSteps(); // clean up after completion
        return $this->end($this->t("ai_helper_{$topic}"));
    }

    public function getParentMenu(): ?string { return \MenuIds::HOME; }
}
```

### MultiStepMenu API

| Method | Description |
|---|---|
| `steps(): array` | Define step names in order (must implement) |
| `currentStep(): string` | Name of the active step |
| `advanceStep(): void` | Move to the next step |
| `rewindStep(): void` | Move to the previous step |
| `captureAndAdvance(): void` | Store `lastInput` for current step and advance |
| `storeStepValue(string $step, mixed $value): void` | Store a value for a named step |
| `getStepValue(string $step): mixed` | Retrieve stored value for a step |
| `isFirstStep(): bool` | True if on the first step |
| `isLastStep(): bool` | True if on the last step |
| `clearSteps(): void` | Remove all step data from session |

Step data is **namespaced per class**. Two multi-step menus in the same session will never overwrite each other's data.

---

## Paginated lists — PaginatedListMenu

Use `PaginatedListMenu` for any list that might not fit a single USSD screen. The base class handles fetching, caching, page boundaries, and navigation inputs (`99` = next, `98` = previous).

```php
<?php
namespace App\Menus;

use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\PaginatedListMenu;

class CropListMenu extends PaginatedListMenu
{
    protected function fetchItems(): array
    {
        // Called once per session; results are cached in session.
        $response = $this->http->get('/crops/available');
        return $response->ok() ? ($response->get('') ?? []) : [];
    }

    protected function itemLabel(mixed $item): string
    {
        // The string shown in the numbered list.
        return $item['name'] ?? 'Unknown';
    }

    protected function onItemSelected(mixed $item): string|UssdResponse
    {
        // Called when the user selects an item by number.
        $this->session->set('selected_crop', $item);
        return \MenuIds::CROP_DETAILS;
    }

    protected function listTitle(): string { return $this->t('crop_list_title'); }
    protected function currentMenuId(): string { return \MenuIds::CROP_LIST; }
    public function getParentMenu(): ?string { return \MenuIds::HOME; }
}
```

The `fetchItems()` result is stored in session on first call and reused on subsequent pages. This avoids repeat API calls during pagination.

---

## Guards — MenuGuardInterface

Guards protect menus from unauthorised access without embedding auth logic inside the menu.

```php
<?php
namespace App\Guards;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Menu\MenuGuardInterface;
use PhpUssd\Session\SessionManagerInterface;

class AuthGuard implements MenuGuardInterface
{
    public function passes(UssdRequest $request, SessionManagerInterface $session): bool
    {
        return $session->has('auth_token');
    }

    public function onFail(UssdRequest $request, SessionManagerInterface $session): string|UssdResponse
    {
        return \MenuIds::LOGIN; // Redirect unauthenticated users to login
    }
}
```

Apply a guard to a menu by implementing `guards()`:

```php
class ProfileMenu extends AbstractMenu
{
    public function guards(): array
    {
        return [AuthGuard::class];
    }
    // ...
}
```

Guards are checked by the navigator before `display()` and before `handleInput()`. Multiple guards can be applied; the first failing guard short-circuits the rest.

---

## Session management

### Available drivers

| Driver | Config key | When to use |
|---|---|---|
| `FileSessionManager` | `'driver' => FileSessionManager::class` | Development and low-traffic deployments |
| `RedisSessionManager` | `'driver' => RedisSessionManager::class` | Production (requires `ext-redis`) |
| `ArraySessionManager` | n/a (use directly in tests) | Unit and integration tests |

### FileSessionManager configuration

```php
'session' => [
    'driver' => FileSessionManager::class,
    'path'   => __DIR__ . '/../storage/sessions',
    'ttl'    => 300, // seconds until session expires
],
```

Sessions are stored as JSON files named after the sanitised session ID. The `storage/sessions/` directory must be writable by the web server.

### RedisSessionManager configuration

```php
'session' => [
    'driver' => RedisSessionManager::class,
    'host'   => '127.0.0.1',
    'port'   => 6379,
    'ttl'    => 300,
],
```

Requires the `ext-redis` PHP extension. Each session is a single JSON string stored with automatic Redis TTL expiry.

### Session API in menus

```php
$this->session->get('key');              // Read (dot-notation: 'user.profile.name')
$this->session->get('key', 'default');   // Read with fallback
$this->session->set('key', $value);      // Write
$this->session->forget('key');           // Delete
$this->session->has('key');              // Boolean existence check
$this->session->all();                   // Return all data as array
```

Session writes accumulate in memory and are flushed once at the end of the request via `save()`. You do not need to call `save()` yourself.

---

## Translations

### Creating a language provider

```php
<?php
namespace App\Lang;

use PhpUssd\I18n\AbstractLanguage;

class Swahili extends AbstractLanguage
{
    protected string $code = 'sw';
    protected string $name = 'Swahili';

    protected array $translations = [
        'welcome'       => 'Karibu kwenye Mfumo wa Msaada wa Mkulima.',
        'choose_language' => 'Chagua lugha yako',
        'back'          => 'Rudi',
        'invalid_input' => 'Ingizo batili. Tafadhali jaribu tena.',
        // ... all keys used by the application
    ];
}
```

### Registering a language

```php
// config/app.php
'languages' => [
    'en' => \App\Lang\English::class,
    'sw' => \App\Lang\Swahili::class,
],
'default_language' => 'en',
```

### Switching language in a menu

```php
$this->lang->setActive('sw');
$this->session->set('_language', 'sw'); // Persist across requests
```

On subsequent requests, the `Application` bootstrap reads `_language` from session and calls `setActive()` before any menu is invoked.

### Missing translations

A key that has no translation entry returns `[missing:key_name]`. This is intentional — missing strings are immediately visible in USSD output during development.

---

## HTTP client

The `HttpClient` is pre-configured from `api.*` in `app.php` and injected as `$this->http` in every menu.

```php
// GET request
$response = $this->http->get('/crops', ['region' => 'central']);

// POST request
$response = $this->http->post('/auth/login', [
    'phone' => $this->session->get('phone_number'),
    'pin'   => $this->lastInput,
]);

// Checking responses
$response->ok();             // true for 2xx
$response->failed();         // true for 4xx/5xx or network error
$response->get('token');     // dot-notation access to JSON body
$response->get('user.name'); // nested access
$response->hasErrors();      // true if body contains an 'errors' key
$response->data;             // raw decoded array
$response->status;           // HTTP status code
```

### Bearer token auth

```php
$response = $this->http
    ->withToken($this->session->get('auth_token'))
    ->get('/profile');
```

### Custom headers

```php
$response = $this->http
    ->withHeaders(['X-Tenant-Id' => 'acme'])
    ->post('/orders', $payload);
```

The client retries failed requests using exponential back-off. The retry count is set via `api.retries` in config.

---

## Gateway drivers

The gateway driver normalises the incoming request from a specific USSD provider into a `UssdRequest` and serialises `UssdResponse` back into the provider's expected format.

### Built-in drivers

| Driver | Provider | Request fields |
|---|---|---|
| `AfricasTalkingDriver` | Africa's Talking | `sessionId`, `phoneNumber`, `serviceCode`, `text`, `networkCode` |
| `NaloDriver` | Nalo Solutions | `sessionid`, `msisdn`, `userdata`, `network`, `msgtype` |

### Custom driver

```php
<?php
namespace App\Gateway;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Gateway\GatewayDriverInterface;

class MyGatewayDriver implements GatewayDriverInterface
{
    public function parse(array $payload): UssdRequest
    {
        return new UssdRequest(
            sessionId:   $payload['session_id'],
            phoneNumber: $payload['msisdn'],
            text:        $payload['input'] ?? '',
            serviceCode: $payload['service_code'] ?? '',
            networkCode: $payload['network'] ?? '',
        );
    }

    public function serialize(UssdResponse $response): string
    {
        $prefix = $response->isContinue() ? 'CON' : 'END';
        return "{$prefix} {$response->body()}";
    }

    public function sendHeaders(): void
    {
        header('Content-Type: text/plain');
    }
}
```

Register in config:

```php
'gateway' => \App\Gateway\MyGatewayDriver::class,
```

---

## Navigation reference

The `UssdRequest` object exposes navigation helpers that the framework uses internally. You can also use them in menus and guards:

```php
$request->isInitial()   // true on the first request in a new session (text == '')
$request->isBack()      // true when input is '0'
$request->isMainMenu()  // true when input is '00'
$request->isNextPage()  // true when input is '99'
$request->isPrevPage()  // true when input is '98'
$request->lastInput     // most recent input segment (after last *)
$request->text          // full accumulated input string (e.g. '1*2*3')
$request->inputSegments() // ['1', '2', '3']
```

The navigator handles `0`, `00`, `99`, and `98` before `handleInput()` is called. Menus only receive other inputs via `handleInput()`.

---

## Common patterns

### Auth flow

A typical login sequence uses a series of menus with a guard protecting all menus after login:

```
LanguageMenu → PhoneMenu → PinMenu → HomeMenu (guard: AuthGuard)
```

The `PinMenu` validates credentials against an API, stores `auth_token` in session on success, and navigates to `HomeMenu`. The `AuthGuard` checks for `auth_token` and redirects to `PhoneMenu` if it is missing.

### Static information screen

For informational screens with no item selection, display text and provide navigation:

```php
public function display(): UssdResponse
{
    $body = $this->t('prices_title') . "\n"
          . "Maize: MK 8,000\n"
          . "Groundnut: MK 12,000";

    return $this->formatMenu($body, $this->navOptions());
}

public function handleInput(): string|UssdResponse
{
    return $this->errorThen($this->t('invalid_input'), \MenuIds::PRICES);
}
```

### Session-based detail view

Store a selection in session and navigate to the same menu to display details:

```php
public function display(): UssdResponse
{
    $selected = $this->session->get('selected_item');
    if ($selected !== null) {
        $this->session->forget('selected_item');
        return $this->respond($selected['detail']);
    }
    return $this->formatMenu($this->t('list_title'), $this->buildOptions());
}

public function handleInput(): string|UssdResponse
{
    $item = $this->items[$this->lastInput] ?? null;
    if ($item === null) {
        return $this->errorThen($this->t('invalid_input'), \MenuIds::LIST);
    }
    $this->session->set('selected_item', $item);
    return \MenuIds::LIST; // navigate to same menu; display() shows details
}
```

---

## Testing

Run the built-in test suite:

```bash
php run_tests.php
```

### Testing menus in isolation

Use `ArraySessionManager` to avoid disk or network I/O in tests:

```php
use PhpUssd\Session\ArraySessionManager;
use PhpUssd\Core\UssdRequest;
use PhpUssd\I18n\LanguageManager;
use PhpUssd\Http\HttpClient;

// Clean state between test cases
ArraySessionManager::flush();

$session = new ArraySessionManager();
$session->load('test-session-id');

$request = new UssdRequest(
    sessionId:   'test-session-id',
    phoneNumber: '+265880000001',
    text:        '1',
);

$menu = new \App\Menus\HomeMenu();
$menu->boot($request, $session, new LanguageManager(['en' => English::class], 'en'), new HttpClient());

$result = $menu->handleInput();
assert($result === \MenuIds::CROP_ADVICE);
```

### Tips

- Call `ArraySessionManager::flush()` before each test case to avoid cross-test contamination.
- Pre-populate session with `$session->set('key', $value)` before calling `display()` or `handleInput()` to simulate mid-flow state.
- Test both the happy path and invalid input in every menu.
- For multi-step menus, test each step independently by setting the step state directly in session before the test.
