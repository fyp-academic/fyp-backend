<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->uuid('course_id')->index();
            $table->uuid('uploaded_by')->nullable()->index();

            $table->string('title');
            $table->string('type', 30);               // pdf | pptx | video | youtube | h5p | scorm | doc | image
            $table->string('file_path')->nullable();   // storage path for uploaded files
            $table->string('url')->nullable();          // external URL (YouTube, etc.)
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);

            $table->longText('extracted_text')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('processing_status', 20)->default('pending'); // pending | processing | completed | failed
            $table->text('processing_error')->nullable();

            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
