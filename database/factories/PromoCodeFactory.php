<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Domain\Ticketing\Models\PromoCode;
use App\Domain\Ticketing\Models\PromoCodeKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
final class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'event_id' => Event::factory(),
            'created_by' => User::factory(),
            'code' => mb_strtoupper(fake()->unique()->lexify('PROMO????')),
            'kind' => PromoCodeKind::Percent,
            'percent_bp' => 1000,
            'max_uses' => null,
            'is_active' => true,
        ];
    }
}
