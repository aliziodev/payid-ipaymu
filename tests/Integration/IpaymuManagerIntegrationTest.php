<?php

use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Aliziodev\PayId\Enums\PaymentStatus;
use Aliziodev\PayId\Managers\PayIdManager;
use Illuminate\Support\Facades\Http;

it('manager can resolve ipaymu driver and perform charge', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/api/v2/payment' => Http::response([
            'Status' => 'Success',
            'Data' => [
                'TransactionId' => 'TRX-MANAGER-001',
                'ReferenceId' => 'ORDER-MANAGER-001',
                'Url' => 'https://sandbox.ipaymu.com/payment/TRX-MANAGER-001',
            ],
        ], 200),
    ]);

    /** @var PayIdManager $manager */
    $manager = app(PayIdManager::class);

    $response = $manager->driver('ipaymu')->charge(ChargeRequest::make([
        'merchant_order_id' => 'ORDER-MANAGER-001',
        'amount' => 120000,
        'currency' => 'IDR',
        'channel' => PaymentChannel::Qris,
        'customer' => [
            'name' => 'Budi',
            'email' => 'budi@example.com',
        ],
    ]));

    expect($response->providerName)->toBe('ipaymu')
        ->and($response->merchantOrderId)->toBe('ORDER-MANAGER-001')
        ->and($response->status)->toBe(PaymentStatus::Paid);
});

it('manager can perform status check through ipaymu driver', function () {
    Http::fake([
        'https://sandbox.ipaymu.com/api/v2/transaction' => Http::response([
            'Status' => 'Success',
            'Data' => [
                'TransactionId' => 'TRX-MANAGER-STATUS-001',
                'ReferenceId' => 'ORDER-MANAGER-STATUS-001',
                'Status' => 'pending',
                'Amount' => 120000,
                'Currency' => 'IDR',
            ],
        ], 200),
    ]);

    /** @var PayIdManager $manager */
    $manager = app(PayIdManager::class);
    $status = $manager->driver('ipaymu')->status('ORDER-MANAGER-STATUS-001');

    expect($status->providerName)->toBe('ipaymu')
        ->and($status->merchantOrderId)->toBe('ORDER-MANAGER-STATUS-001')
        ->and($status->status)->toBe(PaymentStatus::Pending)
        ->and($status->amount)->toBe(120000);
});
