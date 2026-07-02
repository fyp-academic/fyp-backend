<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Web Push (VAPID) subscription fields to user_devices.
     *
     * A browser PushSubscription is identified by its endpoint URL and carries
     * two keys (p256dh + auth) used to encrypt the payload. These are separate
     * from the legacy FCM/APNS `push_token` column.
     */
    public function up(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->text('endpoint')->nullable()->after('push_token')
                ->comment('Web Push subscription endpoint URL');
            $table->string('public_key')->nullable()->after('endpoint')
                ->comment('Web Push p256dh key');
            $table->string('auth_token')->nullable()->after('public_key')
                ->comment('Web Push auth secret');
        });
    }

    public function down(): void
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->dropColumn(['endpoint', 'public_key', 'auth_token']);
        });
    }
};
