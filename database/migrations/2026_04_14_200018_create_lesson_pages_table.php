<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('page_type', 30)->default('content');        // content | question | branch_table | end_of_branch | cluster | end_of_cluster
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('jumps')->nullable();                          // [{answer, destination_page_id}]
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_pages');
    }
};
