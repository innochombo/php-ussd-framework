<?php

declare(strict_types=1);

/**
 * Minimal test runner — no PHPUnit needed.
 * Run: php run_tests.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// ── Inline test language ───────────────────────────────────────────────────
use PhpUssd\I18n\AbstractLanguage;

class TestEnglish extends AbstractLanguage {
    protected string $code = 'en';
    protected string $name = 'English';
    protected array $translations = ['welcome' => 'Welcome', 'back' => 'Back', 'main_menu' => 'Main Menu'];
}

// ── Test runner ────────────────────────────────────────────────────────────

$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, callable $fn): void {
    global $passed, $failed, $errors;
    try {
        $fn();
        echo "\033[32m  ✓ {$name}\033[0m\n";
        $passed++;
    } catch (Throwable $e) {
        echo "\033[31m  ✗ {$name}\033[0m\n";
        echo "    → " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = $name;
    }
}

function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        throw new \RuntimeException($msg ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

function assert_true(mixed $value, string $msg = ''): void {
    if (!$value) throw new \RuntimeException($msg ?: "Expected true, got false");
}

function assert_false(mixed $value, string $msg = ''): void {
    if ($value) throw new \RuntimeException($msg ?: "Expected false, got true");
}

function assert_null(mixed $value, string $msg = ''): void {
    if ($value !== null) throw new \RuntimeException($msg ?: "Expected null, got " . var_export($value, true));
}

function assert_contains(string $needle, string $haystack, string $msg = ''): void {
    if (!str_contains($haystack, $needle)) {
        throw new \RuntimeException($msg ?: "Expected '{$haystack}' to contain '{$needle}'");
    }
}

function assert_throws(string $class, callable $fn): void {
    try {
        $fn();
        throw new \RuntimeException("Expected exception {$class} was not thrown");
    } catch (\Throwable $e) {
        if (!($e instanceof $class)) {
            throw new \RuntimeException("Expected {$class}, got " . get_class($e) . ": " . $e->getMessage());
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mUssdRequest\033[0m\n";

use PhpUssd\Core\UssdRequest;

test('initial request detected when text is empty', function () {
    $req = new UssdRequest('sess1', '+265880000001', '');
    assert_true($req->isInitial());
    assert_equals('', $req->lastInput);
});

test('lastInput is last star-delimited segment', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1*2*3');
    assert_equals('3', $req->lastInput);
    assert_equals('1*2*3', $req->text);
});

test('inputSegments splits on star', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1*2*3');
    assert_equals(['1', '2', '3'], $req->inputSegments());
});

test('isBack detects "0"', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1*0');
    assert_true($req->isBack());
    assert_false($req->isMainMenu());
});

test('isMainMenu detects "00"', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1*2*00');
    assert_true($req->isMainMenu());
    assert_false($req->isBack());
});

test('isNextPage detects "99" as lastInput', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1*99');
    assert_true($req->isNextPage());
    assert_false($req->isPrevPage());
});

test('isPrevPage detects "98" as lastInput', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1*98');
    assert_true($req->isPrevPage());
    assert_false($req->isNextPage());
});

test('single segment text has correct lastInput', function () {
    $req = new UssdRequest('sess1', '+265880000001', '1');
    assert_equals('1', $req->lastInput);
    assert_equals(['1'], $req->inputSegments());
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mUssdResponse\033[0m\n";

use PhpUssd\Core\UssdResponse;

test('CON response serialises correctly', function () {
    $r = UssdResponse::con("Choose option\n1. A\n2. B");
    assert_true($r->isContinue());
    assert_false($r->isEnd());
    assert_equals("CON Choose option\n1. A\n2. B", $r->toString());
});

test('END response serialises correctly', function () {
    $r = UssdResponse::end('Goodbye');
    assert_true($r->isEnd());
    assert_false($r->isContinue());
    assert_equals('END Goodbye', (string)$r);
});

test('CON and END are different types', function () {
    $con = UssdResponse::con('x');
    $end = UssdResponse::end('x');
    assert_true($con->isContinue());
    assert_true($end->isEnd());
    assert_false($con->isEnd());
    assert_false($end->isContinue());
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mConfig\033[0m\n";

use PhpUssd\Core\Config;

test('dot notation access to nested keys', function () {
    $c = new Config(['session' => ['driver' => 'FileDriver', 'ttl' => 300]]);
    assert_equals('FileDriver', $c->get('session.driver'));
    assert_equals(300, $c->get('session.ttl'));
});

test('top-level key access', function () {
    $c = new Config(['gateway' => 'ATDriver']);
    assert_equals('ATDriver', $c->get('gateway'));
});

test('missing key returns null by default', function () {
    $c = new Config([]);
    assert_null($c->get('missing'));
});

test('missing key returns provided default', function () {
    $c = new Config([]);
    assert_equals('fallback', $c->get('missing', 'fallback'));
});

test('has() returns true for present key', function () {
    $c = new Config(['foo' => 'bar']);
    assert_true($c->has('foo'));
    assert_false($c->has('baz'));
});

test('require() throws for missing key', function () {
    $c = new Config([]);
    assert_throws(\PhpUssd\Exceptions\ConfigurationException::class, fn() => $c->require('missing'));
});

test('require() returns value when key present', function () {
    $c = new Config(['key' => 'value']);
    assert_equals('value', $c->require('key'));
});

test('deeply nested dot notation', function () {
    $c = new Config(['a' => ['b' => ['c' => 'deep']]]);
    assert_equals('deep', $c->get('a.b.c'));
    assert_null($c->get('a.b.d'));
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mArraySessionManager\033[0m\n";

use PhpUssd\Session\ArraySessionManager;

test('set and get basic value', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('name', 'Alice');
    assert_equals('Alice', $s->get('name'));
});

test('dot notation nested set/get', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('user.name', 'Bob');
    $s->set('user.token', 'tok123');
    assert_equals('Bob',    $s->get('user.name'));
    assert_equals('tok123', $s->get('user.token'));
});

test('forget removes key', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('key', 'value');
    $s->forget('key');
    assert_null($s->get('key'));
});

test('data persists after save and reload', function () {
    ArraySessionManager::flush();
    $s1 = new ArraySessionManager();
    $s1->load('persist_sess');
    $s1->set('worker_id', 'W001');
    $s1->save();

    $s2 = new ArraySessionManager();
    $s2->load('persist_sess');
    assert_equals('W001', $s2->get('worker_id'));
});

test('default value returned for missing key', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    assert_equals('fallback', $s->get('nonexistent', 'fallback'));
});

test('has() detects existing and missing keys', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('present', true);
    assert_true($s->has('present'));
    assert_false($s->has('absent'));
});

test('destroy clears session data', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('key', 'value');
    $s->destroy();
    assert_null($s->get('key'));
});

test('all() returns full data array', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('a', 1);
    $s->set('b', 2);
    $all = $s->all();
    assert_equals(1, $all['a']);
    assert_equals(2, $all['b']);
});

test('forget nested key', function () {
    ArraySessionManager::flush();
    $s = new ArraySessionManager();
    $s->load('sess1');
    $s->set('user.name', 'Alice');
    $s->set('user.token', 'abc');
    $s->forget('user.token');
    assert_equals('Alice', $s->get('user.name'));
    assert_null($s->get('user.token'));
});

test('different sessions do not share data', function () {
    ArraySessionManager::flush();
    $s1 = new ArraySessionManager();
    $s1->load('session_a');
    $s1->set('key', 'value_a');
    $s1->save();

    $s2 = new ArraySessionManager();
    $s2->load('session_b');
    assert_null($s2->get('key'));
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mFileSessionManager\033[0m\n";

use PhpUssd\Session\FileSessionManager;

test('writes and reads session from disk', function () {
    $dir = sys_get_temp_dir() . '/phpussd_test_' . uniqid();
    $s = new FileSessionManager($dir, 300);
    $s->load('file_sess_1');
    $s->set('token', 'abc123');
    $s->save();

    $s2 = new FileSessionManager($dir, 300);
    $s2->load('file_sess_1');
    assert_equals('abc123', $s2->get('token'));
    // Cleanup
    array_map('unlink', glob($dir . '/*.json'));
    rmdir($dir);
});

test('destroy removes session file', function () {
    $dir = sys_get_temp_dir() . '/phpussd_test_' . uniqid();
    $s = new FileSessionManager($dir, 300);
    $s->load('file_sess_2');
    $s->set('x', 'y');
    $s->save();
    $s->destroy();

    $s2 = new FileSessionManager($dir, 300);
    $s2->load('file_sess_2');
    assert_null($s2->get('x'));
    rmdir($dir);
});

test('expired session returns empty data', function () {
    $dir = sys_get_temp_dir() . '/phpussd_test_' . uniqid();
    // TTL of 0 = immediately expired
    $s = new FileSessionManager($dir, 0);
    $s->load('expire_sess');
    $s->set('k', 'v');
    $s->save();

    // Touch the file to an old mtime
    $files = glob($dir . '/*.json');
    if ($files) touch($files[0], time() - 10);

    $s2 = new FileSessionManager($dir, 1);
    $s2->load('expire_sess');
    assert_null($s2->get('k'));
    array_map('unlink', glob($dir . '/*.json'));
    rmdir($dir);
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mLanguageManager\033[0m\n";

use PhpUssd\I18n\LanguageManager;

test('translates key in active language', function () {
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    assert_equals('Welcome', $lang->get('welcome'));
});

test('missing key returns placeholder', function () {
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    $result = $lang->get('totally_missing_key');
    assert_contains('missing', $result);
});

test('availableLanguages returns code => name map', function () {
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    $avail = $lang->availableLanguages();
    assert_true(array_key_exists('en', $avail));
    assert_equals('English', $avail['en']);
});

test('setActive switches language', function () {
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    $lang->setActive('en'); // Should not throw
    assert_equals('en', $lang->activeCode());
});

test('setActive throws for unregistered code', function () {
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    assert_throws(\InvalidArgumentException::class, fn() => $lang->setActive('zz'));
});

test('format() applies sprintf', function () {
    class FmtLang extends AbstractLanguage {
        protected string $code = 'en';
        protected string $name = 'English';
        protected array $translations = ['hello' => 'Hello %s!'];
    }
    $lang = new LanguageManager(['en' => FmtLang::class], 'en');
    assert_equals('Hello World!', $lang->format('hello', 'World'));
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mHttpResponse\033[0m\n";

use PhpUssd\Http\HttpResponse;

test('ok() for 200 status', function () {
    $r = HttpResponse::fromRaw(200, '{"id":1}');
    assert_true($r->ok());
    assert_false($r->failed());
});

test('failed() for 4xx', function () {
    $r = HttpResponse::fromRaw(404, '{}');
    assert_true($r->failed());
});

test('failed() for 5xx', function () {
    $r = HttpResponse::fromRaw(500, '{}');
    assert_true($r->failed());
});

test('dot notation get on JSON data', function () {
    $r = HttpResponse::fromRaw(200, '{"tokenData":{"token":"abc123"}}');
    assert_equals('abc123', $r->get('tokenData.token'));
    assert_null($r->get('missing.key'));
});

test('get with default for missing key', function () {
    $r = HttpResponse::fromRaw(200, '{"a":1}');
    assert_equals('default', $r->get('missing', 'default'));
});

test('hasErrors detects errors key', function () {
    $r = HttpResponse::fromRaw(422, '{"errors":"bad input"}');
    assert_true($r->hasErrors());
});

test('hasErrors false when no errors key', function () {
    $r = HttpResponse::fromRaw(200, '{"data":"ok"}');
    assert_false($r->hasErrors());
});

test('error() factory creates failed response', function () {
    $r = HttpResponse::error('Connection refused');
    assert_true($r->failed());
    assert_equals(0, $r->status);
    assert_equals('Connection refused', $r->error);
});

test('non-JSON body gives null data', function () {
    $r = HttpResponse::fromRaw(200, 'plain text response');
    assert_null($r->data);
    assert_equals('plain text response', $r->raw);
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mMenuRouter\033[0m\n";

use PhpUssd\Menu\MenuRouter;
use PhpUssd\Menu\AbstractMenu;
use PhpUssd\Core\UssdResponse as Resp;

// Minimal concrete menu for testing
class TestMenu extends AbstractMenu {
    public function display(): Resp { return $this->respond('Test Menu'); }
    public function handleInput(): string|Resp { return 'SOME_MENU'; }
    public function getParentMenu(): ?string { return null; }
}

class TestMenu2 extends AbstractMenu {
    public function display(): Resp { return $this->respond('Menu 2'); }
    public function handleInput(): string|Resp { return 'MENU_1'; }
    public function getParentMenu(): ?string { return 'MENU_1'; }
}

test('register and has() works', function () {
    $router = new MenuRouter(['MENU_1' => TestMenu::class]);
    assert_true($router->has('MENU_1'));
    assert_false($router->has('MENU_MISSING'));
});

test('registeredIds returns all keys', function () {
    $router = new MenuRouter(['MENU_1' => TestMenu::class, 'MENU_2' => TestMenu2::class]);
    $ids = $router->registeredIds();
    assert_true(in_array('MENU_1', $ids));
    assert_true(in_array('MENU_2', $ids));
    assert_equals(2, count($ids));
});

test('resolve returns correct menu instance', function () {
    ArraySessionManager::flush();
    $router  = new MenuRouter(['MENU_1' => TestMenu::class]);
    $request = new UssdRequest('s1', '+265', '1');
    $session = new ArraySessionManager();
    $session->load('s1');
    $lang    = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http    = new \PhpUssd\Http\HttpClient();

    $menu = $router->resolve('MENU_1', $request, $session, $lang, $http);
    assert_true($menu instanceof TestMenu);
});

test('resolve caches instance within same request', function () {
    ArraySessionManager::flush();
    $router  = new MenuRouter(['MENU_1' => TestMenu::class]);
    $request = new UssdRequest('s1', '+265', '1');
    $session = new ArraySessionManager();
    $session->load('s1');
    $lang    = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http    = new \PhpUssd\Http\HttpClient();

    $menu1 = $router->resolve('MENU_1', $request, $session, $lang, $http);
    $menu2 = $router->resolve('MENU_1', $request, $session, $lang, $http);
    assert_true($menu1 === $menu2); // Same instance
});

test('resolve throws for unregistered ID', function () {
    $router  = new MenuRouter([]);
    $request = new UssdRequest('s1', '+265', '1');
    $session = new ArraySessionManager();
    $session->load('s1');
    $lang    = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http    = new \PhpUssd\Http\HttpClient();

    assert_throws(
        \PhpUssd\Exceptions\MenuNotFoundException::class,
        fn() => $router->resolve('MISSING', $request, $session, $lang, $http)
    );
});

test('register throws for non-AbstractMenu class', function () {
    assert_throws(\InvalidArgumentException::class, function () {
        new MenuRouter(['MENU_1' => \stdClass::class]);
    });
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mMultiStepMenu trait\033[0m\n";

use PhpUssd\Menu\MultiStepMenu;

class PinChangeMenuTest extends AbstractMenu {
    use MultiStepMenu;

    protected function steps(): array {
        return ['enter_old', 'enter_new', 'confirm'];
    }

    public function display(): Resp {
        return $this->respond('step: ' . $this->currentStep());
    }

    public function handleInput(): string|Resp {
        $this->captureAndAdvance();
        return 'SELF';
    }

    public function getParentMenu(): ?string { return null; }

    // Expose internals for testing
    public function exposeCurrentStep(): string { return $this->currentStep(); }
    public function exposeIsFirst(): bool { return $this->isFirstStep(); }
    public function exposeIsLast(): bool { return $this->isLastStep(); }
    public function exposeGetValue(string $step): mixed { return $this->getStepValue($step); }
    public function exposeAdvance(): void { $this->advanceStep(); }
    public function exposeRewind(): void { $this->rewindStep(); }
    public function exposeClear(): void { $this->clearSteps(); }
}

function makeMultiStepMenu(string $lastInput = ''): PinChangeMenuTest {
    ArraySessionManager::flush();
    $menu    = new PinChangeMenuTest();
    $request = new UssdRequest('s1', '+265', $lastInput);
    $session = new ArraySessionManager();
    $session->load('s1');
    $lang    = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http    = new \PhpUssd\Http\HttpClient();
    $menu->boot($request, $session, $lang, $http);
    return $menu;
}

test('starts on first step', function () {
    $menu = makeMultiStepMenu();
    assert_equals('enter_old', $menu->exposeCurrentStep());
    assert_true($menu->exposeIsFirst());
    assert_false($menu->exposeIsLast());
});

test('advance moves to next step', function () {
    $menu = makeMultiStepMenu();
    $menu->exposeAdvance();
    assert_equals('enter_new', $menu->exposeCurrentStep());
    assert_false($menu->exposeIsFirst());
    assert_false($menu->exposeIsLast());
});

test('advance to last step', function () {
    $menu = makeMultiStepMenu();
    $menu->exposeAdvance();
    $menu->exposeAdvance();
    assert_equals('confirm', $menu->exposeCurrentStep());
    assert_true($menu->exposeIsLast());
});

test('advance does not go past last step', function () {
    $menu = makeMultiStepMenu();
    $menu->exposeAdvance();
    $menu->exposeAdvance();
    $menu->exposeAdvance(); // Already on last, should stay
    assert_equals('confirm', $menu->exposeCurrentStep());
});

test('rewind goes back one step', function () {
    $menu = makeMultiStepMenu();
    $menu->exposeAdvance();
    $menu->exposeAdvance();
    assert_equals('confirm', $menu->exposeCurrentStep());
    $menu->exposeRewind();
    assert_equals('enter_new', $menu->exposeCurrentStep());
});

test('captureAndAdvance stores value and advances', function () {
    $menu = makeMultiStepMenu('1234');
    $menu->handleInput(); // captures '1234' for 'enter_old', advances to 'enter_new'
    assert_equals('enter_new', $menu->exposeCurrentStep());
    assert_equals('1234', $menu->exposeGetValue('enter_old'));
});

test('clearSteps resets to first step', function () {
    $menu = makeMultiStepMenu();
    $menu->exposeAdvance();
    $menu->exposeAdvance();
    $menu->exposeClear();
    assert_equals('enter_old', $menu->exposeCurrentStep());
});

test('step namespaces prevent collision between two multi-step menus', function () {
    ArraySessionManager::flush();
    $session = new ArraySessionManager();
    $session->load('shared_sess');
    $lang    = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http    = new \PhpUssd\Http\HttpClient();

    // Menu A
    class MenuA extends AbstractMenu {
        use MultiStepMenu;
        protected function steps(): array { return ['a1', 'a2']; }
        public function display(): Resp { return $this->respond('a'); }
        public function handleInput(): string|Resp { return 'A'; }
        public function getParentMenu(): ?string { return null; }
        public function step(): string { return $this->currentStep(); }
        public function adv(): void { $this->advanceStep(); }
    }

    // Menu B
    class MenuB extends AbstractMenu {
        use MultiStepMenu;
        protected function steps(): array { return ['b1', 'b2']; }
        public function display(): Resp { return $this->respond('b'); }
        public function handleInput(): string|Resp { return 'B'; }
        public function getParentMenu(): ?string { return null; }
        public function step(): string { return $this->currentStep(); }
        public function adv(): void { $this->advanceStep(); }
    }

    $menuA = new MenuA(); $menuA->boot(new UssdRequest('shared_sess', '+265', ''), $session, $lang, $http);
    $menuB = new MenuB(); $menuB->boot(new UssdRequest('shared_sess', '+265', ''), $session, $lang, $http);

    $menuA->adv(); // A is on a2
    // B should still be on b1
    assert_equals('a2', $menuA->step());
    assert_equals('b1', $menuB->step());
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mMenuNavigator\033[0m\n";

use PhpUssd\Menu\MenuNavigator;

// Menus for navigation testing
class NavHomeMenu extends AbstractMenu {
    public function display(): Resp { return $this->respond("Home\n1. Go to A"); }
    public function handleInput(): string|Resp {
        return $this->lastInput === '1' ? 'NAV_MENU_A' : $this->errorThen('bad', 'NAV_MENU_HOME');
    }
    public function getParentMenu(): ?string { return null; }
}

class NavMenuA extends AbstractMenu {
    public function display(): Resp { return $this->respond("Menu A\n1. End\n0. Back"); }
    public function handleInput(): string|Resp {
        if ($this->lastInput === '1') return $this->end('Done!');
        return $this->getParentMenu();
    }
    public function getParentMenu(): ?string { return 'NAV_MENU_HOME'; }
}

function makeNavigator(string $input, string $sessionId = 'nav_test'): array {
    ArraySessionManager::flush();
    $session = new ArraySessionManager();
    $session->load($sessionId);
    $lang    = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http    = new \PhpUssd\Http\HttpClient();
    $router  = new MenuRouter(['NAV_MENU_HOME' => NavHomeMenu::class, 'NAV_MENU_A' => NavMenuA::class]);
    $request = new UssdRequest($sessionId, '+265', $input);

    $nav = new MenuNavigator($router, $session, $lang, $http, 'NAV_MENU_HOME', 'NAV_MENU_HOME');
    $response = $nav->handle($request);
    return [$response, $session];
}

test('initial request displays default menu', function () {
    [$response] = makeNavigator('');
    assert_true($response->isContinue());
    assert_contains('Home', $response->body);
});

test('input "1" transitions to Menu A', function () {
    ArraySessionManager::flush();
    $session = new ArraySessionManager();
    $session->load('nav2');
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http = new \PhpUssd\Http\HttpClient();

    // Request 1: initial — each request gets a fresh router (as Application does)
    $router1 = new MenuRouter(['NAV_MENU_HOME' => NavHomeMenu::class, 'NAV_MENU_A' => NavMenuA::class]);
    $nav1    = new MenuNavigator($router1, $session, $lang, $http, 'NAV_MENU_HOME', 'NAV_MENU_HOME');
    $nav1->handle(new UssdRequest('nav2', '+265', ''));
    $session->save();

    // Request 2: user sends "1" — new router instance (simulates new HTTP request)
    $router2  = new MenuRouter(['NAV_MENU_HOME' => NavHomeMenu::class, 'NAV_MENU_A' => NavMenuA::class]);
    $nav2     = new MenuNavigator($router2, $session, $lang, $http, 'NAV_MENU_HOME', 'NAV_MENU_HOME');
    $response = $nav2->handle(new UssdRequest('nav2', '+265', '1'));

    assert_true($response->isContinue());
    assert_contains('Menu A', $response->body);
});

test('"00" main menu resets history and shows main menu', function () {
    [$response] = makeNavigator('00');
    assert_true($response->isContinue());
    assert_contains('Home', $response->body);
});

test('END response from menu is returned as-is', function () {
    // Put session in Menu A state first
    ArraySessionManager::flush();
    $session = new ArraySessionManager();
    $session->load('nav3');
    $session->set('_current_menu', 'NAV_MENU_A');
    $lang  = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http  = new \PhpUssd\Http\HttpClient();
    $router = new MenuRouter(['NAV_MENU_HOME' => NavHomeMenu::class, 'NAV_MENU_A' => NavMenuA::class]);

    $req = new UssdRequest('nav3', '+265', '1*1');
    $nav = new MenuNavigator($router, $session, $lang, $http, 'NAV_MENU_HOME', 'NAV_MENU_HOME');
    $response = $nav->handle($req);

    assert_true($response->isEnd());
    assert_contains('Done', $response->body);
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mGateway drivers\033[0m\n";

use PhpUssd\Gateway\AfricasTalkingDriver;
use PhpUssd\Gateway\NaloDriver;

test('AfricasTalkingDriver parses standard payload', function () {
    $driver  = new AfricasTalkingDriver();
    $request = $driver->parse([
        'sessionId'   => 'ATsess1',
        'phoneNumber' => '+265880000001',
        'serviceCode' => '*123#',
        'text'        => '1*2',
        'networkCode' => 'TNM',
    ]);
    assert_equals('ATsess1', $request->sessionId);
    assert_equals('+265880000001', $request->phoneNumber);
    assert_equals('1*2', $request->text);
    assert_equals('2', $request->lastInput);
    assert_equals('*123#', $request->serviceCode);
});

test('AfricasTalkingDriver throws for missing sessionId', function () {
    $driver = new AfricasTalkingDriver();
    assert_throws(\PhpUssd\Exceptions\UssdException::class, function () use ($driver) {
        $driver->parse(['phoneNumber' => '+265880000001', 'text' => '']);
    });
});

test('AfricasTalkingDriver serialises CON response', function () {
    $driver   = new AfricasTalkingDriver();
    $response = UssdResponse::con("Choose:\n1. Option A");
    assert_equals("CON Choose:\n1. Option A", $driver->serialize($response));
});

test('NaloDriver parses Nalo payload', function () {
    $driver  = new NaloDriver();
    $request = $driver->parse([
        'sessionid' => 'Nalosess1',
        'msisdn'    => '+265880000002',
        'userdata'  => '2',
        'network'   => 'Airtel',
    ]);
    assert_equals('Nalosess1', $request->sessionId);
    assert_equals('+265880000002', $request->phoneNumber);
    assert_equals('2', $request->lastInput);
});

test('NaloDriver throws for missing msisdn', function () {
    $driver = new NaloDriver();
    assert_throws(\PhpUssd\Exceptions\UssdException::class, function () use ($driver) {
        $driver->parse(['sessionid' => 'sess1', 'userdata' => '']);
    });
});


// ═══════════════════════════════════════════════════════════════════════════
echo "\n\033[1mAbstractMenu helpers\033[0m\n";

class HelperMenu extends AbstractMenu {
    public function display(): Resp { return $this->respond('test'); }
    public function handleInput(): string|Resp { return 'MENU'; }
    public function getParentMenu(): ?string { return 'PARENT'; }

    // Expose protected helpers for testing
    public function callFormatMenu(string $title, array $opts): Resp { return $this->formatMenu($title, $opts); }
    public function callEnd(string $body): Resp { return $this->end($body); }
    public function callT(string $key): string { return $this->t($key); }
    public function callErrorThen(string $msg, string $id): string { return $this->errorThen($msg, $id); }
    public function callConsumeError(): ?string { return $this->consumeError(); }
}

function makeHelperMenu(string $input = ''): HelperMenu {
    ArraySessionManager::flush();
    $menu = new HelperMenu();
    $req  = new UssdRequest('h1', '+265', $input);
    $sess = new ArraySessionManager();
    $sess->load('h1');
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http = new \PhpUssd\Http\HttpClient();
    $menu->boot($req, $sess, $lang, $http);
    return $menu;
}

test('formatMenu builds numbered option screen', function () {
    $menu = makeHelperMenu();
    $r = $menu->callFormatMenu('Pick one', ['1' => 'Apple', '2' => 'Banana', '0' => 'Back']);
    assert_true($r->isContinue());
    assert_contains('Pick one', $r->body);
    assert_contains('1. Apple', $r->body);
    assert_contains('2. Banana', $r->body);
    assert_contains('0. Back', $r->body);
});

test('end() creates END response', function () {
    $menu = makeHelperMenu();
    $r = $menu->callEnd('Goodbye');
    assert_true($r->isEnd());
    assert_contains('Goodbye', $r->body);
});

test('t() translates key', function () {
    $menu = makeHelperMenu();
    assert_equals('Welcome', $menu->callT('welcome'));
    assert_equals('Back', $menu->callT('back'));
});

test('errorThen stores message and returns menu id', function () {
    $menu = makeHelperMenu();
    $result = $menu->callErrorThen('Bad input!', 'TARGET_MENU');
    assert_equals('TARGET_MENU', $result);

    // consumeError should retrieve and clear it
    $error = $menu->callConsumeError();
    assert_equals('Bad input!', $error);

    // Second consume should be null
    assert_null($menu->callConsumeError());
});

test('errors are namespaced per menu class', function () {
    ArraySessionManager::flush();
    $sess = new ArraySessionManager();
    $sess->load('shared');
    $lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    $http = new \PhpUssd\Http\HttpClient();

    class MenuX extends AbstractMenu {
        public function display(): Resp { return $this->respond('x'); }
        public function handleInput(): string|Resp { return 'X'; }
        public function getParentMenu(): ?string { return null; }
        public function setErr(string $m): void { $this->errorThen($m, 'X'); }
        public function getErr(): ?string { return $this->consumeError(); }
    }
    class MenuY extends AbstractMenu {
        public function display(): Resp { return $this->respond('y'); }
        public function handleInput(): string|Resp { return 'Y'; }
        public function getParentMenu(): ?string { return null; }
        public function getErr(): ?string { return $this->consumeError(); }
    }

    $mx = new MenuX(); $mx->boot(new UssdRequest('shared', '+265', ''), $sess, $lang, $http);
    $my = new MenuY(); $my->boot(new UssdRequest('shared', '+265', ''), $sess, $lang, $http);

    $mx->setErr('Error for X');
    // Y should not see X's error
    assert_null($my->getErr());
    assert_equals('Error for X', $mx->getErr());
});


// ═══════════════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════════════

echo "\n" . str_repeat('─', 50) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[32m  All {$total} tests passed.\033[0m\n\n";
} else {
    echo "\033[31m  {$failed} of {$total} tests failed:\033[0m\n";
    foreach ($errors as $e) {
        echo "    • {$e}\n";
    }
    echo "\n";
}
exit($failed > 0 ? 1 : 0);
