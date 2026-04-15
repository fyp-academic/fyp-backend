<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->uuid('facilitator_id')->nullable()->index();
            $table->unsignedSmallInteger('week_number');
            $table->string('tier', 20)->default('amber');              // amber | red | critical
            $table->decimal('trigger_score', 5, 2)->nullable();
            $table->string('profile_type', 20)->nullable();
            $table->string('channel', 30)->default('email');           // email | sms | in_app
            $table->string('template_id', 80)->nullable();
            $table->text('message_body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cooldown_expires_at')->nullable();
            $table->decimal('score_at_t7', 5, 2)->nullable();
            $table->decimal('score_at_t14', 5, 2)->nullable();
            $table->string('outcome', 40)->nullable();                 // recovered | ongoing | escalated | no_change
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('facilitator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
