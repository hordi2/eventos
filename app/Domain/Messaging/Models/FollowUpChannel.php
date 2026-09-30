<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

enum FollowUpChannel: string
{
    case Email = 'email';
    case Whatsapp = 'whatsapp';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail seulement',
            self::Whatsapp => 'WhatsApp seulement',
            self::Both => 'E-mail et WhatsApp',
        };
    }

    public function includesEmail(): bool
    {
        return $this !== self::Whatsapp;
    }

    public function includesWhatsapp(): bool
    {
        return $this !== self::Email;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $channel): array => ['value' => $channel->value, 'label' => $channel->label()], self::cases());
    }
}
