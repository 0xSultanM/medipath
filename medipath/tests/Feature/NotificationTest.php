<?php

use App\Services\NotificationService;

it('creates a notification and keeps unread counts server-authoritative', function () {
    $user = makeUser();
    $service = app(NotificationService::class);

    $notification = $service->send(
        $user,
        'appointment.accepted',
        'Appointment accepted',
        'Your appointment was accepted.',
        'appointment',
        ['appointment_id' => 99],
        '/patient/appointments',
    );

    expect($notification->exists)->toBeTrue()
        ->and($service->unreadCount($user))->toBe(1)
        ->and($service->recent($user))->toHaveCount(1)
        ->and($notification->data['appointment_id'])->toBe(99);

    expect($service->markRead($user, $notification->id))->toBeTrue();
    expect($service->unreadCount($user))->toBe(0);

    $service->send($user, 'billing.paid', 'Bill paid', 'Receipt ready.', 'billing');
    expect($service->markAllRead($user))->toBe(1)
        ->and($service->unreadCount($user))->toBe(0);
});

it('does not let a user mark another user\'s notification as read', function () {
    $owner = makeUser();
    $attacker = makeUser();
    $notification = app(NotificationService::class)->send($owner, 'system.info', 'Private', 'Private message.');

    $this->actingAs($attacker)
        ->postJson("/notifications/{$notification->id}/read")
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks only the current user\'s notifications on read-all', function () {
    $owner = makeUser();
    $other = makeUser();
    $service = app(NotificationService::class);
    $service->send($owner, 'system.info', 'Owner only', 'Owner message.');
    $service->send($other, 'system.info', 'Other only', 'Other message.');

    $this->actingAs($owner)->post('/notifications/read-all')->assertRedirect();

    expect($service->unreadCount($owner))->toBe(0)
        ->and($service->unreadCount($other))->toBe(1);
});

it('returns the unread count as JSON for polling clients', function () {
    $user = makeUser();
    app(NotificationService::class)->send($user, 'system.info', 'One', 'Body');

    $this->actingAs($user)->getJson('/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('unread', 1);
});

it('is defensive about missing notification ids', function () {
    $user = makeUser();

    $this->actingAs($user)->postJson('/notifications/999999/read')->assertNotFound();
});
