<?php

namespace Omnibus\RoyalMail;

use Omnibus\Exception\CarrierException;
use Omnibus\Http\Redaction;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Royal Mail's Click & Drop API (a bearer API key) for orders and labels, and
 * the Tracking API (its own client id and secret, api.royalmail.net) for
 * where a parcel is.
 */
final class Api
{
    public const CLICK_AND_DROP = 'https://api.parcel.royalmail.com/api/v1';
    public const TRACKING = 'https://api.royalmail.net/mailpieces/v2';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $apiKey,
        private readonly ?string $trackingClientId = null,
        private readonly ?string $trackingClientSecret = null,
        private readonly int $timeout = 20,
    ) {
    }

    /** @return array<mixed> JSON, or ['content' => binary] for a PDF */
    public function call(string $method, string $path, ?array $body = null, array $query = []): array
    {
        try {
            $response = $this->http->request($method, self::CLICK_AND_DROP.$path, [
                'headers' => ['Authorization' => 'Bearer '.$this->apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json, application/pdf'],
                'query' => $query,
                'body' => null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR),
                'timeout' => $this->timeout,
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
            $type = $response->getHeaders(false)['content-type'][0] ?? '';
        } catch (HttpExceptionInterface|\JsonException $e) {
            $secrets = $this->secrets();

            throw Redaction::withoutArguments(new CarrierException('royal_mail', 'Royal Mail request failed: '.Redaction::text($e->getMessage(), $secrets), null, Redaction::copy($e, $secrets)));
        }
        if ($status < 400 && str_contains($type, 'pdf')) {
            return ['content' => $content];
        }
        $data = '' === $content ? [] : json_decode($content, true);
        if (!\is_array($data)) {
            throw Redaction::withoutArguments(new CarrierException('royal_mail', sprintf('Royal Mail answered HTTP %d with a body that is not JSON.', $status)));
        }
        if ($status >= 400) {
            throw Redaction::withoutArguments(new CarrierException('royal_mail', (string) ($data['message'] ?? $data['errors'][0]['errorMessage'] ?? $data['title'] ?? sprintf('HTTP %d', $status)), isset($data['code']) ? (string) $data['code'] : null));
        }

        return $data;
    }

    public function canTrack(): bool
    {
        return $this->trackingClientId && $this->trackingClientSecret;
    }

    /** @return array<string, mixed> */
    public function track(string $number): array
    {
        if (!$this->canTrack()) {
            throw Redaction::withoutArguments(new CarrierException('royal_mail', 'Tracking needs the Tracking API\'s credentials (options tracking_client_id and tracking_client_secret).'));
        }
        try {
            $response = $this->http->request('GET', self::TRACKING.'/'.rawurlencode($number).'/events', ['headers' => ['X-IBM-Client-Id' => $this->trackingClientId, 'X-IBM-Client-Secret' => $this->trackingClientSecret, 'Accept' => 'application/json', 'X-Accept-RMG-Terms' => 'yes'], 'timeout' => $this->timeout]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $e) {
            $secrets = $this->secrets();

            throw Redaction::withoutArguments(new CarrierException('royal_mail', 'Royal Mail tracking failed: '.Redaction::text($e->getMessage(), $secrets), null, Redaction::copy($e, $secrets)));
        }
        if ($status >= 400) {
            throw Redaction::withoutArguments(new CarrierException('royal_mail', (string) ($data['errors'][0]['errorDescription'] ?? $data['moreInformation'] ?? sprintf('HTTP %d', $status)), $data['errors'][0]['errorCode'] ?? $data['httpCode'] ?? null));
        }

        return $data;
    }

    /**
     * What a failure must not carry: the credentials, and what a request made of them.
     *
     * @return list<string>
     */
    private function secrets(string ...$more): array
    {
        return [$this->apiKey, (string) $this->trackingClientSecret, ...$more];
    }
}
