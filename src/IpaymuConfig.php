<?php

namespace Aliziodev\PayIdIpaymu;

use InvalidArgumentException;

final class IpaymuConfig
{
    public function __construct(
        public readonly string $environment,
        public readonly string $va,
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly int $timeout,
        public readonly bool $webhookVerificationEnabled,
        public readonly ?string $webhookToken,
        public readonly ?string $webhookSignatureKey,
        public readonly string $paymentPath,
        public readonly string $directPaymentPath,
        public readonly string $paymentChannelPath,
        public readonly string $transactionPath,
        public readonly string $balancePath,
        public readonly string $historyPath,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $va = (string) ($config['va'] ?? '');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($va === '') {
            throw new InvalidArgumentException('IPAYMU_VA is required.');
        }

        if ($apiKey === '') {
            throw new InvalidArgumentException('IPAYMU_API_KEY is required.');
        }

        $environment = (string) ($config['environment'] ?? 'sandbox');

        return new self(
            environment: $environment,
            va: $va,
            apiKey: $apiKey,
            baseUrl: rtrim(
                (string) ($config['base_url'] ?? self::resolveBaseUrl($environment)),
                '/',
            ),
            timeout: max(5, (int) ($config['timeout'] ?? 30)),
            webhookVerificationEnabled: (bool) ($config['webhook_verification_enabled'] ?? false),
            webhookToken: isset($config['webhook_token']) ? (string) $config['webhook_token'] : null,
            webhookSignatureKey: isset($config['webhook_signature_key']) ? (string) $config['webhook_signature_key'] : null,
            paymentPath: (string) ($config['payment_path'] ?? '/api/v2/payment'),
            directPaymentPath: (string) ($config['direct_payment_path'] ?? '/api/v2/payment/direct'),
            paymentChannelPath: (string) ($config['payment_channel_path'] ?? '/api/v2/payment-channel'),
            transactionPath: (string) ($config['transaction_path'] ?? '/api/v2/transaction'),
            balancePath: (string) ($config['balance_path'] ?? '/api/v2/balance'),
            historyPath: (string) ($config['history_path'] ?? '/api/v2/history'),
        );
    }

    private static function resolveBaseUrl(string $environment): string
    {
        return strtolower($environment) === 'production'
            ? 'https://my.ipaymu.com'
            : 'https://sandbox.ipaymu.com';
    }
}
