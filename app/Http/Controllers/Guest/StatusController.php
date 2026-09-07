<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Support\Status\Models\SystemStatusCheck;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Page de statut publique (T-076, §18.2) — aucune authentification, ne
 * révèle rien de plus que « base de données » / « file d'attente » en
 * échec ou non, jamais de détail d'infrastructure.
 */
final class StatusController extends Controller
{
    public function __invoke(): View
    {
        $recentChecks = SystemStatusCheck::query()
            ->where('checked_at', '>=', CarbonImmutable::now()->subDay())
            ->orderByDesc('checked_at')
            ->get();

        $latest = $recentChecks->first();
        $uptimePercentage = $recentChecks->isEmpty()
            ? null
            : round($recentChecks->where('is_healthy', true)->count() / $recentChecks->count() * 100, 2);

        return view('guest.status', [
            'isHealthy' => $latest->is_healthy ?? true,
            'components' => $latest?->components?->toArray() ?? [],
            'uptimePercentage' => $uptimePercentage,
            'checkedAt' => $latest->checked_at ?? null,
            'recentChecks' => $recentChecks->take(20),
        ]);
    }
}
