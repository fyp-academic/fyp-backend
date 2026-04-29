<?php

namespace App\Adapters;

use InvalidArgumentException;

class ChannelAdapterFactory
{
    /**
     * Create an adapter instance for the given channel.
     *
     * @param string $channel The channel name (email, in_app, push, sms)
     * @return ChannelAdapterInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $channel): ChannelAdapterInterface
    {
        return match($channel) {
            'email' => new EmailAdapter(),
            'in_app' => new InAppAdapter(),
            'push' => new PushAdapter(),
            'sms' => new SmsAdapter(),
            default => throw new InvalidArgumentException("Unknown notification channel: {$channel}"),
        };
    }
}
