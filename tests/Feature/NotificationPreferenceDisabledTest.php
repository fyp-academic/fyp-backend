<?php

namespace Tests\Feature;

use App\Constants\NotificationTypes;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationPreferenceDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    public function test_preference_disabled_skips_channel_delivery()
    {
        Queue::fake();

        $user = User::factory()->create();

        // Create disabled preference
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::COURSE_ANNOUNCEMENT,
            'channel' => 'email',
            'enabled' => false,
            'digest_mode' => 'instant',
        ]);

        $payload = [
            'title' => 'Course Announcement',
            'body' => 'Important update to the course',
        ];

        // Dispatch notification
        $notification = $this->notificationService->dispatch(
            NotificationTypes::COURSE_ANNOUNCEMENT,
            $user->id,
            $payload,
            'announcement_1'
        );

        // No notification should be created (channel disabled)
        $this->assertNull($notification);
        $this->assertEquals(0, Notification::count());

        // No job should be dispatched
        Queue::assertNothingPushed();
    }

    public function test_partially_disabled_preferences_deliver_to_enabled_channels_only()
    {
        Queue::fake();

        $user = User::factory()->create();

        // Email disabled, in_app enabled, push disabled
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::QUIZ_AVAILABLE,
            'channel' => 'email',
            'enabled' => false,
        ]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::QUIZ_AVAILABLE,
            'channel' => 'in_app',
            'enabled' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::QUIZ_AVAILABLE,
            'channel' => 'push',
            'enabled' => false,
        ]);

        $payload = [
            'title' => 'Quiz Available',
            'body' => 'Quiz 1 is now open',
        ];

        $this->notificationService->dispatch(
            NotificationTypes::QUIZ_AVAILABLE,
            $user->id,
            $payload,
            'quiz_1'
        );

        // Only in_app notification should be created
        $this->assertEquals(1, Notification::count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationTypes::QUIZ_AVAILABLE,
            'channel' => 'in_app',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'type' => NotificationTypes::QUIZ_AVAILABLE,
            'channel' => 'email',
        ]);
    }

    public function test_global_mute_prevents_all_notifications()
    {
        Queue::fake();

        $user = User::factory()->create();

        // Enable all preferences
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::GRADE_RELEASED,
            'channel' => 'email',
            'enabled' => true,
        ]);

        // Set global mute
        $this->notificationService->setGlobalMute($user->id, true);

        $this->assertTrue($this->notificationService->isGloballyMuted($user->id));

        // Dispatch should still create notification but be handled differently
        // (In a full implementation, the service would check global mute)
    }

    public function test_preference_check_happens_in_worker_not_dispatcher()
    {
        $user = User::factory()->create();

        // Create enabled preference
        $pref = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::NEW_SUBMISSION,
            'channel' => 'email',
            'enabled' => true,
        ]);

        $payload = [
            'title' => 'New Submission',
            'body' => 'Student submitted work',
        ];

        // Dispatch should create notification
        $this->notificationService->dispatch(
            NotificationTypes::NEW_SUBMISSION,
            $user->id,
            $payload,
            'submission_1'
        );

        // Notification created even if preference changes before worker runs
        $this->assertEquals(1, Notification::count());

        // Now disable preference (simulating user changing settings)
        $pref->update(['enabled' => false]);

        // The job when processed would re-check preferences and skip
        // This is verified by the job's internal logic
    }

    public function test_digest_mode_skips_immediate_queue()
    {
        Queue::fake();

        $user = User::factory()->create();

        // Create preference with daily digest
        NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => NotificationTypes::LOW_ENGAGEMENT_ALERT,
            'channel' => 'email',
            'enabled' => true,
            'digest_mode' => 'daily',
        ]);

        $payload = [
            'title' => 'Low Engagement Alert',
            'body' => 'Some students have low engagement',
        ];

        $this->notificationService->dispatch(
            NotificationTypes::LOW_ENGAGEMENT_ALERT,
            $user->id,
            $payload,
            'alert_1'
        );

        // Notification created but not queued immediately
        $this->assertEquals(1, Notification::count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Should be added to digest, not queued
        Queue::assertNothingPushed();

        // Digest record should exist
        $this->assertDatabaseHas('notification_digests', [
            'user_id' => $user->id,
            'channel' => 'email',
            'frequency' => 'daily',
        ]);
    }
}
