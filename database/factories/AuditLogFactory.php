<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Organization\Models\AuditLog;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'causer_type' => User::class,
            'causer_id' => User::factory(),
            'action' => 'auth.login',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'metadata' => [],
        ];
    }
}
