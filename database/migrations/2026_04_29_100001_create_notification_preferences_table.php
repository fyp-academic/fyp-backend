<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type', 80)->comment('e.g. assignment_due, grade_released, etc.');
            $table->enum('channel', ['in_app', 'email', 'push', 'sms']);
            $table->boolean('enabled')->default(true);
            $table->enum('digest_mode', ['instant', 'daily', 'weekly'])->default('instant');
            $table->time('quiet_start')->nullable()->comment('e.g. 23:00');
            $table->time('quiet_end')->nullable()->comment('e.g. 07:00');
            $table->timestamps();

            $table->unique(['user_id', 'notification_type', 'channel'], 'uq_pref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
