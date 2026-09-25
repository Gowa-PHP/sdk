<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

enum ScheduleStatus: string
{
    case Active = 'active';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    public static function tryFromValue(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::Unknown;
    }
}
