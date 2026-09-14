<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Organizer = 'organizer';
    case Customer = 'customer';
    case Gatekeeper = 'gatekeeper';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'System Admin',
            self::Organizer => 'Event Organizer',
            self::Customer => 'Customer',
            self::Gatekeeper => 'Gatekeeper',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function selfRegistrable(): array
    {
        return [self::Customer->value, self::Organizer->value];
    }

    /**
     * Roles that can be assigned to accounts managed by the admin (never Admin).
     *
     * @return array<int, string>
     */
    public static function manageable(): array
    {
        return [...self::selfRegistrable(), self::Gatekeeper->value];
    }
}
