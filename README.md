# omnibus/royal-mail

Royal Mail for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): orders with their
postage and labels through Click & Drop, tracking through the Tracking API. Prices come from
configuration (`rates`): Click & Drop quotes none.

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
