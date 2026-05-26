<?php

namespace App\Enums;

enum CustomerSource: string
{
    case WALK_IN = 'walk_in';
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';
    case REFERRAL = 'referral';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WALK_IN => 'Walk In',
            self::INSTAGRAM => 'Instagram',
            self::FACEBOOK => 'Facebook',
            self::REFERRAL => 'Referral',
            self::OTHER => 'Other',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $source): array => ['value' => $source->value, 'label' => $source->label()],
            self::cases()
        );
    }
}
