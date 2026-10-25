<?php

namespace Omnibus\RoyalMail;

use Omnibus\Config;
use Omnibus\GatewayFactory;
use Omnibus\RoyalMail\Action\ShippingAction;
use Omnibus\RoyalMail\Action\TrackingAction;
use Symfony\Component\HttpClient\HttpClient;

/**
 *   options:
 *     api_key: '%env(ROYAL_MAIL_API_KEY)%'          # Click & Drop: Settings > Integrations > API
 *     tracking_client_id: null                      # optional: the Tracking API (developer.royalmail.net)
 *     tracking_client_secret: null
 *     rates: [...]                                  # prices from configuration: Click & Drop quotes none
 *
 * No pickup points: Royal Mail's are its post offices, not an API.
 */
final class RoyalMailGatewayFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibus.factory_name' => 'royal_mail',
            'omnibus.factory_title' => 'Royal Mail',
            'omnibus.required_options' => ['api_key'],
            'tracking_client_id' => null,
            'tracking_client_secret' => null,
            'omnibus.api' => function (Config $c) {
                $http = $this->http ?? HttpClient::create();

                return new Api($http, (string) $c['api_key'], $c['tracking_client_id'] ?: null, $c['tracking_client_secret'] ?: null);
            },
            'omnibus.action.shipping' => new ShippingAction(),
            'omnibus.action.tracking' => static fn (Config $c) => $c['tracking_client_id'] && $c['tracking_client_secret'] ? new TrackingAction() : null, // the Tracking API has credentials of its own
        ]);
    }
}
