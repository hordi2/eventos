<?php

declare(strict_types=1);

namespace App\Support\Status;

use App\Support\Status\Data\SystemHealthResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class CheckSystemHealth
{
    public function handle(): SystemHealthResult
    {
        return new SystemHealthResult([
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ]);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            return Redis::connection()->ping() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
