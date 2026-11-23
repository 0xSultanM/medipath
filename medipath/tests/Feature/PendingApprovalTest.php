<?php

use App\Models\PendingApproval;
use App\Models\User;

it('shows the pending application, queue position and three-stage progress', function () {
    $this->seed();
    $pending = User::query()->where('email', 'tanvir.alam@medipath.test')->firstOrFail();

    $this->actingAs($pending)->get('/pending')->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Guest/PendingApproval')
            ->where('application.state', 'pending')
            ->where('application.requestedRole', 'doctor')
            ->where('application.requestedRoleLabel', 'Doctor')
            ->where('application.queuePosition', 1)
    );
});

it('returns the poll payload with a checked timestamp', function () {
    $this->seed();
    $pending = User::query()->where('email', 'tanvir.alam@medipath.test')->firstOrFail();

    $this->actingAs($pending)->getJson('/pending/status')
        ->assertOk()
        ->assertJsonPath('state', 'pending')
        ->assertJsonPath('requestedRole', 'doctor')
        ->assertJsonStructure(['checked_at', 'queuePosition', 'totalPending', 'dashboardUrl']);
});

it('shows the rejection reason to a rejected applicant', function () {
    $user = makeUser(['email' => 'rejected.flow@medipath.test', 'role' => 'doctor', 'status' => 'rejected']);
    PendingApproval::query()->create([
        'user_id' => $user->id,
        'requested_role' => 'doctor',
        'status' => 'rejected',
        'review_reason' => 'License image unreadable — please re-upload.',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($user)->get('/pending')->assertInertia(
        fn ($page) => $page
            ->where('application.state', 'rejected')
            ->where('application.reviewReason', 'License image unreadable — please re-upload.')
    );
});

it('redirects approved users away from the holding screen', function () {
    $user = makeUser(['role' => 'admin']);

    $this->actingAs($user)->get('/pending')->assertRedirect(route('admin.dashboard'));
});

it('requires authentication for the holding screen', function () {
    $this->get('/pending')->assertRedirect(route('login'));
});

it('renders an empty state when no application exists', function () {
    $user = makeUser(['email' => 'orphan.pending@medipath.test', 'status' => 'pending']);

    $this->actingAs($user)->get('/pending')->assertInertia(
        fn ($page) => $page->where('application.state', 'none')
    );
});
