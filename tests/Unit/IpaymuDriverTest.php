<?php

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\Capability;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayIdIpaymu\Http\IpaymuClient;
use Aliziodev\PayIdIpaymu\IpaymuConfig;
use Aliziodev\PayIdIpaymu\IpaymuDriver;
use Aliziodev\PayIdIpaymu\Webhooks\IpaymuSignatureVerifier;
use Aliziodev\PayIdIpaymu\Webhooks\IpaymuWebhookParser;
use Illuminate\Http\Request;

it('driver capabilities are exposed', function () {
    $driver = new IpaymuDriver(
        config: IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ]),
        client: Mockery::mock(IpaymuClient::class),
        signatureVerifier: new IpaymuSignatureVerifier(IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ])),
        webhookParser: new IpaymuWebhookParser,
    );

    expect($driver->supports(Capability::Charge))->toBeTrue()
        ->and($driver->supports(Capability::Status))->toBeTrue()
        ->and($driver->supports(Capability::WebhookVerification))->toBeTrue()
        ->and($driver->supports(Capability::WebhookParsing))->toBeTrue();
});

it('driver charge maps payload to charge response', function () {
    $client = Mockery::mock(IpaymuClient::class);
    $client->shouldReceive('createPayment')
        ->once()
        ->andReturn([
            'Status' => 'Success',
            'Data' => [
                'TransactionId' => 'TRX-1001',
                'ReferenceId' => 'ORDER-1001',
                'Url' => 'https://sandbox.ipaymu.com/payment/TRX-1001',
            ],
        ]);

    $driver = new IpaymuDriver(
        config: IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ]),
        client: $client,
        signatureVerifier: new IpaymuSignatureVerifier(IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ])),
        webhookParser: new IpaymuWebhookParser,
    );

    $response = $driver->charge(ChargeRequest::make([
        'merchant_order_id' => 'ORDER-1001',
        'amount' => 150000,
        'currency' => 'IDR',
        'channel' => PaymentChannel::Qris,
        'customer' => [
            'name' => 'Budi',
            'email' => 'budi@example.com',
        ],
        'description' => 'Order test',
    ]));

    expect($response->providerName)->toBe('ipaymu')
        ->and($response->providerTransactionId)->toBe('TRX-1001')
        ->and($response->merchantOrderId)->toBe('ORDER-1001')
        ->and($response->status)->toBe(PaymentStatus::Paid)
        ->and($response->paymentUrl)->toContain('/payment/TRX-1001');
});

it('driver status maps check transaction response', function () {
    $client = Mockery::mock(IpaymuClient::class);
    $client->shouldReceive('checkTransaction')
        ->once()
        ->andReturn([
            'Status' => 'Success',
            'Data' => [
                'TransactionId' => 'TRX-STATUS-001',
                'ReferenceId' => 'ORDER-STATUS-001',
                'Status' => 'pending',
                'Amount' => 70000,
                'Currency' => 'IDR',
            ],
        ]);

    $driver = new IpaymuDriver(
        config: IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ]),
        client: $client,
        signatureVerifier: new IpaymuSignatureVerifier(IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ])),
        webhookParser: new IpaymuWebhookParser,
    );

    $response = $driver->status('ORDER-STATUS-001');

    expect($response->providerTransactionId)->toBe('TRX-STATUS-001')
        ->and($response->merchantOrderId)->toBe('ORDER-STATUS-001')
        ->and($response->status)->toBe(PaymentStatus::Pending)
        ->and($response->amount)->toBe(70000);
});

it('driver extension methods return raw data', function () {
    $client = Mockery::mock(IpaymuClient::class);
    $client->shouldReceive('createDirectPayment')->once()->andReturn(['Status' => 'Success', 'Data' => ['TransactionId' => 'TRX-DIRECT-1']]);
    $client->shouldReceive('redirectPayment')->once()->andReturn(['Status' => 'Success', 'Data' => ['Url' => 'https://sandbox.ipaymu.com/payment/REDIRECT-1']]);
    $client->shouldReceive('paymentChannels')->once()->andReturn(['Status' => 'Success', 'Data' => [['Code' => 'qris']]]);
    $client->shouldReceive('listPaymentChannels')->once()->andReturn(['Status' => 'Success', 'Data' => [['Code' => 'va']]]);
    $client->shouldReceive('checkBalance')->once()->andReturn(['Status' => 'Success', 'Data' => ['Balance' => 100000]]);
    $client->shouldReceive('historyTransaction')->once()->andReturn(['Status' => 'Success', 'Data' => [['ReferenceId' => 'ORDER-1']]]);

    $driver = new IpaymuDriver(
        config: IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ]),
        client: $client,
        signatureVerifier: new IpaymuSignatureVerifier(IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ])),
        webhookParser: new IpaymuWebhookParser,
    );

    $direct = $driver->directPayment([
        'amount' => 50000,
        'buyerName' => 'Budi',
    ]);
    $redirect = $driver->redirectPayment([
        'referenceId' => 'ORDER-REDIRECT-1',
        'amount' => 50000,
    ]);
    $channels = $driver->paymentChannels();
    $channelsList = $driver->listPaymentChannels();
    $balance = $driver->checkBalance();
    $history = $driver->historyTransaction(['limit' => 10]);

    expect(data_get($direct, 'Data.TransactionId'))->toBe('TRX-DIRECT-1')
        ->and(data_get($redirect, 'Data.Url'))->toContain('/payment/REDIRECT-1')
        ->and(data_get($channels, 'Data.0.Code'))->toBe('qris')
        ->and(data_get($channelsList, 'Data.0.Code'))->toBe('va')
        ->and(data_get($balance, 'Data.Balance'))->toBe(100000)
        ->and(data_get($history, 'Data.0.ReferenceId'))->toBe('ORDER-1');
});

it('driver callback params helper maps success pending expired statuses', function () {
    $driver = new IpaymuDriver(
        config: IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ]),
        client: Mockery::mock(IpaymuClient::class),
        signatureVerifier: new IpaymuSignatureVerifier(IpaymuConfig::fromArray([
            'va' => '0000000000000000',
            'api_key' => 'test-key',
        ])),
        webhookParser: new IpaymuWebhookParser,
    );

    $success = $driver->callbackParams([
        'referenceId' => 'ORDER-CB-SUCCESS',
        'transactionId' => 'TRX-CB-SUCCESS',
        'status' => 'success',
        'amount' => 10000,
        'currency' => 'IDR',
    ]);

    $pending = $driver->callbackParams([
        'referenceId' => 'ORDER-CB-PENDING',
        'transactionId' => 'TRX-CB-PENDING',
        'status' => 'pending',
    ]);

    $expired = $driver->callbackParams([
        'referenceId' => 'ORDER-CB-EXPIRED',
        'transactionId' => 'TRX-CB-EXPIRED',
        'status' => 'expired',
    ]);

    expect($success['status'])->toBe('paid')
        ->and($pending['status'])->toBe('pending')
        ->and($expired['status'])->toBe('expired');
});

it('driver webhook parse returns normalized webhook', function () {
    $config = IpaymuConfig::fromArray([
        'va' => '0000000000000000',
        'api_key' => 'test-key',
        'webhook_verification_enabled' => false,
    ]);

    $driver = new IpaymuDriver(
        config: $config,
        client: Mockery::mock(IpaymuClient::class),
        signatureVerifier: new IpaymuSignatureVerifier($config),
        webhookParser: new IpaymuWebhookParser,
    );

    $request = Request::create('/payid/webhook/ipaymu', 'POST', [
        'referenceId' => 'ORDER-WEBHOOK-001',
        'transactionId' => 'TRX-WEBHOOK-001',
        'status' => 'paid',
        'amount' => 50000,
        'currency' => 'IDR',
    ]);

    $webhook = $driver->parseWebhook($request);

    expect($webhook->merchantOrderId)->toBe('ORDER-WEBHOOK-001')
        ->and($webhook->providerTransactionId)->toBe('TRX-WEBHOOK-001')
        ->and($webhook->status)->toBe(PaymentStatus::Paid)
        ->and($webhook->signatureValid)->toBeTrue();
});
