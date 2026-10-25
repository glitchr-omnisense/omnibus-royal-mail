<?php

namespace Omnibus\RoyalMail\Tests;

use Omnibus\Exception\CarrierException;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Model\TrackingStatus;
use Omnibus\Request\Pickup;
use Omnibus\RoyalMail\RoyalMailGatewayFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RoyalMailGatewayTest extends TestCase
{
    private array $calls = [];

    private function gateway(bool $tracking = true): \Omnibus\GatewayInterface
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $path = (string) parse_url($url, \PHP_URL_PATH);
            $this->calls[] = [$method, $url, \is_string($options['body'] ?? null) && str_starts_with($options['body'], '{') ? json_decode($options['body'], true) : [], $options['headers']];

            return match (true) {
                'POST' === $method && str_ends_with($path, '/api/v1/orders') => new MockResponse(json_encode(['successCount' => 1, 'createdOrders' => [['orderIdentifier' => 1042, 'orderReference' => 'ORDER-1042', 'trackingNumber' => 'KL123456789GB']], 'failedOrders' => []])),
                str_ends_with($path, '/orders/1042/label') => new MockResponse('%PDF-1.4 rm', ['response_headers' => ['content-type' => 'application/pdf']]),
                str_contains($url, 'api.royalmail.net') => new MockResponse(json_encode(['mailPieces' => ['mailPieceId' => 'KL123456789GB', 'summary' => ['lastEventCode' => 'EVKSP', 'statusDescription' => 'Delivered'], 'events' => [
                    ['eventCode' => 'EVKSP', 'eventName' => 'Delivered by', 'eventDateTime' => '2026-10-02T11:02:00+01:00', 'locationName' => 'Manchester DO'],
                    ['eventCode' => 'EVNMT', 'eventName' => 'Item received', 'eventDateTime' => '2026-10-01T09:30:00+01:00', 'locationName' => 'London'],
                ]]])),
                default => new MockResponse(json_encode(['message' => 'No such resource '.$path]), ['http_code' => 404]),
            };
        });

        return (new RoyalMailGatewayFactory($http))->create(['api_key' => 'cd-key'] + ($tracking ? ['tracking_client_id' => 'tc', 'tracking_client_secret' => 'ts'] : []));
    }

    private static function shipment(): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['1 Rue du Test'], '67000', 'Strasbourg', 'FR'), new Address('Alex Martin', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'alex@example.org'), [new Parcel(850, 30, 20, 10, 2500, 'GBP')], 'TPN01', reference: 'ORDER-1042');
    }

    public function testAnOrderIsCreatedInClickAndDropWithItsLabel(): void
    {
        $label = $this->gateway()->ship(self::shipment());
        self::assertSame('KL123456789GB', $label->trackingNumber);
        self::assertSame('%PDF-1.4 rm', $label->content);
        $item = $this->calls[0][2]['items'][0];
        self::assertSame('ORDER-1042', $item['orderReference']);
        self::assertSame('SW1A 2AA', $item['recipient']['address']['postcode']);
        self::assertSame(850, $item['packages'][0]['weightInGrams']);
        self::assertSame('TPN01', $item['postageDetails']['serviceCode']);
        self::assertSame('GBP', $item['currencyCode']);
        self::assertContains('Authorization: Bearer cd-key', $this->calls[0][3]);
    }

    public function testTrackingUsesTheTrackingApisOwnCredentials(): void
    {
        $tracking = $this->gateway()->track('KL123456789GB');
        self::assertSame(TrackingStatus::DELIVERED, $tracking->status);
        self::assertSame('Item received', $tracking->events[0]->description);
        self::assertContains('X-IBM-Client-Id: tc', $this->calls[0][3]);

        $this->expectException(\Omnibus\Exception\RequestNotSupportedException::class); // without its option, not offered
        $this->gateway(false)->track('KL123456789GB');
    }

    public function testNoPickupPoints(): void
    {
        self::assertFalse($this->gateway()->supports(Pickup::class));
    }
}
