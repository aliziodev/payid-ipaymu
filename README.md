# PayID iPaymu Driver

Driver iPaymu untuk aliziodev/payid.

Package ini menyediakan integrasi iPaymu dengan API PayID untuk flow:
- Redirect Payment (iPaymu Payment Page) via `charge(...)` atau `redirectPayment(...)`
- Direct Payment
- List Payment Channels
- Check Transaction
- Check Balance
- History Transaction
- Callback Params helper (success, pending, expired)
- webhook verification plus parsing

## Requirements

- PHP 8.2+
- Laravel 11 or 12 or 13
- aliziodev/payid ^0.1

## Instalasi

```bash
composer require aliziodev/payid
composer require aliziodev/payid-ipaymu
```

## Konfigurasi

Contoh konfigurasi driver pada config/payid.php:

```php
'ipaymu' => [
    'driver' => 'ipaymu',
    'environment' => env('IPAYMU_ENV', 'sandbox'),
    'va' => env('IPAYMU_VA'),
    'api_key' => env('IPAYMU_API_KEY'),
    'timeout' => 30,
    'webhook_verification_enabled' => false,
    'webhook_token' => env('IPAYMU_WEBHOOK_TOKEN'),
    'webhook_signature_key' => env('IPAYMU_WEBHOOK_SIGNATURE_KEY'),
    'direct_payment_path' => '/api/v2/payment/direct',
    'payment_channel_path' => '/api/v2/payment-channel',
],
```

## Penggunaan

```php
use Aliziodev\PayId\DTO\ChargeRequest;
use Aliziodev\PayId\Enums\PaymentChannel;
use Illuminate\Support\Facades\PayId;

$response = PayId::driver('ipaymu')->charge(ChargeRequest::make([
    'merchant_order_id' => 'ORDER-2001',
    'amount' => 150000,
    'currency' => 'IDR',
    'channel' => PaymentChannel::Qris,
    'customer' => [
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'phone' => '08123456789',
    ],
]));
```

Driver-specific extensions:

```php
/** @var \Aliziodev\PayIdIpaymu\IpaymuDriver $driver */
$driver = PayId::driver('ipaymu')->getDriver();

$directPayment = $driver->directPayment([
    'amount' => 50000,
    'buyerName' => 'Budi',
]);

$redirectPayment = $driver->redirectPayment([
    'referenceId' => 'ORDER-2001',
    'amount' => 150000,
]);

$channels = $driver->paymentChannels();
$channelsList = $driver->listPaymentChannels();
$balance = $driver->checkBalance();
$history = $driver->historyTransaction([
    'limit' => 20,
]);

$callbackParams = $driver->callbackParams([
    'referenceId' => 'ORDER-2001',
    'transactionId' => 'TRX-2001',
    'status' => 'pending',
]);
```

## Lisensi

MIT
