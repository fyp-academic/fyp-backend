<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('certificate_id')->index();                    // FK → certificate_templates
            $table->uuid('student_id')->index();
            $table->timestamp('issued_at')->useCurrent();
            $table->string('code', 80)->unique();                       // verifiable unique code
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('certificate_id')->references('id')->on('certificate_templates')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_issues');
    }
};
