<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id')->unique();
            $table->string('pace', 10)->default('medium');
            $table->float('quiz_average')->default(0);
            $table->jsonb('weak_topics')->default('[]');
            $table->string('preferred_modality', 20)->default('text');
            $table->float('completion_rate')->default(0);
            $table->string('profile_hash', 32)->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('profile_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
