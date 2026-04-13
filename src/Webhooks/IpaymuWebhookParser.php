<?php

namespace Aliziodev\PayIdIpaymu\Webhooks;

use Aliziodev\PayId\DTO\NormalizedWebhook;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Exceptions\WebhookParsingException;
use Illuminate\Http\Request;

final class IpaymuWebhookParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callbackParams(array $payload): array
    {
        $statusRaw = (string) data_get(
            $payload,
            'status',
            data_get($payload, 'transactionStatus', data_get($payload, 'transaction_status', 'pending')),
        );

        return [
            'reference_id' => (string) data_get(
                $payload,
                'referenceId',
                data_get($payload, 'reference_id', data_get($payload, 'order_id', '')),
            ),
            'transaction_id' => (string) data_get(
                $payload,
                'transactionId',
                data_get($payload, 'transaction_id', data_get($payload, 'sessionId', '')),
            ),
            'status_raw' => $statusRaw,
            'status' => $this->mapStatus($statusRaw)->value,
            'amount' => (int) data_get($payload, 'amount', data_get($payload, 'total', 0)),
            'currency' => (string) data_get($payload, 'currency', 'IDR'),
            'channel_raw' => (string) data_get($payload, 'paymentMethod', data_get($payload, 'channel', '')),
            'occurred_at' => data_get($payload, 'updated_at', data_get($payload, 'created_at')),
            'raw_payload' => $payload,
        ];
    }

    public function parse(Request $request, bool $signatureValid): NormalizedWebhook
    {
        $payload = $request->all();

        $merchantOrderId = (string) data_get(
            $payload,
            'referenceId',
            data_get($payload, 'reference_id', data_get($payload, 'order_id', '')),
        );

        if ($merchantOrderId === '') {
            throw new WebhookParsingException('ipaymu', 'Missing referenceId/reference_id/order_id in webhook payload.');
        }

        $status = (string) data_get(
            $payload,
            'status',
            data_get($payload, 'transactionStatus', data_get($payload, 'transaction_status', 'pending')),
        );

        return NormalizedWebhook::make([
            'provider' => 'ipaymu',
            'merchant_order_id' => $merchantOrderId,
            'provider_transaction_id' => (string) data_get(
                $payload,
                'transactionId',
                data_get($payload, 'transaction_id', data_get($payload, 'sessionId', '')),
            ),
            'event_type' => (string) data_get($payload, 'event', 'payment.notification'),
            'status' => $this->mapStatus($status),
            'amount' => (int) data_get($payload, 'amount', data_get($payload, 'total', 0)),
            'currency' => (string) data_get($payload, 'currency', 'IDR'),
            'channel' => $this->mapChannel((string) data_get($payload, 'paymentMethod', data_get($payload, 'channel', ''))),
            'signature_valid' => $signatureValid,
            'raw_payload' => $payload,
            'occurred_at' => data_get($payload, 'updated_at', data_get($payload, 'created_at', now()->toISOString())),
        ]);
    }

    protected function mapStatus(string $status): PaymentStatus
    {
        return match (strtolower($status)) {
            'berhasil', 'paid', 'settlement', 'settled', 'success' => PaymentStatus::Paid,
            'pending', 'process', 'processing' => PaymentStatus::Pending,
            'gagal', 'failed', 'deny' => PaymentStatus::Failed,
            'expired', 'kadaluarsa' => PaymentStatus::Expired,
            'cancelled', 'canceled', 'batal' => PaymentStatus::Cancelled,
            'refund', 'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Created,
        };
    }

    protected function mapChannel(string $channel): ?PaymentChannel
    {
        return match (strtolower($channel)) {
            'va_bca', 'bca' => PaymentChannel::VaBca,
            'va_bni', 'bni' => PaymentChannel::VaBni,
            'va_bri', 'bri' => PaymentChannel::VaBri,
            'va_mandiri', 'mandiri' => PaymentChannel::VaMandiri,
            'qris' => PaymentChannel::Qris,
            'gopay' => PaymentChannel::Gopay,
            'ovo' => PaymentChannel::Ovo,
            'dana' => PaymentChannel::Dana,
            'shopeepay' => PaymentChannel::Shopeepay,
            'alfamart' => PaymentChannel::CstoreAlfamart,
            'indomaret' => PaymentChannel::CstoreIndomaret,
            default => null,
        };
    }
}
