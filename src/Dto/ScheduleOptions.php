<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class ScheduleOptions
{
    /**
     * @param list<int>|null $weekdays Days to send on, 0=Sunday through 6=Saturday
     */
    public function __construct(
        public readonly string $scheduledAt,
        public readonly string $timezone,
        public readonly string $recurrence = 'once',
        public readonly ?array $weekdays = null,
        public readonly ?int $dayOfMonth = null,
        public readonly ?string $endAt = null,
        public readonly ?int $occurrenceLimit = null,
    ) {}

    /**
     * Build parameters array for JSON send payloads
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'scheduled_at' => $this->scheduledAt,
            'timezone'     => $this->timezone,
            'recurrence'   => $this->recurrence,
        ];

        if ($this->weekdays !== null && $this->weekdays !== []) {
            $payload['weekdays'] = array_values($this->weekdays);
        }

        if ($this->dayOfMonth !== null) {
            $payload['day_of_month'] = $this->dayOfMonth;
        }

        if ($this->endAt !== null && $this->endAt !== '') {
            $payload['end_at'] = $this->endAt;
        }

        if ($this->occurrenceLimit !== null) {
            $payload['occurrence_limit'] = $this->occurrenceLimit;
        }

        return $payload;
    }

    /**
     * Build multipart form-data items for multipart send endpoints
     *
     * @return list<array{name: string, contents: string}>
     */
    public function toMultipart(): array
    {
        $items = [
            ['name' => 'scheduled_at', 'contents' => $this->scheduledAt],
            ['name' => 'timezone', 'contents' => $this->timezone],
            ['name' => 'recurrence', 'contents' => $this->recurrence],
        ];

        if ($this->weekdays !== null) {
            foreach ($this->weekdays as $day) {
                $items[] = ['name' => 'weekdays', 'contents' => (string) $day];
            }
        }

        if ($this->dayOfMonth !== null) {
            $items[] = ['name' => 'day_of_month', 'contents' => (string) $this->dayOfMonth];
        }

        if ($this->endAt !== null && $this->endAt !== '') {
            $items[] = ['name' => 'end_at', 'contents' => $this->endAt];
        }

        if ($this->occurrenceLimit !== null) {
            $items[] = ['name' => 'occurrence_limit', 'contents' => (string) $this->occurrenceLimit];
        }

        return $items;
    }
}
