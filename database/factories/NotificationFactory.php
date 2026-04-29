<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                'assignment_posted',
                'grade_released',
                'course_announcement',
                'quiz_available',
            ]),
            'channel' => $this->faker->randomElement(['in_app', 'email', 'push']),
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'payload' => null,
            'status' => $this->faker->randomElement(['pending', 'sent', 'delivered', 'read', 'failed']),
            'dedup_key' => null,
            'retry_count' => 0,
        ];
    }
}
