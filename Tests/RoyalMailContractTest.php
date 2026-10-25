<?php

namespace Omnibus\RoyalMail\Tests;

use Omnibus\GatewayFactoryInterface;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Request\Tracking;
use Omnibus\RoyalMail\RoyalMailGatewayFactory;
use Omnibus\Testing\GatewayContractTests;
use Omnibus\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on Royal Mail's recorded answers (Click & Drop, the Tracking API). */
final class RoyalMailContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new RoyalMailGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['api_key' => 'cd-key', 'tracking_client_id' => 'tc', 'tracking_client_secret' => 'ts'];
    }

    protected function operationOptions(): array
    {
        return ['tracking_client_id' => [Tracking::class], 'tracking_client_secret' => [Tracking::class]];
    }

    protected function contractShipment(): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['1 Rue du Test'], '67000', 'Strasbourg', 'FR'), new Address('Alex Martin', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'alex@example.org'), [new Parcel(850, 30, 20, 10, 2500, 'GBP')], 'TPN01', reference: 'ORDER-1042');
    }

    protected function contractTrackingNumber(): string
    {
        return 'KL123456789GB';
    }

    protected function recordedAnswers(): array
    {
        return [
            '/orders/1042/label' => RecordedHttpClient::answer('%PDF-1.4 rm', 200, ['content-type' => 'application/pdf']),
            'POST /api/v1/orders' => ['successCount' => 1, 'createdOrders' => [['orderIdentifier' => 1042, 'orderReference' => 'ORDER-1042', 'trackingNumber' => 'KL123456789GB']], 'failedOrders' => []],
            'api.royalmail.net' => ['mailPieces' => ['mailPieceId' => 'KL123456789GB', 'summary' => ['lastEventCode' => 'EVKSP', 'statusDescription' => 'Delivered'], 'events' => [
                ['eventCode' => 'EVKSP', 'eventName' => 'Delivered by', 'eventDateTime' => '2026-10-02T11:02:00+01:00', 'locationName' => 'Manchester DO'],
                ['eventCode' => 'EVNMT', 'eventName' => 'Item received', 'eventDateTime' => '2026-10-01T09:30:00+01:00', 'locationName' => 'London'],
            ]]],
        ];
    }
}
