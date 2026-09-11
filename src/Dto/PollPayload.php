<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class PollPayload
{
    /**
     * @param list<array<string, mixed>>|list<string> $options
     * @param list<string> $selectedOptions
     * @param list<string> $selectedOptionHashes
     */
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $pollId = null,
        public readonly ?string $question = null,
        public readonly array $options = [],
        public readonly int $selectableOptionsCount = 1,
        public readonly array $selectedOptions = [],
        public readonly array $selectedOptionHashes = [],
        public readonly ?string $resolutionStatus = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $question = $data['question'] ?? $data['title'] ?? null;
        $pollId = $data['poll_id'] ?? $data['pollId'] ?? $data['id'] ?? null;
        $type = $data['type'] ?? null;

        if ($question === null && $pollId === null && ! isset($data['options']) && ! isset($data['selected_options'])) {
            return null;
        }

        $options = isset($data['options']) && is_array($data['options']) ? array_values($data['options']) : [];
        $selectedOptions = isset($data['selected_options']) && is_array($data['selected_options'])
            ? array_values(array_map('strval', $data['selected_options']))
            : [];
        $selectedHashes = isset($data['selected_option_hashes']) && is_array($data['selected_option_hashes'])
            ? array_values(array_map('strval', $data['selected_option_hashes']))
            : [];

        $selectableCount = $data['selectable_options_count'] ?? $data['selectableOptionsCount'] ?? 1;

        return new self(
            type: is_string($type) ? $type : null,
            pollId: is_string($pollId) ? $pollId : null,
            question: is_string($question) ? $question : null,
            options: $options,
            selectableOptionsCount: is_numeric($selectableCount) ? (int) $selectableCount : 1,
            selectedOptions: $selectedOptions,
            selectedOptionHashes: $selectedHashes,
            resolutionStatus: is_string($data['resolution_status'] ?? null) ? (string) $data['resolution_status'] : null,
        );
    }

    public function isVote(): bool
    {
        return $this->type === 'vote';
    }

    public function isCreation(): bool
    {
        return $this->type === 'creation';
    }

    public function isResolved(): bool
    {
        return $this->resolutionStatus === 'resolved';
    }
}
