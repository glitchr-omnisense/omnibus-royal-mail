# omnibus/royal-mail

Royal Mail for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): orders with their
postage and PDF labels through the Click & Drop API, tracking through Royal Mail's Tracking API v2
(its own credentials). Click & Drop quotes no prices: they come from the `rates` option. The
gateway calls:

| Operation | API | Request |
|---|---|---|
| ship | Click & Drop | `POST https://api.parcel.royalmail.com/api/v1/orders` |
| ship (label) | Click & Drop | `GET https://api.parcel.royalmail.com/api/v1/orders/{orderIdentifier}/label?documentType=postageLabel` |
| track | Tracking API v2 | `GET https://api.royalmail.net/mailpieces/v2/{trackingNumber}/events` |

There is no sandbox address. Every call has a 20-second timeout.

## Installation

```sh
composer require omnibus/royal-mail
```

Requires PHP 8.2, `omnisense/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- A Click & Drop business account's **API key** (Click & Drop: Settings > Integrations > API),
  sent as `Authorization: Bearer`.
- For tracking, an app at [developer.royalmail.net](https://developer.royalmail.net) with the
  Tracking API v2: its **client id** and **client secret**, sent as `X-IBM-Client-Id` and
  `X-IBM-Client-Secret` (with `X-Accept-RMG-Terms: yes`). Without them, the gateway does not
  support `Tracking`.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | the Click & Drop API key |
| `tracking_client_id` | ?string | `null` | no | the Tracking API's client id |
| `tracking_client_secret` | ?string | `null` | no | the Tracking API's client secret |
| `rates` | list | - | no | the only way this gateway rates ([the rates option](https://github.com/glitchr-omnisense/omnibus/blob/2.x/docs/configuration.md#the-rates-option)) |

Shipment options read when booking (`Shipment::$options`):

| Option | Default | |
|---|---|---|
| `package_format` | `parcel` | Click & Drop's package format identifier (`parcel`, `largeLetter`, `letter`...) |

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | only with `rates` (configured prices): Click & Drop quotes none |
| `Shipping` | yes |
| `Tracking` | only with `tracking_client_id` and `tracking_client_secret` |
| `Pickup` | no: Royal Mail's are its post offices, with no API |
| `GetSlip` | no |
| `Cancel` | no |
| `Paperless` | no |

## Behaviour

**Ship.** One order (`POST /orders`, one item):

- the order reference is the shipment's `reference`, 40 characters;
- the recipient: full name, company, two address lines, city, postcode, country, phone, email;
  the sender is not sent: Click & Drop uses the account's own sender address;
- each parcel is a package: its weight in grams (at least 1), the `package_format`, its
  dimensions converted to millimetres when all three are given (height, width, and the length as
  depth);
- the order date `Shipment::$shippingDate`, else now (ISO 8601); subtotal and total the sum of the
  parcels' `value` in major units, shipping cost 0, in the first parcel's currency;
- postage: the service code `Shipment::$service` (Click & Drop codes: `TPN01` Tracked 24, `TPS01`
  Tracked 48, `TRN01` Tracked 24 with signature...); none sends no code and Click & Drop applies
  the account's default; notifications go to the recipient when they have an email.

When no order comes back, the first failed order's first error is raised. The tracking number is
the created order's `trackingNumber`, else its `orderIdentifier`. Then the postage label is asked
for (`documentType=postageLabel`); a PDF answer is the label's `content`. Click & Drop makes the
label once the postage is applied: if that request fails, the booking still succeeds with no
content; the order stays in Click & Drop, where its label is printed (`GetSlip` is not
supported: Click & Drop finds an order by its identifier or reference, not by its tracking
number). The format is `application/pdf`; `trackingUrl` is
`https://www.royalmail.com/track-your-item#/tracking-results/<number>`. `pickupPoint` is not sent.

**Track.** The mail piece's events (`mailPieces`, else the answer itself): their `eventDateTime`,
`eventName` as description, `locationName` as location, `eventCode` as `code`, sorted oldest
first. The tracking's status is read from the summary's `lastEventCode` and `statusDescription`
(else the latest event's name). A status is read in this order:

| The code / the name contains | `TrackingStatus` |
|---|---|
| a code starting with `EVKSP`, or `delivered` | `DELIVERED` |
| `out for delivery`, `due to be delivered` | `OUT_FOR_DELIVERY` |
| `ready for collection`, `available for collection` | `AVAILABLE_FOR_PICKUP` |
| `returned`, `return to sender` | `RETURNED` |
| `attempted`, `unable`, `delay` | `EXCEPTION` |
| `received`, `in transit`, `arrived`, `dispatched`, `accepted` | `IN_TRANSIT` |
| `advised`, `label` | `PENDING` |
| anything else | `UNKNOWN` |

The locale is not sent.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\RoyalMail\RoyalMailGatewayFactory;
use Symfony\Component\HttpClient\HttpClient;

$royalMail = (new RoyalMailGatewayFactory(HttpClient::create()))->create([
    'api_key' => getenv('ROYAL_MAIL_API_KEY'),
    'tracking_client_id' => getenv('RM_TRACKING_ID'),        // leave both out: no tracking
    'tracking_client_secret' => getenv('RM_TRACKING_SECRET'),
    'rates' => [
        ['service' => 'TPN01', 'label' => 'Tracked 24', 'days' => 1, 'currency' => 'GBP', 'countries' => ['GB'],
            'bands' => [2000 => 495, 10000 => 795, 20000 => 1295]],
        ['service' => 'TPS01', 'label' => 'Tracked 48', 'days' => 2, 'currency' => 'GBP', 'countries' => ['GB'],
            'bands' => [2000 => 395, 10000 => 695, 20000 => 1095]],
    ],
]);

$shipment = new Shipment(
    new Address('Glitch Art', ['1 Trafalgar Square'], 'WC2N 5DN', 'London', 'GB'),
    new Address('Ada Lovelace', ['10 Downing Street'], 'SW1A 2AA', 'London', 'GB', email: 'ada@example.org', phone: '+447700900000'),
    [new Parcel(850, 30, 20, 10, value: 4500, currency: 'GBP')],
    service: 'TPN01',
    reference: 'ORDER-1042',
    options: ['package_format' => 'parcel'],
);

foreach ($royalMail->rate($shipment) as $rate) {
    printf("%s %-11s %5.2f %s\n", $rate->service, $rate->label, $rate->amount / 100, $rate->currency);
}

$label = $royalMail->ship($shipment);
if (null !== $label->content) {
    file_put_contents($label->trackingNumber.'.pdf', $label->content);
}
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $royalMail->track($label->trackingNumber);
echo $tracking->status->value, "\n";
```

In Symfony:

```yaml
omnibus:
    gateways:
        royal_mail:
            factory: royal_mail
            options:
                api_key: '%env(ROYAL_MAIL_API_KEY)%'
                tracking_client_id: '%env(RM_TRACKING_ID)%'
                tracking_client_secret: '%env(RM_TRACKING_SECRET)%'
                rates:
                    - { service: TPN01, label: 'Tracked 24', currency: GBP, countries: [GB], bands: { 2000: 495, 10000: 795 } }
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `royal_mail`:

- a Click & Drop HTTP status of 400 or more: the answer's `message`, else its first error's
  `errorMessage`, else `title`, else `HTTP <status>`; its `code` in `carrierCode`;
- a Click & Drop body that is neither JSON nor a PDF: `Royal Mail answered HTTP <status> with a
  body that is not JSON.`; a network error: `Royal Mail request failed: ...`;
- an order refused: the first failed order's first `errorMessage`, else `Click & Drop created no
  order.`;
- a Tracking API status of 400 or more: its first error's `errorDescription`, else
  `moreInformation`, else `HTTP <status>`; the error code (else `httpCode`) in `carrierCode`; a
  network error or unreadable body: `Royal Mail tracking failed: ...`.

A failure to fetch the label after the order is created is not raised (above). A missing `api_key`
is an `InvalidConfigException` when the gateway is built; `Tracking` without its two credentials,
`Pickup`, `GetSlip`, `Cancel`, `Paperless`, and `Rating` without `rates`, raise
`RequestNotSupportedException` before any call.

No failure carries the API key and the Tracking API's client secret: a network error's message has
them masked as `***` (the key parameters of a URL too), its previous is a copy of the HTTP client's
exception (the same class and code), and no trace of the chain keeps its arguments, where the
request's options stood ([No secret in a
failure](https://github.com/glitchr-omnisense/omnibus/blob/2.x/docs/errors.md#no-secret-in-a-failure)).

## Verified, and not

Tested offline: `Tests/RoyalMailGatewayTest.php` (an order created with its label, the reference,
the postcode, the weight in grams, the service code, the currency and the bearer key; tracking with
the Tracking API's own credentials, events oldest first and `DELIVERED`; no pickup points) and
`Tests/RoyalMailContractTest.php`, the family's conformance suite on recorded answers (`Tracking`
supported only with its credentials). The example above was run in the core's harness on those
recorded answers.

Not verified: the README says the package is built from Royal Mail's published API documentation
and was not yet run against the service; that needs the credentials above.

## Responsibility

An independent, unofficial client, not affiliated with Royal Mail Group Limited. Whoever runs it
complies with Royal Mail's terms
([royalmail.com/terms-website](https://www.royalmail.com/terms-website)): see the README's
Responsibility section.
