<?php

declare(strict_types=1);

use App\Support\Casts\AsMoney;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Table et modèle jetables
|--------------------------------------------------------------------------
|
| Aucun modèle métier ne stocke encore d'argent (T-050+). On valide ici le
| cast AsMoney de bout en bout sur une vraie table, avant qu'un module
| métier n'en dépende.
|
*/
function priceableModel(): Model
{
    return new class extends Model
    {
        protected $table = 'priceables';

        protected $fillable = ['price', 'fee'];

        protected function casts(): array
        {
            return [
                'price' => AsMoney::class,
                'fee' => AsMoney::class.':fee_amount_minor,fee_currency',
            ];
        }
    };
}

beforeEach(function (): void {
    Schema::create('priceables', function (Blueprint $table): void {
        $table->id();
        $table->bigInteger('amount_minor')->nullable();
        $table->char('currency', 3)->nullable();
        $table->bigInteger('fee_amount_minor')->nullable();
        $table->char('fee_currency', 3)->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('priceables');
});

it('persiste et relit un Money sur les colonnes par défaut', function (): void {
    $model = priceableModel()::query()->create([
        'price' => Money::fromMinorUnits(1050, 'EUR'),
    ]);

    $fresh = $model::query()->find($model->id);

    expect($fresh->price)->toBeInstanceOf(Money::class);
    expect($fresh->price->amountMinor())->toBe(1050);
    expect($fresh->price->currency())->toBe('EUR');
    expect($fresh->getAttributes()['amount_minor'])->toBe(1050);
    expect($fresh->getAttributes()['currency'])->toBe('EUR');
});

it('persiste et relit un Money sur des colonnes personnalisées', function (): void {
    $model = priceableModel()::query()->create([
        'fee' => Money::fromMinorUnits(250, 'USD'),
    ]);

    $fresh = $model::query()->find($model->id);

    expect($fresh->fee)->toBeInstanceOf(Money::class);
    expect($fresh->fee->amountMinor())->toBe(250);
    expect($fresh->fee->currency())->toBe('USD');
});

it('retourne null quand aucun montant n\'est enregistré', function (): void {
    $model = priceableModel()::query()->create([]);

    expect($model->price)->toBeNull();
});

it('rejette une valeur qui n\'est pas une instance de Money', function (): void {
    priceableModel()::query()->create(['price' => 'pas un Money']);
})->throws(InvalidArgumentException::class);
