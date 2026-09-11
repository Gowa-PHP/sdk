<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case VideoNote = 'video_note';
    case Audio = 'audio';
    case Document = 'document';
    case Sticker = 'sticker';
    case Location = 'location';
    case LiveLocation = 'live_location';
    case Contact = 'contact';
    case ContactsArray = 'contacts_array';
    case Poll = 'poll';
    case Order = 'order';
    case Event = 'event';
    case Interactive = 'interactive';
    case List = 'list';
    case Unknown = 'unknown';

    public static function tryFromValue(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? self::Unknown;
    }
}
