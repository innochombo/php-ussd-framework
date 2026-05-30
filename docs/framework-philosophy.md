# Framework Philosophy

PhpUSSD is a state-machine framework for building USSD applications in PHP. It is small, opinionated, and dependency-free by design.

---

## The mental model

A USSD session is a conversation between a phone and a server. The phone sends a string of inputs separated by `*`, and the server responds with a screen of text. Every response is either **CON** (continue — more input expected) or **END** (end the session).

PhpUSSD models each screen as a **menu**. A menu does two things:

1. **`display()`** — Renders the screen shown to the user.
2. **`handleInput()`** — Processes their next input and returns either a new menu ID (transition) or a direct response.

Navigation is explicit. There are no hidden routes or framework-managed paths. The only way to move from one screen to another is to return a menu ID from `handleInput()`. If you return `MenuIds::CROP_ADVICE`, the user goes to crop advice. If you return a `UssdResponse`, they see that text immediately. Nothing else happens.

This makes the full application flow readable from a single config file — the menu registry — without tracing code.

---

## Core design principles

### State machine first

Every screen is a node. Every input is a transition. The framework enforces this by making menus responsible only for what is on screen and where to go next. Business logic, session management, API calls, and translations all live in separate layers that menus consume, not contain.

The state at any point is: which menu is active + what is in the session. That is the full picture of where a user is in the application.

### No dependencies

The framework requires only PHP 8.1. It ships no Composer dependencies. This is intentional:

- USSD servers are often minimal infrastructure. Dependency chains add surface area and deployment risk.
- A framework with no runtime dependencies can be dropped into any PHP environment without conflict.
- The framework stays focused on its purpose. If your app needs Redis, you configure it directly — you do not get a framework abstraction over an abstraction.

### Separation of concerns

Menus are responsible for one screen at a time. Everything else is injected:

- **Session** — read and written via `SessionManagerInterface`, never directly by menus.
- **Translations** — accessed via `LanguageManager`, never hardcoded in menus.
- **HTTP/API calls** — made via `HttpClient`, which is injected and mockable.
- **Gateway parsing** — done by the driver before the menu ever sees the request.

This separation makes menus testable in isolation. You can exercise a menu with an in-memory session and a fake HTTP client without starting a server.

### Explicit configuration

Every menu that exists in the application is registered in one place: the `menus` array in `app.php`. This makes it possible to read the full application map without searching the codebase. Adding a menu means adding a line to the registry. Removing a menu means removing it from the registry — if it is still referenced from another menu, that reference becomes a visible error.

### Extensibility without modification

The core framework has defined extension points:

- **Gateway drivers** — implement `GatewayDriverInterface` to support any USSD gateway.
- **Session drivers** — implement `SessionManagerInterface` to use any backing store.
- **Language providers** — extend `AbstractLanguage` to add any language.
- **Guards** — implement `MenuGuardInterface` to add any access control rule.

None of these extension points require modifying core files.

---

## What the framework solves

### Stateful navigation

USSD is inherently stateful, but the protocol gives you only a flat input string. The framework reconstructs session state from each request using the session ID provided by the gateway. From a menu's perspective, the session is always available and up to date.

Back navigation (`0`), main menu (`00`), next page (`99`), and previous page (`98`) are handled by the framework before a menu's `handleInput()` is called. Menus do not need to implement these themselves.

### Multi-step forms

Capturing multiple inputs in sequence is the most common USSD pattern. Without abstractions, it requires tracking a step counter in session and branching on it in every menu method. The `MultiStepMenu` trait removes that boilerplate. You declare steps as a list of names, and the trait manages the current position, stores each step's input under a namespaced key, and advances or rewinds on demand.

Step data is namespaced per class, so two multi-step menus used in the same session never overwrite each other's data.

### Paginated lists

USSD screens fit roughly 160–182 characters. A list of items that exceeds one screen requires pagination controls. The `PaginatedListMenu` base class handles fetch, cache, page boundaries, selection routing, and the `98`/`99` navigation inputs. A subclass only implements `fetchItems()`, `itemLabel()`, and `onItemSelected()`.

### Translations

Language selection and localised text are first-class concerns. The `LanguageManager` holds a registry of language providers and an active language code. Menus call `$this->t('key')` without knowing or caring which language is active. Switching language is a single call that persists to session.

Missing translation keys produce `[missing:key]` placeholders in output rather than silent empty strings, making gaps visible immediately during development.

### Batched session writes

Session data is read once at the start of a request and written once at the end. In-memory `set()` calls accumulate through the request lifecycle. This avoids per-write disk or network I/O and is safe for all drivers.

---

## Design values for AI agents

This framework is designed to be readable and navigable by both human developers and AI coding agents.

**Trace any flow from one file.** The menu registry in `app.php` is the map of the application. Every menu ID that appears in `handleInput()` returns must exist in that registry. If an AI agent is asked to understand, extend, or refactor the application, the registry is the correct starting point.

**Change one thing at a time.** Each menu is a self-contained file. Changing one menu does not affect any other menu unless the change alters a menu ID that others reference. An AI agent can be instructed to modify a single menu without risk of unintended side effects elsewhere.

**Errors are explicit.** The framework throws typed exceptions (`MenuNotFoundException`, `SessionException`, `ConfigurationException`) rather than failing silently. An AI agent troubleshooting a broken flow can read the exception type and message to understand what is missing without digging through logs.

**Tests are independent.** The test suite in `run_tests.php` requires no external services. An AI agent can run the tests after any change to verify correctness without setting up infrastructure.

---

## What the framework does not do

- **No ORM or database layer.** Data access is the application's responsibility. Use `HttpClient` to call an API or bring your own database adapter.
- **No templating engine.** USSD text is plain strings. `formatMenu()` is a lightweight helper, not a template system.
- **No event system.** Lifecycle hooks (`onEnter`, `onLeave`) are optional menu methods, not a general-purpose pub/sub mechanism.
- **No automatic routing.** There is no URL-based routing, no annotation scanning, no convention-based menu discovery. Registration is manual and explicit.

These are not missing features — they are deliberate omissions that keep the framework focused and the application understandable.
