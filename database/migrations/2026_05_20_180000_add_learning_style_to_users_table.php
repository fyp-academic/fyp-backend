<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('vark_style', 20)->nullable()->after('bio');          // V|A|R|K
            $table->json('preferred_modes')->nullable()->after('vark_style');    // [video, pdf, ...]
            $table->string('pace_preference', 30)->nullable()->after('preferred_modes'); // self-directed|guided|accelerated
            $table->json('declared_interests')->nullable()->after('pace_preference'); // [algorithms, databases, ...]
            $table->text('support_notes')->nullable()->after('declared_interests');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vark_style', 'preferred_modes', 'pace_preference', 'declared_interests', 'support_notes']);
        });
    }
};
