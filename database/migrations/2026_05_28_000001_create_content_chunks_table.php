<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Maps to lesson_pages since that is the primary content store in this LMS
            $table->uuid('content_id');
            $table->integer('chunk_index');
            $table->text('chunk_text');
            $table->string('chunk_type', 20)->default('lecture'); // lecture | note | pdf_text | example | quiz | assessment
            $table->timestamps();

            $table->index(['content_id', 'chunk_index']);
            $table->foreign('content_id')->references('id')->on('lesson_pages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_chunks');
    }
};
