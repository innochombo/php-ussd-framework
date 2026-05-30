<?php

declare(strict_types=1);

namespace PhpUssd\Menu;

use PhpUssd\Core\UssdRequest;
use PhpUssd\Session\SessionManagerInterface;
use PhpUssd\Core\UssdResponse;

/**
 * Guards protect menus from being accessed without the right preconditions.
 *
 * Example guards: AuthGuard (requires valid token in session),
 * VerifiedPhoneGuard, FeatureFlagGuard, etc.
 *
 * Checked by the MenuNavigator BEFORE display() or handleInput() are called.
 */
interface MenuGuardInterface
{
    /**
     * Return true if the guard passes (access allowed).
     */
    public function passes(UssdRequest $request, SessionManagerInterface $session): bool;

    /**
     * What to do when the guard fails.
     * Return a menu ID string to redirect, or a UssdResponse to show directly.
     */
    public function onFail(UssdRequest $request, SessionManagerInterface $session): string|UssdResponse;
}
