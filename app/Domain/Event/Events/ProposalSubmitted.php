<?php

declare(strict_types=1);

namespace App\Domain\Event\Events;

use App\Domain\Event\Models\Proposal;
use Illuminate\Foundation\Events\Dispatchable;

final class ProposalSubmitted
{
    use Dispatchable;

    public function __construct(
        public readonly Proposal $proposal,
    ) {}
}
