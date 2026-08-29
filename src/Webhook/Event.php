<?php

declare(strict_types=1);

namespace Gowa\Sdk\Webhook;

enum Event: string
{
    case Message = 'message';
    case MessageReaction = 'message.reaction';
    case MessageRevoked = 'message.revoked';
    case MessageEdited = 'message.edited';
    case MessageAck = 'message.ack';
    case MessageDeleted = 'message.deleted';
    case ChatPresence = 'chat_presence';
    case GroupParticipants = 'group.participants';
    case GroupJoined = 'group.joined';
    case CallOffer = 'call.offer';
    case Unknown = 'unknown';

    public static function tryFromValue(string $value): self
    {
        return self::tryFrom(strtolower($value)) ?? self::Unknown;
    }
}
