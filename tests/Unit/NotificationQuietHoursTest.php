<?php

namespace Tests\Unit;

use App\Models\NotificationPreference;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationQuietHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set a fixed time for testing: 02:00 (during typical quiet hours)
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 2, 0, 0));
    }

    public function test_is_in_quiet_hours_returns_true_when_within_quiet_hours()
    {
        $user = User::factory()->create();

        // Quiet hours: 23:00 - 07:00
        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'assignment_due',
            'channel' => 'push',
            'enabled' => true,
            'quiet_start' => '23:00',
            'quiet_end' => '07:00',
        ]);

        // Current time is 02:00, which is within 23:00 - 07:00
        $this->assertTrue($preference->isInQuietHours());
    }

    public function test_is_in_quiet_hours_returns_false_when_outside_quiet_hours()
    {
        // Change time to 10:00 (outside quiet hours)
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 10, 0, 0));

        $user = User::factory()->create();

        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'assignment_due',
            'channel' => 'push',
            'enabled' => true,
            'quiet_start' => '23:00',
            'quiet_end' => '07:00',
        ]);

        $this->assertFalse($preference->isInQuietHours());
    }

    public function test_quiet_hours_handles_non_midnight_crossing_range()
    {
        // Set time to 14:00
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 14, 0, 0));

        $user = User::factory()->create();

        // Quiet hours: 12:00 - 13:00 (lunch break)
        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'assignment_due',
            'channel' => 'push',
            'enabled' => true,
            'quiet_start' => '12:00',
            'quiet_end' => '13:00',
        ]);

        // Current time 14:00 is outside 12:00 - 13:00
        $this->assertFalse($preference->isInQuietHours());

        // Change time to 12:30
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 12, 30, 0));
        $this->assertTrue($preference->isInQuietHours());
    }

    public function test_quiet_hours_returns_false_when_not_set()
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 2, 0, 0));

        $user = User::factory()->create();

        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'assignment_due',
            'channel' => 'push',
            'enabled' => true,
            'quiet_start' => null,
            'quiet_end' => null,
        ]);

        $this->assertFalse($preference->isInQuietHours());
    }

    public function test_should_batch_returns_true_for_digest_modes()
    {
        $user = User::factory()->create();

        $dailyPreference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'quiz_attempts_digest',
            'channel' => 'email',
            'enabled' => true,
            'digest_mode' => 'daily',
        ]);

        $weeklyPreference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'low_engagement_alert',
            'channel' => 'email',
            'enabled' => true,
            'digest_mode' => 'weekly',
        ]);

        $instantPreference = NotificationPreference::create([
            'user_id' => $user->id,
            'notification_type' => 'assignment_due',
            'channel' => 'email',
            'enabled' => true,
            'digest_mode' => 'instant',
        ]);

        $this->assertTrue($dailyPreference->shouldBatch());
        $this->assertTrue($weeklyPreference->shouldBatch());
        $this->assertFalse($instantPreference->shouldBatch());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset
        parent::tearDown();
    }
}
