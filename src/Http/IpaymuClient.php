<?php

namespace Aliziodev\PayIdIpaymu\Http;

use Aliziodev\PayId\Exceptions\ProviderApiException;
use Aliziodev\PayId\Exceptions\ProviderNetworkException;
use Aliziodev\PayIdIpaymu\IpaymuConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class IpaymuClient
{
    public function __construct(
        protected readonly IpaymuConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('POST', $this->config->paymentPath, $payload);
    }

    /**
     * Redirect Payment (iPaymu Payment Page).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redirectPayment(array $payload): array
    {
        return $this->createPayment($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createDirectPayment(array $payload): array
    {
        return $this->request('POST', $this->config->directPaymentPath, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function paymentChannels(array $payload = []): array
    {
        return $this->request('POST', $this->config->paymentChannelPath, $payload);
    }

    /**
     * List Payment Channels.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function listPaymentChannels(array $payload = []): array
    {
        return $this->paymentChannels($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkTransaction(array $payload): array
    {
        return $this->request('POST', $this->config->transactionPath, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function checkBalance(array $payload = []): array
    {
        return $this->request('POST', $this->config->balancePath, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function historyTransaction(array $payload = []): array
    {
        return $this->request('POST', $this->config->historyPath, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, array $payload = []): array
    {
        $timestamp = now()->toIso8601String();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if (! is_string($body)) {
            $body = '{}';
        }

        $signature = hash(
            'sha256',
            strtoupper($method).':'.$this->config->va.':'.$this->config->apiKey.':'.$body,
        );

        $url = $this->config->baseUrl.'/'.ltrim($path, '/');

        try {
            $response = Http::timeout($this->config->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'va' => $this->config->va,
                    'signature' => $signature,
                    'timestamp' => $timestamp,
                ])
                ->withBody($body, 'application/json')
                ->send(strtoupper($method), $url);
        } catch (ConnectionException $e) {
            throw new ProviderNetworkException('ipaymu', $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw new ProviderApiException('ipaymu', $e->getMessage(), 0, [], $e);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new ProviderApiException(
                driver: 'ipaymu',
                message: 'Invalid JSON response from iPaymu API.',
                httpStatus: $response->status(),
            );
        }

        if (! $response->successful()) {
            throw new ProviderApiException(
                driver: 'ipaymu',
                message: (string) data_get($json, 'Message', 'iPaymu API request failed.'),
                httpStatus: $response->status(),
                rawResponse: $json,
            );
        }

        return $json;
    }
}
