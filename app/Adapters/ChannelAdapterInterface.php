<?php

namespace App\Adapters;

use App\Models\Notification;

interface ChannelAdapterInterface
{
    /**
     * Send the notification through this channel.
     *
     * @param Notification $notification The notification to send
     * @return bool True on success, false on failure
     */
    public function send(Notification $notification): bool;

    /**
     * Get the channel name.
     */
    public function getChannel(): string;
}
