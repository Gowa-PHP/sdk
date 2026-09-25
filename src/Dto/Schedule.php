<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class Schedule
{
    /**
     * @param list<int>|null $weekdays
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $id,
        public readonly string $messageType,
        public readonly string $phone,
        public readonly ?string $summary = null,
        public readonly ScheduleStatus $status = ScheduleStatus::Active,
        public readonly ?string $scheduledAt = null,
        public readonly ?string $nextRunAt = null,
        public readonly ?string $timezone = null,
        public readonly string $recurrence = 'once',
        public readonly ?array $weekdays = null,
        public readonly ?int $dayOfMonth = null,
        public readonly ?string $endAt = null,
        public readonly ?int $occurrenceLimit = null,
        public readonly int $occurrenceCount = 0,
        public readonly int $attempts = 0,
        public readonly ?string $lastRunAt = null,
        public readonly ?string $lastMessageId = null,
        public readonly ?string $lastError = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusStr = (string) ($data['status'] ?? 'active');

        $weekdays = null;
        if (isset($data['weekdays'])) {
            if (is_array($data['weekdays'])) {
                $weekdays = array_values(array_map('intval', $data['weekdays']));
            } elseif (is_string($data['weekdays']) && trim($data['weekdays']) !== '') {
                $parts = array_filter(
                    array_map('trim', explode(',', $data['weekdays'])),
                    fn($v) => is_numeric($v),
                );
                $weekdays = array_values(array_map('intval', $parts));
            }
        }

        $dayOfMonth = null;
        if (isset($data['day_of_month']) && is_numeric($data['day_of_month'])) {
            $dayOfMonth = (int) $data['day_of_month'];
        }

        $occurrenceLimit = null;
        if (isset($data['occurrence_limit']) && is_numeric($data['occurrence_limit'])) {
            $occurrenceLimit = (int) $data['occurrence_limit'];
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            messageType: (string) ($data['message_type'] ?? ''),
            phone: (string) ($data['phone'] ?? ''),
            summary: is_string($data['summary'] ?? null) ? (string) $data['summary'] : null,
            status: ScheduleStatus::tryFromValue($statusStr),
            scheduledAt: is_string($data['scheduled_at'] ?? null) ? (string) $data['scheduled_at'] : null,
            nextRunAt: is_string($data['next_run_at'] ?? null) ? (string) $data['next_run_at'] : null,
            timezone: is_string($data['timezone'] ?? null) ? (string) $data['timezone'] : null,
            recurrence: (string) ($data['recurrence'] ?? 'once'),
            weekdays: $weekdays,
            dayOfMonth: $dayOfMonth,
            endAt: is_string($data['end_at'] ?? null) ? (string) $data['end_at'] : null,
            occurrenceLimit: $occurrenceLimit,
            occurrenceCount: (int) ($data['occurrence_count'] ?? 0),
            attempts: (int) ($data['attempts'] ?? 0),
            lastRunAt: is_string($data['last_run_at'] ?? null) ? (string) $data['last_run_at'] : null,
            lastMessageId: is_string($data['last_message_id'] ?? null) ? (string) $data['last_message_id'] : null,
            lastError: is_string($data['last_error'] ?? null) ? (string) $data['last_error'] : null,
            createdAt: is_string($data['created_at'] ?? null) ? (string) $data['created_at'] : null,
            updatedAt: is_string($data['updated_at'] ?? null) ? (string) $data['updated_at'] : null,
            raw: $data,
        );
    }
}
