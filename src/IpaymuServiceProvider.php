<?php

namespace Aliziodev\PayIdIpaymu;

use Aliziodev\PayId\Factories\DriverFactory;
use Aliziodev\PayIdIpaymu\Http\IpaymuClient;
use Aliziodev\PayIdIpaymu\Webhooks\IpaymuSignatureVerifier;
use Aliziodev\PayIdIpaymu\Webhooks\IpaymuWebhookParser;
use Illuminate\Support\ServiceProvider;

class IpaymuServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->resolving(DriverFactory::class, function (DriverFactory $factory): void {
            $factory->extend('ipaymu', function (array $config): IpaymuDriver {
                $baseConfig = (array) config('payid.drivers.ipaymu', []);
                $ipaymuConfig = IpaymuConfig::fromArray(array_merge($baseConfig, $config));

                return new IpaymuDriver(
                    config: $ipaymuConfig,
                    client: new IpaymuClient($ipaymuConfig),
                    signatureVerifier: new IpaymuSignatureVerifier($ipaymuConfig),
                    webhookParser: new IpaymuWebhookParser,
                );
            });
        });
    }
}
