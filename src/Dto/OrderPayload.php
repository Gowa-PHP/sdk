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
        $id = $data['order_id'] ?? $data['orderId'] ?? $data['id'] ?? null;
        $title = $data['order_title'] ?? $data['orderTitle'] ?? $data['title'] ?? null;
        $itemCount = $data['item_count'] ?? $data['itemCount'] ?? null;
        $currency = $data['total_currency_code'] ?? $data['totalCurrencyCode'] ?? $data['currency'] ?? null;
        $seller = $data['seller_jid'] ?? $data['sellerJid'] ?? null;
        $msg = $data['message'] ?? null;

        $amount1000 = $data['total_amount_1000'] ?? $data['totalAmount1000'] ?? null;
        $amount = $data['total_amount'] ?? $data['totalAmount'] ?? null;
        $finalAmount = null;
        if ($amount1000 !== null && is_numeric($amount1000)) {
            $finalAmount = ((float) $amount1000) / 1000.0;
        } elseif ($amount !== null && is_numeric($amount)) {
            $finalAmount = (float) $amount;
        }

        if ($id === null && $title === null && $itemCount === null && $finalAmount === null) {
            return null;
        }

        return new self(
            orderId: is_string($id) ? $id : null,
            title: is_string($title) ? $title : null,
            itemCount: $itemCount !== null && is_numeric($itemCount) ? (int) $itemCount : null,
            totalAmount: $finalAmount,
            currency: is_string($currency) ? $currency : null,
            sellerJid: is_string($seller) ? $seller : null,
            message: is_string($msg) ? $msg : null,
        );
    }
}
