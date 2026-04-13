<?php

namespace Aliziodev\PayIdIpaymu\Webhooks;

use Aliziodev\PayIdIpaymu\IpaymuConfig;
use Illuminate\Http\Request;

final class IpaymuSignatureVerifier
{
    public function __construct(
        protected readonly IpaymuConfig $config,
    ) {}

    public function verify(Request $request): bool
    {
        if (! $this->config->webhookVerificationEnabled) {
            return true;
        }

        $token = trim((string) $this->config->webhookToken);
        $incomingToken = (string) ($request->header('x-callback-token')
            ?? $request->header('X-CALLBACK-TOKEN')
            ?? '');

        if ($token !== '' && $incomingToken !== '') {
            return hash_equals($token, $incomingToken);
        }

        $secret = trim((string) $this->config->webhookSignatureKey);
        $incomingSignature = (string) ($request->header('x-signature')
            ?? $request->header('X-SIGNATURE')
            ?? '');

        if ($secret !== '' && $incomingSignature !== '') {
            $expected = hash_hmac('sha256', (string) $request->getContent(), $secret);

            return hash_equals($expected, $incomingSignature);
        }

        return false;
    }
}
