<?php

declare(strict_types=1);

namespace App\Support\Billing\Data;

final class OrganizationUsageData
{
    public function __construct(
        public readonly int $registrationsThisMonth,
        public readonly ?int $registrationsQuota,
        public readonly int $emailsThisMonth,
        public readonly ?int $emailsQuota,
        public readonly int $activeEvents,
        public readonly ?int $activeEventsQuota,
    ) {}

    /**
     * @return array{registrations: ?int, emails: ?int, active_events: ?int}
     */
    public function percentages(): array
    {
        return [
            'registrations' => $this->percentage($this->registrationsThisMonth, $this->registrationsQuota),
            'emails' => $this->percentage($this->emailsThisMonth, $this->emailsQuota),
            'active_events' => $this->percentage($this->activeEvents, $this->activeEventsQuota),
        ];
    }

    private function percentage(int $used, ?int $quota): ?int
    {
        if ($quota === null || $quota === 0) {
            return null;
        }

        return (int) min(100, round($used / $quota * 100));
    }
}
