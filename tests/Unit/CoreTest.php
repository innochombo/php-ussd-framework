<?php

declare(strict_types=1);

namespace PhpUssd\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PhpUssd\Core\UssdRequest;
use PhpUssd\Core\UssdResponse;
use PhpUssd\Core\Config;
use PhpUssd\Session\ArraySessionManager;
use PhpUssd\I18n\LanguageManager;
use PhpUssd\I18n\AbstractLanguage;
use PhpUssd\Http\HttpResponse;

// ── Inline test language ───────────────────────────────────────────────────

class TestEnglish extends AbstractLanguage
{
    protected string $code = 'en';
    protected string $name = 'English';
    protected array $translations = [
        'welcome' => 'Welcome',
        'back'    => 'Back',
    ];
}


// ── UssdRequest ────────────────────────────────────────────────────────────

class UssdRequestTest extends TestCase
{
    public function test_initial_request_detected(): void
    {
        $req = new UssdRequest('sess1', '+265880000001', '');
        $this->assertTrue($req->isInitial());
        $this->assertSame('', $req->lastInput);
    }

    public function test_last_input_extracted_from_star_delimited_text(): void
    {
        $req = new UssdRequest('sess1', '+265880000001', '1*2*3');
        $this->assertSame('3', $req->lastInput);
        $this->assertSame(['1', '2', '3'], $req->inputSegments());
    }

    public function test_back_detected(): void
    {
        $req = new UssdRequest('sess1', '+265880000001', '1*0');
        $this->assertTrue($req->isBack());
    }

    public function test_main_menu_detected(): void
    {
        $req = new UssdRequest('sess1', '+265880000001', '1*2*00');
        $this->assertTrue($req->isMainMenu());
    }
}


// ── UssdResponse ───────────────────────────────────────────────────────────

class UssdResponseTest extends TestCase
{
    public function test_con_response(): void
    {
        $r = UssdResponse::con('Choose option');
        $this->assertTrue($r->isContinue());
        $this->assertFalse($r->isEnd());
        $this->assertSame('CON Choose option', $r->toString());
    }

    public function test_end_response(): void
    {
        $r = UssdResponse::end('Goodbye');
        $this->assertTrue($r->isEnd());
        $this->assertSame('END Goodbye', (string)$r);
    }
}


// ── Config ────────────────────────────────────────────────────────────────

class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config([
            'gateway' => 'SomeDriver',
            'session' => ['driver' => 'FileDriver', 'ttl' => 300],
            'api'     => ['base_url' => 'http://localhost', 'timeout' => 30],
        ]);
    }

    public function test_dot_notation_access(): void
    {
        $this->assertSame('FileDriver', $this->config->get('session.driver'));
        $this->assertSame(300,          $this->config->get('session.ttl'));
        $this->assertSame('http://localhost', $this->config->get('api.base_url'));
    }

    public function test_default_returned_for_missing_key(): void
    {
        $this->assertSame('default', $this->config->get('missing.key', 'default'));
        $this->assertNull($this->config->get('missing.key'));
    }

    public function test_require_throws_for_missing_key(): void
    {
        $this->expectException(\PhpUssd\Exceptions\ConfigurationException::class);
        $this->config->require('does_not_exist');
    }
}


// ── ArraySessionManager ────────────────────────────────────────────────────

class ArraySessionManagerTest extends TestCase
{
    private ArraySessionManager $session;

    protected function setUp(): void
    {
        ArraySessionManager::flush();
        $this->session = new ArraySessionManager();
        $this->session->load('test_session');
    }

    public function test_set_and_get(): void
    {
        $this->session->set('name', 'Alice');
        $this->assertSame('Alice', $this->session->get('name'));
    }

    public function test_dot_notation_nested(): void
    {
        $this->session->set('user.name', 'Bob');
        $this->session->set('user.token', 'tok123');
        $this->assertSame('Bob',    $this->session->get('user.name'));
        $this->assertSame('tok123', $this->session->get('user.token'));
    }

    public function test_forget(): void
    {
        $this->session->set('key', 'value');
        $this->session->forget('key');
        $this->assertNull($this->session->get('key'));
    }

    public function test_data_persists_across_load_calls(): void
    {
        $this->session->set('worker_id', 'W001');
        $this->session->save();

        $session2 = new ArraySessionManager();
        $session2->load('test_session');
        $this->assertSame('W001', $session2->get('worker_id'));
    }

    public function test_default_value(): void
    {
        $this->assertSame('fallback', $this->session->get('nonexistent', 'fallback'));
    }

    public function test_destroy_clears_data(): void
    {
        $this->session->set('key', 'value');
        $this->session->destroy();
        $this->assertNull($this->session->get('key'));
    }
}


// ── LanguageManager ────────────────────────────────────────────────────────

class LanguageManagerTest extends TestCase
{
    private LanguageManager $lang;

    protected function setUp(): void
    {
        $this->lang = new LanguageManager(['en' => TestEnglish::class], 'en');
    }

    public function test_translate_key(): void
    {
        $this->assertSame('Welcome', $this->lang->get('welcome'));
    }

    public function test_missing_key_returns_placeholder(): void
    {
        $this->assertStringContainsString('missing', $this->lang->get('nonexistent_key'));
    }

    public function test_available_languages_map(): void
    {
        $langs = $this->lang->availableLanguages();
        $this->assertArrayHasKey('en', $langs);
        $this->assertSame('English', $langs['en']);
    }

    public function test_invalid_language_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->lang->setActive('zz');
    }
}


// ── HttpResponse ───────────────────────────────────────────────────────────

class HttpResponseTest extends TestCase
{
    public function test_ok_for_2xx(): void
    {
        $r = HttpResponse::fromRaw(200, '{"id":1}');
        $this->assertTrue($r->ok());
        $this->assertFalse($r->failed());
    }

    public function test_failed_for_4xx(): void
    {
        $r = HttpResponse::fromRaw(404, '{"errors":"not found"}');
        $this->assertTrue($r->failed());
    }

    public function test_dot_notation_get(): void
    {
        $r = HttpResponse::fromRaw(200, '{"tokenData":{"token":"abc123"}}');
        $this->assertSame('abc123', $r->get('tokenData.token'));
        $this->assertNull($r->get('nonexistent'));
    }

    public function test_has_errors(): void
    {
        $r = HttpResponse::fromRaw(422, '{"errors":"Validation failed"}');
        $this->assertTrue($r->hasErrors());
    }

    public function test_error_response_is_failed(): void
    {
        $r = HttpResponse::error('Connection refused');
        $this->assertTrue($r->failed());
        $this->assertSame(0, $r->status);
    }
}
