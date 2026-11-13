# omnibus/royal-mail

Royal Mail for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): orders with their
postage and labels through Click & Drop, tracking through the Tracking API. Prices come from
configuration (`rates`): Click & Drop quotes none.

## Install and a first call

```sh
composer require omnibus/royal-mail
```

No account for this first call: Royal Mail's answer is recorded
(`Omnibus\Testing\RecordedHttpClient` answers in place of the API), so nothing leaves the process.
With your own credentials, give the factory your HTTP client (or none: it makes its own) and drop
the recorded one.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\RoyalMail\RoyalMailGatewayFactory;
use Omnibus\Testing\RecordedHttpClient;

// Royal Mail's answer, recorded: no account, and nothing leaves the process.
$http = new RecordedHttpClient([
    'api.royalmail.net' => ['mailPieces' => ['mailPieceId' => 'KL123456789GB', 'summary' => ['lastEventCode' => 'EVKSP', 'statusDescription' => 'Delivered'], 'events' => [
        ['eventCode' => 'EVKSP', 'eventName' => 'Delivered by', 'eventDateTime' => '2026-10-02T11:02:00+01:00', 'locationName' => 'Manchester DO'],
    ]]],
]);
$gateway = (new RoyalMailGatewayFactory($http))->create(['api_key' => 'cd-key', 'tracking_client_id' => 'tc', 'tracking_client_secret' => 'ts']);

$tracking = $gateway->track('KL123456789GB');
echo $tracking->status->value, ' - ', $tracking->latest()?->description, "\n";   // delivered - Delivered by
```

A real call needs a Royal Mail account (the credentials below). The answer is shaped after Royal
Mail's published documentation; the package has not been run against its service yet.

## Configuration

```php
$gateway = (new RoyalMailGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `omnisense/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        royal_mail:
            factory: royal_mail
            options:
                api_key: '%env(ROYAL_MAIL_API_KEY)%'            # Click & Drop > Settings > Integrations > API
                tracking_client_id: '%env(RM_TRACKING_ID)%'     # optional: the Tracking API
                tracking_client_secret: '%env(RM_TRACKING_SECRET)%'
                rates: [...]
```

The service is a Click & Drop service code (TPN01 Tracked 24, TPS01 Tracked 48, TRN01 Tracked 24
with signature...; none: the account's default). Shipment option: `package_format` (parcel,
largeLetter, letter...). No pickup points: Royal Mail's are its post offices.

Credentials: a Click & Drop business account's API key, and - for tracking - an app at
[developer.royalmail.net](https://developer.royalmail.net) with the Tracking API v2.

Built from Royal Mail's published API documentation and tested on recorded answers; not yet run
against the service: that needs the credentials above.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Royal Mail Group Limited. It reaches their service on behalf of whoever runs it, and it
does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Royal Mail's terms
([royalmail.com/terms-website](https://www.royalmail.com/terms-website)), for obtaining whatever
they require (an account, a key, a licence or subscription, an attribution or a citation, a rate
limit to respect), and for any use made of what it returns. The authors publish it free of charge,
make no commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
