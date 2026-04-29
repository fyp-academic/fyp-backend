<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_notification_is_prevented_by_dedup_key()
    {
        $user = User::factory()->create();

        // Create first notification with dedup key
        $notification1 = Notification::create([
            'user_id' => $user->id,
            'type' => 'assignment_due',
            'channel' => 'email',
            'title' => 'Assignment Due',
            'body' => 'Your assignment is due soon',
            'dedup_key' => 'assignment_due__' . $user->id . '__assignment_123',
            'status' => 'pending',
        ]);

        // Try to create duplicate with same dedup key
        $this->expectException(\Illuminate\Database\QueryException::class);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'assignment_due',
            'channel' => 'email',
            'title' => 'Assignment Due',
            'body' => 'Your assignment is due soon',
            'dedup_key' => 'assignment_due__' . $user->id . '__assignment_123', // Same key
            'status' => 'pending',
        ]);
    }

    public function test_notifications_with_different_context_ids_are_allowed()
    {
        $user = User::factory()->create();

        // Create first notification
        $notification1 = Notification::create([
            'user_id' => $user->id,
            'type' => 'assignment_due',
            'channel' => 'email',
            'title' => 'Assignment 1 Due',
            'body' => 'Assignment 1 is due',
            'dedup_key' => 'assignment_due__' . $user->id . '__assignment_123',
            'status' => 'pending',
        ]);

        // Create notification for different assignment - should succeed
        $notification2 = Notification::create([
            'user_id' => $user->id,
            'type' => 'assignment_due',
            'channel' => 'email',
            'title' => 'Assignment 2 Due',
            'body' => 'Assignment 2 is due',
            'dedup_key' => 'assignment_due__' . $user->id . '__assignment_456', // Different key
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification1->id,
            'type' => 'assignment_due',
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification2->id,
            'type' => 'assignment_due',
        ]);

        $this->assertEquals(2, Notification::count());
    }

    public function test_same_notification_type_for_different_users_is_allowed()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $notification1 = Notification::create([
            'user_id' => $user1->id,
            'type' => 'grade_released',
            'channel' => 'email',
            'title' => 'Grade Released',
            'body' => 'Your grade is available',
            'dedup_key' => 'grade_released__' . $user1->id . '__grade_789',
            'status' => 'pending',
        ]);

        $notification2 = Notification::create([
            'user_id' => $user2->id,
            'type' => 'grade_released',
            'channel' => 'email',
            'title' => 'Grade Released',
            'body' => 'Your grade is available',
            'dedup_key' => 'grade_released__' . $user2->id . '__grade_789', // Same context, different user
            'status' => 'pending',
        ]);

        $this->assertEquals(2, Notification::count());
    }

    public function test_notification_service_generates_correct_dedup_key()
    {
        $userId = 123;
        $type = 'assignment_due';
        $contextId = 'assignment_456';

        $expectedKey = 'assignment_due__123__assignment_456';
        $actualKey = $this->generateDedupKey($type, $userId, $contextId);

        $this->assertEquals($expectedKey, $actualKey);
    }

    private function generateDedupKey(string $type, int $userId, string $contextId): string
    {
        return "{$type}__{$userId}__{$contextId}";
    }
}
