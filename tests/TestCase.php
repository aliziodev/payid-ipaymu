<?php

namespace Aliziodev\PayIdIpaymu\Tests;

use Aliziodev\PayId\PayIdServiceProvider;
use Aliziodev\PayIdIpaymu\IpaymuServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PayIdServiceProvider::class,
            IpaymuServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('payid.default', 'ipaymu');
        $app['config']->set('payid.drivers.ipaymu', [
            'driver' => 'ipaymu',
            'environment' => 'sandbox',
            'va' => '0000000000000000',
            'api_key' => 'test-api-key',
            'webhook_verification_enabled' => false,
            'webhook_token' => 'ipaymu-webhook-token',
            'webhook_signature_key' => 'ipaymu-webhook-signature',
        ]);
    }
}
