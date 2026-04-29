<?php

namespace Tests\Feature;

use App\Constants\NotificationTypes;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationDispatchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
        Mail::fake();
    }

    public function test_dispatching_grade_released_delivers_in_app_and_email()
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'student@test.com']);

        // Seed preferences for grade_released
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'email',
            'enabled' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'in_app',
            'enabled' => true,
        ]);

        $payload = [
            'title' => 'Your grade has been released',
            'body' => 'You scored 95% on Assignment 1',
            'action_url' => '/courses/1/grades',
            'grade' => 95,
            'assignment_id' => 1,
        ];

        // Dispatch the notification
        $notification = $this->notificationService->dispatch(
            NotificationTypes::GRADE_RELEASED,
            $user->id,
            $payload,
            'assignment_1'
        );

        // Assert notification was created
        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'email',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'in_app',
        ]);

        // Assert 2 notifications created (email + in_app)
        $this->assertEquals(2, Notification::count());

        // Assert jobs were dispatched
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_dispatching_skips_disabled_channels()
    {
        Queue::fake();

        $user = User::factory()->create();

        // Email enabled, in_app disabled
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'email',
            'enabled' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'in_app',
            'enabled' => false,
        ]);

        $payload = [
            'title' => 'Your grade has been released',
            'body' => 'You scored 95%',
        ];

        $this->notificationService->dispatch(
            NotificationTypes::GRADE_RELEASED,
            $user->id,
            $payload,
            'assignment_1'
        );

        // Only 1 notification (email only)
        $this->assertEquals(1, Notification::count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'channel' => 'email',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'channel' => 'in_app',
        ]);
    }

    public function test_dispatching_with_no_preferences_creates_defaults()
    {
        Queue::fake();

        $user = User::factory()->create();

        // No preferences exist yet
        $this->assertEquals(0, NotificationPreference::count());

        $payload = [
            'title' => 'Assignment Posted',
            'body' => 'New assignment available',
        ];

        $this->notificationService->dispatch(
            NotificationTypes::ASSIGNMENT_POSTED,
            $user->id,
            $payload,
            'assignment_1'
        );

        // Default preferences should be created
        $this->assertGreaterThan(0, NotificationPreference::where('user_id', $user->id)->count());

        // Notification should be created for default channels
        $this->assertGreaterThan(0, Notification::count());
    }

    public function test_dispatching_respects_dedup_key()
    {
        $user = User::factory()->create();

        // Create first notification
        $this->notificationService->dispatch(
            NotificationTypes::ASSIGNMENT_DUE_SOON,
            $user->id,
            ['title' => 'Due Soon', 'body' => 'Assignment due'],
            'assignment_123'
        );

        $firstCount = Notification::count();

        // Try to dispatch duplicate with same context
        $duplicate = $this->notificationService->dispatch(
            NotificationTypes::ASSIGNMENT_DUE_SOON,
            $user->id,
            ['title' => 'Due Soon', 'body' => 'Assignment due'],
            'assignment_123'
        );

        // Duplicate should return null
        $this->assertNull($duplicate);

        // Count should not increase
        $this->assertEquals($firstCount, Notification::count());
    }

    public function test_mark_notification_as_read_updates_badge()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create unread notifications
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'sent',
        ]);

        $notification = Notification::first();

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJson([
                'message' => 'Notification marked as read.',
                'unread_count' => 2, // 3 - 1 = 2
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => 'read',
        ]);
    }

    public function test_get_paginated_notifications_with_status_filter()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create notifications with different statuses
        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'status' => 'sent',
        ]);
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'read',
        ]);

        // Test unread filter
        $response = $this->actingAs($user)
            ->getJson('/api/v1/notifications?status=unread&limit=10');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('unread_count', 5);

        // Test read filter
        $response = $this->actingAs($user)
            ->getJson('/api/v1/notifications?status=read&limit=10');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_update_preferences_endpoint()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/api/v1/notifications/preferences', [
                'preferences' => [
                    [
                        'type' => 'assignment_posted',
                        'channel' => 'email',
                        'enabled' => false,
                    ],
                    [
                        'type' => 'grade_released',
                        'channel' => 'push',
                        'enabled' => true,
                        'digest_mode' => 'daily',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Preferences updated successfully.');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'notification_type' => 'assignment_posted',
            'channel' => 'email',
            'enabled' => false,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'notification_type' => 'grade_released',
            'channel' => 'push',
            'enabled' => true,
            'digest_mode' => 'daily',
        ]);
    }
}
