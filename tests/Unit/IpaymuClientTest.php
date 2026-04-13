<?php

use Aliziodev\PayId\Exceptions\ProviderApiException;
use Aliziodev\PayIdIpaymu\Http\IpaymuClient;
use Aliziodev\PayIdIpaymu\IpaymuConfig;
use Illuminate\Support\Facades\Http;

it('client sends signed request and returns json array', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/*' => Http::response([
            'Status' => 'Success',
            'Data' => [
                'ReferenceId' => 'ORDER-HTTP-001',
            ],
        ], 200),
    ]);

    $client = new IpaymuClient(IpaymuConfig::fromArray([
        'environment' => 'sandbox',
        'va' => '0000000000000000',
        'api_key' => 'test-key',
    ]));

    $result = $client->createPayment([
        'referenceId' => 'ORDER-HTTP-001',
    ]);

    expect(data_get($result, 'Data.ReferenceId'))->toBe('ORDER-HTTP-001');

    Http::assertSent(function ($request) {
        return $request->hasHeader('va')
            && $request->hasHeader('signature')
            && $request->hasHeader('timestamp');
    });
});

it('client throws provider api exception on failed response', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/*' => Http::response([
            'Status' => 'Error',
            'Message' => 'Bad request',
        ], 400),
    ]);

    $client = new IpaymuClient(IpaymuConfig::fromArray([
        'environment' => 'sandbox',
        'va' => '0000000000000000',
        'api_key' => 'test-key',
    ]));

    $client->createPayment([
        'referenceId' => 'ORDER-FAILED-001',
    ]);
})->throws(ProviderApiException::class);

it('client supports direct payment endpoint', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/api/v2/payment/direct' => Http::response([
            'Status' => 'Success',
            'Data' => [
                'TransactionId' => 'TRX-DIRECT-HTTP-001',
            ],
        ], 200),
    ]);

    $client = new IpaymuClient(IpaymuConfig::fromArray([
        'environment' => 'sandbox',
        'va' => '0000000000000000',
        'api_key' => 'test-key',
    ]));

    $result = $client->createDirectPayment([
        'amount' => 50000,
    ]);

    expect(data_get($result, 'Data.TransactionId'))->toBe('TRX-DIRECT-HTTP-001');
});

it('client supports payment channels endpoint', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/api/v2/payment-channel' => Http::response([
            'Status' => 'Success',
            'Data' => [
                ['Code' => 'qris'],
                ['Code' => 'va'],
            ],
        ], 200),
    ]);

    $client = new IpaymuClient(IpaymuConfig::fromArray([
        'environment' => 'sandbox',
        'va' => '0000000000000000',
        'api_key' => 'test-key',
    ]));

    $result = $client->paymentChannels();

    expect(data_get($result, 'Data.0.Code'))->toBe('qris')
        ->and(data_get($result, 'Data.1.Code'))->toBe('va');
});

it('client redirect payment alias uses payment endpoint', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/api/v2/payment' => Http::response([
            'Status' => 'Success',
            'Data' => [
                'Url' => 'https://sandbox.ipaymu.com/payment/REDIRECT-HTTP-001',
            ],
        ], 200),
    ]);

    $client = new IpaymuClient(IpaymuConfig::fromArray([
        'environment' => 'sandbox',
        'va' => '0000000000000000',
        'api_key' => 'test-key',
    ]));

    $result = $client->redirectPayment([
        'referenceId' => 'ORDER-REDIRECT-HTTP-001',
    ]);

    expect(data_get($result, 'Data.Url'))->toContain('/payment/REDIRECT-HTTP-001');
});

it('client list payment channels alias uses payment channel endpoint', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/api/v2/payment-channel' => Http::response([
            'Status' => 'Success',
            'Data' => [
                ['Code' => 'qris'],
            ],
        ], 200),
    ]);

    $client = new IpaymuClient(IpaymuConfig::fromArray([
        'environment' => 'sandbox',
        'va' => '0000000000000000',
        'api_key' => 'test-key',
    ]));

    $result = $client->listPaymentChannels();

    expect(data_get($result, 'Data.0.Code'))->toBe('qris');
});
