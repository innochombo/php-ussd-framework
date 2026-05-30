<?php

declare(strict_types=1);

namespace PhpUssd\Core;

use PhpUssd\Exceptions\ConfigurationException;
use PhpUssd\Exceptions\UssdException;
use PhpUssd\Gateway\GatewayDriverInterface;
use PhpUssd\Http\HttpClient;
use PhpUssd\I18n\LanguageManager;
use PhpUssd\Menu\MenuNavigator;
use PhpUssd\Menu\MenuRouter;
use PhpUssd\Session\FileSessionManager;
use PhpUssd\Session\SessionManagerInterface;

/**
 * Application kernel.
 *
 * Boots all services from config, processes the request, and returns
 * the serialised response string. This is the only class index.php needs.
 *
 * Usage:
 *
 *   $app = new Application(require 'config/app.php');
 *   echo $app->run($_POST);
 */
class Application
{
    private Config                   $config;
    private Container                $container;
    private GatewayDriverInterface   $gateway;
    private SessionManagerInterface  $session;
    private LanguageManager          $lang;
    private HttpClient               $http;
    private MenuRouter               $router;

    public function __construct(array $config)
    {
        $this->config    = new Config($config);
        $this->container = new Container();
        $this->boot();
    }

    /**
     * Process a raw gateway payload and return the response string.
     * This is the single public entry point.
     */
    public function run(array $payload): string
    {
        try {
            $request = $this->gateway->parse($payload);

            // Load session for this request
            $this->session->load($request->sessionId);

            // Sync language from session
            $sessionLang = $this->session->get('_language');
            if ($sessionLang) {
                $this->lang->setActive($sessionLang);
            }

            // Build HTTP client with auth token if present
            $http = $this->http;
            $token = $this->session->get('auth_token');
            if ($token) {
                $http = $http->withToken($token);
            }

            // Run the state machine
            $navigator = new MenuNavigator(
                router:        $this->router,
                session:       $this->session,
                lang:          $this->lang,
                http:          $http,
                defaultMenuId: $this->config->require('default_menu'),
                mainMenuId:    $this->config->require('main_menu'),
            );

            $response = $navigator->handle($request);

            // Batch-write session once — not on every set() call
            $this->session->save();

            $this->gateway->sendHeaders();
            return $this->gateway->serialize($response);

        } catch (UssdException $e) {
            error_log('[PhpUssd] UssdException: ' . $e->getMessage());
            $this->gateway->sendHeaders();
            return 'END An error occurred. Please try again.';

        } catch (\Throwable $e) {
            error_log('[PhpUssd] Unhandled exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->gateway->sendHeaders();
            return 'END A system error occurred. Please try again later.';
        }
    }

    // ── Boot sequence ──────────────────────────────────────────────────────

    private function boot(): void
    {
        $this->bootGateway();
        $this->bootSession();
        $this->bootLanguages();
        $this->bootHttpClient();
        $this->bootMenuRouter();
    }

    private function bootGateway(): void
    {
        $driverClass = $this->config->require('gateway');

        if (!class_exists($driverClass)) {
            throw ConfigurationException::invalidDriver('gateway', $driverClass);
        }

        $this->gateway = new $driverClass();
    }

    private function bootSession(): void
    {
        $driver = $this->config->get('session.driver', FileSessionManager::class);

        if (!class_exists($driver)) {
            throw ConfigurationException::invalidDriver('session.driver', $driver);
        }

        // FileSessionManager needs a path; other drivers may have different constructors
        if ($driver === FileSessionManager::class || is_a($driver, FileSessionManager::class, true)) {
            $path = $this->config->get('session.path', sys_get_temp_dir() . '/phpussd_sessions');
            $ttl  = $this->config->get('session.ttl', 300);
            $this->session = new $driver($path, $ttl);
        } else {
            // For Redis and custom drivers, expect a factory closure in config
            $factory = $this->config->get('session.factory');
            if ($factory && is_callable($factory)) {
                $this->session = $factory($this->config);
            } else {
                $this->session = new $driver();
            }
        }
    }

    private function bootLanguages(): void
    {
        $languages = $this->config->require('languages');
        $default   = $this->config->get('default_language', array_key_first($languages));

        $this->lang = new LanguageManager($languages, $default);
    }

    private function bootHttpClient(): void
    {
        $this->http = new HttpClient(
            baseUrl:     $this->config->get('api.base_url', ''),
            timeout:     $this->config->get('api.timeout', 30),
            retries:     $this->config->get('api.retries', 1),
            throwOnError: $this->config->get('api.throw_on_error', false),
        );

        // Set default headers (not the auth token — that's per-request from session)
        $headers = $this->config->get('api.headers', []);
        if ($headers) {
            $this->http = $this->http->withHeaders($headers);
        }
    }

    private function bootMenuRouter(): void
    {
        $menus = $this->config->require('menus');
        $this->router = new MenuRouter($menus);
    }
}
