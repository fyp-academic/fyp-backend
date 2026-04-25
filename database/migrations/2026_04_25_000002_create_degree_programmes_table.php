<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degree_programmes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('college_id')->index();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('college_id')->references('id')->on('colleges')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_programmes');
    }
};
