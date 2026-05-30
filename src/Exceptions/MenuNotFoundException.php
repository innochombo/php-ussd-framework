<?php

declare(strict_types=1);

namespace PhpUssd\Exceptions;

class MenuNotFoundException extends UssdException
{
    public static function forId(string $menuId): self
    {
        return new self("No menu registered for ID: '{$menuId}'. Did you add it to config/menus.php?");
    }
}
