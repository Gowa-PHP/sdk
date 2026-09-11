<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class ContactCard
{
    /**
     * @param list<array{phone: string}> $phones
     */
    public function __construct(
        public readonly string $name,
        public readonly array $phones = [],
        public readonly ?string $vcard = null,
    ) {}

    public function phone(): ?string
    {
        return isset($this->phones[0]['phone']) && is_string($this->phones[0]['phone'])
            ? $this->phones[0]['phone']
            : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $name = $data['displayName'] ?? $data['display_name'] ?? $data['name'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        $phone = $data['phone_number'] ?? $data['phone'] ?? null;
        $phones = [];
        if (is_string($phone) && $phone !== '') {
            $phones[] = ['phone' => $phone];
        } elseif (isset($data['phones']) && is_array($data['phones'])) {
            foreach ($data['phones'] as $item) {
                if (is_array($item) && isset($item['phone']) && is_string($item['phone']) && $item['phone'] !== '') {
                    $phones[] = ['phone' => $item['phone']];
                }
            }
        }

        $vcard = isset($data['vcard']) && is_string($data['vcard']) ? $data['vcard'] : null;

        return new self(
            name: $name,
            phones: $phones,
            vcard: $vcard,
        );
    }
}
