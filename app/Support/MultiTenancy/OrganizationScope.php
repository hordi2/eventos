<?php

declare(strict_types=1);

namespace App\Support\MultiTenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn('organization_id'),
            app(CurrentOrganization::class)->requireId(),
        );
    }
}
