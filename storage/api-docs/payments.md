# Payments API examples

## Environment variables

Configure Payyo credentials in your `.env` file:

```env
PAYYO_INSTANCE=your-instance.payyo.com
PAYYO_KEY=your-payyo-api-key
```

Assign a school to Payyo by setting `payment_provider` to `payyo` and storing
`payyo_instance` and `payyo_key` for that school. Schools without these values
will use Payrexx instead.

## Sample API call

Create a payment link for a booking:

```bash
curl -X POST http://localhost/api/slug/bookings/payments/1 \
  -H 'slug: your-school-slug' \
  -H 'Content-Type: application/json' \
  -d '{"redirectUrl": "https://example.com/return"}'
```

The endpoint returns a URL to the hosted payment page. If the school is not
configured for Payyo, the request falls back to generating a Payrexx link.
