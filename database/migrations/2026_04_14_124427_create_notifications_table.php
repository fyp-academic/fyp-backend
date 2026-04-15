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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('title');
            $table->text('message');
            $table->timestamp('timestamp')->useCurrent();
            $table->boolean('read')->default(false);
            $table->string('type', 40)->default('info');                // info | assignment | quiz | live | material | ai
            $table->string('action_url')->nullable();                   // deep-link into the relevant page

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
