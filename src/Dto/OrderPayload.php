<?php

declare(strict_types=1);

namespace Gowa\Sdk\Dto;

final class OrderPayload
{
    public function __construct(
        public readonly ?string $orderId = null,
        public readonly ?string $title = null,
        public readonly ?int $itemCount = null,
        public readonly ?float $totalAmount = null,
        public readonly ?string $currency = null,
        public readonly ?string $sellerJid = null,
        public readonly ?string $message = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $rawId = $data['order_id'] ?? $data['orderId'] ?? $data['id'] ?? null;
        $orderId = is_string($rawId) && trim($rawId) !== '' ? $rawId : null;

        $rawTitle = $data['order_title'] ?? $data['orderTitle'] ?? $data['title'] ?? null;
        $title = is_string($rawTitle) && trim($rawTitle) !== '' ? $rawTitle : null;

        $rawCount = $data['item_count'] ?? $data['itemCount'] ?? null;
        $itemCount = $rawCount !== null && is_numeric($rawCount) ? (int) $rawCount : null;

        $rawCurrency = $data['total_currency_code'] ?? $data['totalCurrencyCode'] ?? $data['currency'] ?? null;
        $currency = is_string($rawCurrency) && trim($rawCurrency) !== '' ? $rawCurrency : null;

        $rawSeller = $data['seller_jid'] ?? $data['sellerJid'] ?? null;
        $sellerJid = is_string($rawSeller) && trim($rawSeller) !== '' ? $rawSeller : null;

        $rawMsg = $data['message'] ?? null;
        $message = is_string($rawMsg) ? $rawMsg : null;

        $amount1000 = $data['total_amount_1000'] ?? $data['totalAmount1000'] ?? null;
        $amount = $data['total_amount'] ?? $data['totalAmount'] ?? null;
        $finalAmount = null;
        if ($amount1000 !== null && is_numeric($amount1000)) {
            $parsed = ((float) $amount1000) / 1000.0;
            $finalAmount = is_finite($parsed) ? $parsed : null;
        } elseif ($amount !== null && is_numeric($amount)) {
            $parsed = (float) $amount;
            $finalAmount = is_finite($parsed) ? $parsed : null;
        }

        if ($orderId === null && $title === null && $itemCount === null && $finalAmount === null) {
            return null;
        }

        return new self(
            orderId: $orderId,
            title: $title,
            itemCount: $itemCount,
            totalAmount: $finalAmount,
            currency: $currency,
            sellerJid: $sellerJid,
            message: $message,
        );
    }
}
