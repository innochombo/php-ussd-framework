# PhpUSSD Documentation

Complete documentation for the PhpUSSD framework — a minimal, state-machine-based USSD framework for PHP 8.1+.

---

## Documents

### [framework-philosophy.md](framework-philosophy.md)

The mental model, design principles, and reasoning behind every architectural decision in the framework. Start here to understand *why* the framework works the way it does before writing code.

Topics: state machine model · no-dependency stance · separation of concerns · session batching · what the framework intentionally does not do · AI-agent design values.

---

### [guidelines-usage.md](guidelines-usage.md)

Practical developer guide covering everything needed to build a production USSD application from scratch.

Topics: installation · project layout · entry point · config reference · `AbstractMenu` API · `MultiStepMenu` trait · `PaginatedListMenu` · guards · session drivers · translations · HTTP client · gateway drivers · navigation reference · common patterns · testing.

---

### [contributing-and-prompts.md](contributing-and-prompts.md)

Contribution guide for both human developers and AI coding agents. Covers code conventions, extension point recipes, and structured guidance for AI-assisted development.

Topics: PR checklist · adding a gateway driver · adding a session driver · adding a language · AI context summary · good prompt patterns · anti-patterns to avoid · autonomous agent workflow.

---

## Example application

The [example/](../example/) directory contains a working farmer AI helper USSD application built on PhpUSSD. It demonstrates:

| Pattern | Where |
|---|---|
| Language selection and session persistence | [example/menus/LanguageMenu.php](../example/menus/LanguageMenu.php) |
| Form input with validation | [example/menus/WorkerIdMenu.php](../example/menus/WorkerIdMenu.php) |
| Simple branching menu | [example/menus/OverviewMenus.php](../example/menus/OverviewMenus.php) |
| Session-based detail view | [example/menus/CropAdviceMenu.php](../example/menus/CropAdviceMenu.php) |
| Inline response on selection | [example/menus/WeatherTipsMenu.php](../example/menus/WeatherTipsMenu.php) |
| Static information screen with nav | [example/menus/MarketPricesMenu.php](../example/menus/MarketPricesMenu.php) |
| Multi-step flow (MultiStepMenu) | [example/menus/AiHelperMenu.php](../example/menus/AiHelperMenu.php) |
| Bilingual translations | [example/lang/English.php](../example/lang/English.php), [example/lang/Chichewa.php](../example/lang/Chichewa.php) |
| Application config and menu registry | [example/config/app.php](../example/config/app.php) |

---

## How to use these docs

1. **New to PhpUSSD** — Read `framework-philosophy.md` first, then `guidelines-usage.md`.
2. **Building a new app** — Use `guidelines-usage.md` as a reference. Browse the example app for concrete patterns.
3. **Contributing or extending the framework** — Read `contributing-and-prompts.md` for conventions and PR requirements.
4. **Working with an AI agent** — Share `contributing-and-prompts.md` and the relevant context files listed in the "Context to include" section.
