<?php

namespace App\Enums;

/**
 * Roles the system creates on install. Administrator bypasses every permission check
 * (see AppServiceProvider), so it can never be locked out of a new module.
 */
enum SystemRole: string
{
    case Administrator = 'Administrator';
    case Manager = 'Manager';
    case Cashier = 'Cashier';

    /**
     * System roles cannot be renamed or deleted from the UI.
     */
    public static function isProtected(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }
}
