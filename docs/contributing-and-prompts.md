# Contributing & AI Prompts

Guidelines for contributing code, documentation, and example applications to PhpUSSD, plus guidance for AI agents and developers using AI-assisted workflows.

---

## Contributing

### Getting started

1. Fork the repository on GitHub.
2. Clone your fork locally.
3. Create a feature branch from `main`: `git checkout -b feat/your-feature`.
4. Make your changes (see conventions below).
5. Run the test suite: `php run_tests.php` — all tests must pass.
6. Open a pull request against `main` with a clear description.

### Code conventions

- PHP 8.1+ features are welcome (match expressions, fibers, named arguments, readonly properties).
- All new classes must carry `declare(strict_types=1)`.
- One class per file. File name matches the class name exactly.
- No external dependencies. The framework must remain installable with no `composer.json` `require` entries beyond `php`.
- All public API methods must have parameter and return types.
- Tests must be added for any new framework behaviour. Use the existing inline test pattern in `run_tests.php`.

### What belongs where

| Change type | Where it lives |
|---|---|
| New gateway driver | `src/Gateway/` |
| New session driver | `src/Session/` |
| Extension to AbstractMenu, MultiStepMenu, PaginatedListMenu | `src/Menu/` |
| New core value object | `src/Core/` |
| New exception type | `src/Exceptions/` |
| Example app changes | `example/` |
| Framework documentation | `docs/` |

### Pull request checklist

- [ ] Feature branch is based on a current `main`.
- [ ] All existing tests pass: `php run_tests.php`.
- [ ] New tests cover the added behaviour.
- [ ] If adding a new public API, the README and relevant docs file are updated.
- [ ] If adding a new menu class or pattern to the example app, both `English.php` and `Chichewa.php` are updated with matching translation keys.
- [ ] No new `composer.json` `require` dependencies introduced.

---

## Adding a gateway driver

A gateway driver translates between a USSD provider's HTTP payload and the framework's internal types.

```php
<?php
namespace PhpUssd\Gateway;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;

class MyProviderDriver implements GatewayDriverInterface
{
    public function parse(array $payload): UssdRequest
    {
        // Map provider-specific keys to UssdRequest constructor parameters.
        return new UssdRequest(
            sessionId:   $payload['session_id'] ?? '',
            phoneNumber: $payload['msisdn'] ?? '',
            text:        $payload['input'] ?? '',
            serviceCode: $payload['service_code'] ?? '',
            networkCode: $payload['network'] ?? '',
        );
    }

    public function serialize(UssdResponse $response): string
    {
        // Format the response as the provider expects it.
        $type = $response->isContinue() ? 'CON' : 'END';
        return "{$type} {$response->body()}";
    }

    public function sendHeaders(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
    }
}
```

Add a test case to `run_tests.php` verifying `parse()` and `serialize()` round-trip correctly.

---

## Adding a session driver

A session driver wraps a backing store. Extend `AbstractSessionManager` and implement two methods:

```php
<?php
namespace PhpUssd\Session;

class MySessionManager extends AbstractSessionManager
{
    protected function read(string $sessionId): array
    {
        // Load session data from backing store.
        // Return [] if no session exists.
        $raw = my_store_get($sessionId);
        return $raw !== null ? json_decode($raw, true) : [];
    }

    protected function write(string $sessionId, array $data): void
    {
        // Persist session data to backing store.
        my_store_set($sessionId, json_encode($data), $this->ttl);
    }
}
```

`AbstractSessionManager` provides dot-notation `get()`, `set()`, `forget()`, and `has()` on top of these two methods.

---

## Adding a language

```php
<?php
namespace App\Lang;

use PhpUssd\I18n\AbstractLanguage;

class French extends AbstractLanguage
{
    protected string $code = 'fr';
    protected string $name = 'Français';

    protected array $translations = [
        'welcome'       => 'Bienvenue.',
        'choose_language' => 'Choisissez votre langue',
        'back'          => 'Retour',
        'invalid_input' => 'Entrée invalide. Veuillez réessayer.',
        // ... all keys used by the application
    ];
}
```

Register it in `app.php`:

```php
'languages' => [
    'en' => \App\Lang\English::class,
    'fr' => \App\Lang\French::class,
],
```

Every key used in any menu via `$this->t('key')` must have an entry in every registered language. Missing keys produce `[missing:key]` placeholders.

---

## AI agent guidance

This section is written for AI coding agents (GitHub Copilot, Claude Code, and others) working in this repository. Human developers should also read this to understand how to structure prompts effectively.

### How to describe the framework to an AI

When starting a new conversation about this repository, provide this summary:

> PhpUSSD is a PHP 8.1 USSD framework built around a state machine. Every screen is a class called a "menu" that extends `AbstractMenu`. A menu implements `display()` to render the screen, `handleInput()` to process input and return either a menu ID (transition) or a `UssdResponse` (direct response), and `getParentMenu()` to support back navigation. All menus are registered in `config/app.php`. The framework handles session management (FileSessionManager, RedisSessionManager, ArraySessionManager), gateway parsing (AfricasTalking, Nalo), translations (AbstractLanguage), and HTTP calls (HttpClient). Multi-step flows use the `MultiStepMenu` trait. Paginated lists use `PaginatedListMenu`. Guards implement `MenuGuardInterface`. There are no external Composer dependencies.

### Context to include in a prompt

The most useful files to give an AI agent context:

| File | Why it helps |
|---|---|
| `config/app.php` | Shows the full application map (all menus, drivers, languages) |
| `config/MenuIds.php` | Defines all menu ID constants |
| `src/Menu/AbstractMenu.php` | Core API: every method a menu can call |
| The specific menu file being changed | The current screen's behaviour |
| `docs/framework-philosophy.md` | The design constraints and why |

You usually do not need to include all source files. The menu registry and the file being changed are almost always sufficient.

### Good prompt patterns

**Adding a new menu:**
> Add a new menu called `CropDetailsMenu` to the farmer AI helper example. It should display a crop name and description read from `session->get('selected_crop')`. Show the crop name as the title, the description as body text, and provide back navigation to `MenuIds::CROP_ADVICE`. Use the existing English and Chichewa translation keys or add new ones if needed.

**Fixing navigation:**
> In `LanguageMenu.php`, the `handleInput()` method returns `MenuIds::WORKER_ID` but that constant does not exist. The correct constant is `MenuIds::PHONE`. Fix the reference.

**Adding a translation key:**
> Add the translation key `crop_details_title` to both `English.php` and `Chichewa.php`. English value: "Crop Details". Use an appropriate Chichewa translation.

**Refactoring a multi-step menu:**
> The `RegistrationMenu` currently tracks step state manually using `$this->session->get('reg_step')`. Refactor it to use the `MultiStepMenu` trait. The steps are: `enter_name`, `enter_phone`, `confirm`. Keep the same validation logic for each step. Clear steps after successful confirmation.

**Writing a test:**
> Write a test case for `CropAdviceMenu` in `run_tests.php` using the existing `test()` helper. Cover: (1) `display()` shows the crop list when no crop is selected, (2) `handleInput()` with '1' stores 'maize' in session and returns `MenuIds::CROP_ADVICE`, (3) `display()` with 'selected_crop' = 'maize' in session shows the details and clears the session key, (4) `handleInput()` with invalid input returns an error then `MenuIds::CROP_ADVICE`.

### Patterns the AI should follow

**Return types from `handleInput()`**: Always return either a menu ID string constant (e.g. `\MenuIds::HOME`) or a `UssdResponse` built with `$this->respond()` or `$this->end()`. Never return raw strings that are not menu IDs.

**Translation keys instead of hardcoded text**: All user-visible strings belong in language files. Use `$this->t('key')` in menus, not string literals.

**Error pattern**: Invalid input goes through `errorThen($message, $menuId)`, not inline response building. The error is displayed at the top of the next `display()` call via `consumeError()`.

**Session cleanup**: If a menu stores temporary data in session (e.g. `selected_item`), clear it when it is consumed: call `$this->session->forget('selected_item')` inside `display()` after reading it.

**Multi-step cleanup**: Always call `clearSteps()` after a multi-step flow completes, whether the outcome is success or failure.

**Guard direction**: A guard's `onFail()` redirects to the beginning of the login flow, not to the menu that triggered the guard check. This prevents redirect loops.

### Patterns the AI should avoid

- Do not add `require` or `use` statements that pull in external Composer packages.
- Do not put business logic in `display()`. Keep `display()` deterministic — it reads state and renders, nothing else.
- Do not call `$this->session->save()` inside a menu. The framework calls `save()` once at the end of the request.
- Do not use bare string literals as menu IDs. Always use the constants from `MenuIds`.
- Do not register the same menu class under multiple menu IDs unless there is a documented reason.
- Do not skip adding translation keys to all language files when adding a new menu.

### When to ask for human review

An AI agent working in this repository should request human review before:

- Changing the signature of any method in `AbstractMenu`, `MultiStepMenu`, `PaginatedListMenu`, or `MenuGuardInterface` — these are the extension points that all application code depends on.
- Introducing any Composer dependency, even a dev dependency, to the framework's `composer.json`.
- Changing how session data is keyed (e.g. `_language`, `_current_menu`, `_menu_history`) — these keys have meaning to the framework internals.
- Changing the navigator's back/main-menu/pagination input conventions (`0`, `00`, `98`, `99`).
- Altering any existing test in `run_tests.php` to make it pass rather than fixing the underlying behaviour.

### Autonomous agent workflow

When an AI agent is given an open-ended task (e.g. "add a new feature to the farmer example app"), a reliable workflow is:

1. Read `config/app.php` and `config/MenuIds.php` to understand the current application map.
2. Identify which menus need to be created or modified.
3. Check `example/lang/English.php` for existing translation keys; identify any new keys needed.
4. Write or update menus one file at a time.
5. Add translation keys to all language files simultaneously.
6. Register any new menus in `config/app.php` and `config/MenuIds.php`.
7. Run `php run_tests.php` and fix any failures before reporting completion.

An agent that skips step 7 risks reporting a change as complete when it breaks existing functionality.
