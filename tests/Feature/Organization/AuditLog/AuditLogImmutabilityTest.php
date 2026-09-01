<?php

declare(strict_types=1);

use App\Domain\Organization\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('empêche la modification d\'une entrée via Eloquent', function (): void {
    $log = AuditLog::factory()->create();

    $log->action = 'modifié';
    $log->save();
})->throws(LogicException::class);

it('empêche la suppression d\'une entrée via Eloquent', function (): void {
    $log = AuditLog::factory()->create();

    $log->delete();
})->throws(LogicException::class);

it('la base de données elle-même refuse toute modification, même en sql brut', function (): void {
    $log = AuditLog::factory()->create();

    DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'modifié']);
})->throws(QueryException::class);

it('la base de données elle-même refuse toute suppression, même en sql brut', function (): void {
    $log = AuditLog::factory()->create();

    DB::table('audit_logs')->where('id', $log->id)->delete();
})->throws(QueryException::class);
