<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_login_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('device_type', 20)->default('desktop');   // desktop | mobile | tablet
            $table->unsignedTinyInteger('hour_of_day')->default(0);  // 0-23
            $table->boolean('is_bounce')->default(false);             // session < 120 seconds
            $table->unsignedSmallInteger('pages_visited')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'is_bounce']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_login_sessions');
    }
};
