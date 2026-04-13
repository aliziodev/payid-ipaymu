<?php

namespace Aliziodev\PayIdIpaymu;

use Aliziodev\PayId\Contracts\DriverInterface;
use Aliziodev\PayId\Contracts\HasCapabilities;
use Aliziodev\PayId\Contracts\SupportsCharge;
use Aliziodev\PayId\Contracts\SupportsStatus;
use Aliziodev\PayId\Contracts\SupportsWebhookParsing;
use Aliziodev\PayId\Contracts\SupportsWebhookVerification;
use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\DTO\ChargeResponse;
use Aliziodev\PayId\DTO\NormalizedWebhook;
use Aliziodev\PayId\DTO\StatusResponse;
use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayIdIpaymu\Http\IpaymuClient;
use Aliziodev\PayIdIpaymu\Webhooks\IpaymuSignatureVerifier;
use Aliziodev\PayIdIpaymu\Webhooks\IpaymuWebhookParser;
use Illuminate\Http\Request;

class IpaymuDriver implements DriverInterface, SupportsCharge, SupportsStatus, SupportsWebhookParsing, SupportsWebhookVerification
{
    use HasCapabilities;

    public function __construct(
        protected readonly IpaymuConfig $config,
        protected readonly IpaymuClient $client,
        protected readonly IpaymuSignatureVerifier $signatureVerifier,
        protected readonly IpaymuWebhookParser $webhookParser,
    ) {}

    public function getName(): string
    {
        return 'ipaymu';
    }

    public function getCapabilities(): array
    {
        return [
            Capability::Charge,
            Capability::Status,
            Capability::WebhookVerification,
            Capability::WebhookParsing,
        ];
    }

    public function charge(ChargeRequest $request): ChargeResponse
    {
        $payload = [
            'referenceId' => $request->merchantOrderId,
            'amount' => $request->amount,
            'product' => $this->mapProducts($request),
            'qty' => $this->mapQty($request),
            'price' => $this->mapPrice($request),
            'buyerName' => $request->customer->name,
            'buyerEmail' => $request->customer->email,
            'buyerPhone' => $request->customer->phone,
            'description' => $request->description ?: 'PayID '.$request->merchantOrderId,
            'returnUrl' => $request->successUrl,
            'cancelUrl' => $request->failureUrl,
            'notifyUrl' => $request->callbackUrl,
            'paymentMethod' => $this->mapChannel($request->channel),
        ];

        $raw = $this->client->createPayment($payload);

        return ChargeResponse::make([
            'provider_name' => 'ipaymu',
            'provider_transaction_id' => (string) data_get(
                $raw,
                'Data.TransactionId',
                data_get($raw, 'Data.SessionID', ''),
            ),
            'merchant_order_id' => (string) data_get($raw, 'Data.ReferenceId', $request->merchantOrderId),
            'status' => $this->mapStatus((string) data_get($raw, 'Status', 'pending')),
            'payment_url' => data_get($raw, 'Data.Url'),
            'raw_response' => $raw,
        ]);
    }

    public function status(string $merchantOrderId): StatusResponse
    {
        $raw = $this->client->checkTransaction([
            'referenceId' => $merchantOrderId,
        ]);

        return StatusResponse::make([
            'provider_name' => 'ipaymu',
            'provider_transaction_id' => (string) data_get(
                $raw,
                'Data.TransactionId',
                data_get($raw, 'Data.SessionID', ''),
            ),
            'merchant_order_id' => (string) data_get($raw, 'Data.ReferenceId', $merchantOrderId),
            'status' => $this->mapStatus((string) data_get($raw, 'Data.Status', data_get($raw, 'Status', 'pending'))),
            'raw_response' => $raw,
            'amount' => (int) data_get($raw, 'Data.Amount', 0),
            'currency' => (string) data_get($raw, 'Data.Currency', 'IDR'),
        ]);
    }

    public function verifyWebhook(Request $request): bool
    {
        return $this->signatureVerifier->verify($request);
    }

    public function parseWebhook(Request $request): NormalizedWebhook
    {
        $verified = $this->verifyWebhook($request);

        return $this->webhookParser->parse($request, $verified);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkBalance(array $payload = []): array
    {
        return $this->client->checkBalance($payload);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function directPayment(array $payload): array
    {
        return $this->client->createDirectPayment($payload);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * Redirect Payment (iPaymu Payment Page).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redirectPayment(array $payload): array
    {
        return $this->client->redirectPayment($payload);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function paymentChannels(array $payload = []): array
    {
        return $this->client->paymentChannels($payload);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * List Payment Channels.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function listPaymentChannels(array $payload = []): array
    {
        return $this->client->listPaymentChannels($payload);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function historyTransaction(array $payload = []): array
    {
        return $this->client->historyTransaction($payload);
    }

    /**
     * Driver-specific extension API (outside PayID manager contract).
     *
     * Callback params helper for success, pending, and expired transaction callbacks.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function callbackParams(array $payload): array
    {
        return $this->webhookParser->callbackParams($payload);
    }

    protected function mapStatus(string $status): PaymentStatus
    {
        return match (strtolower($status)) {
            'success', 'berhasil', 'paid', 'settled', 'settlement' => PaymentStatus::Paid,
            'pending', 'process', 'processing' => PaymentStatus::Pending,
            'expired', 'kadaluarsa' => PaymentStatus::Expired,
            'failed', 'gagal', 'deny' => PaymentStatus::Failed,
            'cancelled', 'canceled', 'batal' => PaymentStatus::Cancelled,
            'refund', 'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Created,
        };
    }

    protected function mapChannel(PaymentChannel $channel): string
    {
        return match ($channel) {
            PaymentChannel::Qris => 'qris',
            PaymentChannel::Gopay => 'gopay',
            PaymentChannel::Ovo => 'ovo',
            PaymentChannel::Dana => 'dana',
            PaymentChannel::Shopeepay => 'shopeepay',
            PaymentChannel::CstoreAlfamart => 'alfamart',
            PaymentChannel::CstoreIndomaret => 'indomaret',
            default => 'va',
        };
    }

    /**
     * @return list<string>
     */
    protected function mapProducts(ChargeRequest $request): array
    {
        if ($request->items !== []) {
            return array_map(static fn ($item) => $item->name, $request->items);
        }

        return [$request->description ?: $request->merchantOrderId];
    }

    /**
     * @return list<int>
     */
    protected function mapQty(ChargeRequest $request): array
    {
        if ($request->items !== []) {
            return array_map(static fn ($item) => $item->quantity, $request->items);
        }

        return [1];
    }

    /**
     * @return list<int>
     */
    protected function mapPrice(ChargeRequest $request): array
    {
        if ($request->items !== []) {
            return array_map(static fn ($item) => $item->price, $request->items);
        }

        return [$request->amount];
    }
}
