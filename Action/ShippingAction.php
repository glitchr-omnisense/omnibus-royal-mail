<?php

namespace Omnibus\RoyalMail\Action;

use Omnibus\Action\ActionInterface;
use Omnibus\Action\ApiAwareInterface;
use Omnibus\Action\ApiAwareTrait;
use Omnibus\Exception\CarrierException;
use Omnibus\Model\Label;
use Omnibus\Request\Request;
use Omnibus\Request\Shipping;
use Omnibus\RoyalMail\Api;

/**
 * An order in Click & Drop with its postage (the service: a Click & Drop
 * service code, e.g. TPN01 Tracked 24, TPS01 Tracked 48, TRN01 Tracked 24 with
 * signature; none: the account's default), then its label as PDF.
 */
final class ShippingAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Shipping;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Shipping);
        $s = $request->shipment;
        $r = $s->recipient;
        $value = array_sum(array_map(static fn ($p) => $p->value ?? 0, $s->parcels)) / 100;
        $data = $this->api->call('POST', '/orders', ['items' => [array_filter([
            'orderReference' => $s->reference ? mb_substr($s->reference, 0, 40) : null,
            'recipient' => array_filter([
                'address' => array_filter(['fullName' => $r->name, 'companyName' => $r->company, 'addressLine1' => $r->line(0), 'addressLine2' => $r->line(1) ?: null, 'city' => $r->city, 'postcode' => $r->postcode, 'countryCode' => strtoupper($r->country)]),
                'phoneNumber' => $r->phone,
                'emailAddress' => $r->email,
            ]),
            'packages' => array_map(static fn ($p) => array_filter(['weightInGrams' => max(1, $p->weight), 'packageFormatIdentifier' => $s->option('package_format', 'parcel'), 'dimensions' => $p->length && $p->width && $p->height ? ['heightInMms' => $p->height * 10, 'widthInMms' => $p->width * 10, 'depthInMms' => $p->length * 10] : null]), $s->parcels),
            'orderDate' => ($s->shippingDate ?? new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'subtotal' => $value, 'shippingCostCharged' => 0, 'total' => $value,
            'currencyCode' => $s->parcels[0]->currency,
            'postageDetails' => array_filter(['serviceCode' => $s->service, 'sendNotificationsTo' => $r->email ? 'recipient' : null]),
        ], static fn ($v) => null !== $v)]]);
        $order = $data['createdOrders'][0] ?? null;
        if (!\is_array($order) || empty($order['orderIdentifier'])) {
            throw new CarrierException('royal_mail', (string) ($data['failedOrders'][0]['errors'][0]['errorMessage'] ?? 'Click & Drop created no order.'));
        }
        $number = (string) ($order['trackingNumber'] ?? $order['orderIdentifier']);
        $label = null;
        try {
            $label = $this->api->call('GET', '/orders/'.rawurlencode((string) $order['orderIdentifier']).'/label', null, ['documentType' => 'postageLabel'])['content'] ?? null;
        } catch (CarrierException) {
            // Not yet: a label is made once the postage is applied. The order stays in Click & Drop,
            // where its label is printed (no GetSlip: Click & Drop finds no order by tracking number).
        }
        $request->setResult(new Label('royal_mail', $number, $label, Label::PDF, null, 'https://www.royalmail.com/track-your-item#/tracking-results/'.rawurlencode($number)));
    }
}
