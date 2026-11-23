<?php

use App\Exceptions\ImmutableRecordException;
use App\Models\ActivityLog;
use App\Models\InventoryLedger;
use Illuminate\Support\Facades\Route;

it('refuses to re-link a patient to another user', function () {
    $firstUser = makeUser();
    $patient = makePatientRecord($firstUser);
    $secondUser = makeUser();

    expect(fn () => $patient->update(['user_id' => $secondUser->id]))
        ->toThrow(ImmutableRecordException::class);

    expect($patient->fresh()->user_id)->toBe($firstUser->id);
});

it('refuses to change a patient code', function () {
    $patient = makePatientRecord(makeUser());

    expect(fn () => $patient->update(['patient_code' => 'PT-HACKED']))
        ->toThrow(ImmutableRecordException::class);
});

it('keeps the activity log append-only at the model layer', function () {
    $admin = makeUser(['role' => 'admin']);
    $entry = ActivityLog::query()->create([
        'actor_id' => $admin->id,
        'actor_role' => 'admin',
        'action' => 'test.event',
        'detail' => ['ok' => true],
        'created_at' => now(),
    ]);

    expect(fn () => $entry->update(['action' => 'tampered']))
        ->toThrow(ImmutableRecordException::class);

    expect(fn () => $entry->delete())
        ->toThrow(ImmutableRecordException::class);

    expect(ActivityLog::query()->whereKey($entry->id)->exists())->toBeTrue();
});

it('keeps the inventory ledger append-only', function () {
    $ledger = InventoryLedger::query()->create([
        'medicine_id' => makeMedicine()->id,
        'type' => 'adjustment',
        'quantity_change' => 1,
        'previous_qty' => 1,
        'new_qty' => 2,
        'created_at' => now(),
    ]);

    expect(fn () => $ledger->delete())->toThrow(ImmutableRecordException::class);
});

it('exposes no update or delete routes for the audit trail', function () {
    $auditRoutes = collect(Route::getRoutes())->filter(function ($route) {
        return str_contains($route->uri(), 'activity')
            && in_array('PUT', $route->methods(), true)
            || str_contains($route->uri(), 'activity') && in_array('DELETE', $route->methods(), true);
    });

    expect($auditRoutes)->toBeEmpty();
});
