<?php

namespace Tests\Feature;

use App\Mail\GiftVoucherDeliveredMail;
use App\Models\GiftVoucher;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicGiftVoucherTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create([
            'name' => 'Test School',
        ]);
    }

    public function test_public_purchase_creates_paid_voucher_and_sends_mail(): void
    {
        Mail::fake();

        $payload = [
            'amount' => 120,
            'currency' => 'CHF',
            'school_id' => $this->school->id,
            'buyer_name' => 'Alice Buyer',
            'buyer_email' => 'buyer@example.com',
            'buyer_phone' => '+41000000000',
            'buyer_locale' => 'en',
            'recipient_name' => 'Bob Recipient',
            'recipient_email' => 'recipient@example.com',
            'recipient_phone' => '+41000000001',
            'recipient_locale' => 'en',
            'personal_message' => 'Enjoy your time on the slopes!',
            'template' => 'birthday',
        ];

        $response = $this->postJson('/api/public/gift-vouchers/purchase', $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'gift_voucher' => [
                        'id',
                        'code',
                        'amount',
                        'buyer_name',
                        'recipient_email'
                    ],
                    'voucher_code',
                    'payment_url'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'payment_url' => null,
                ]
            ]);

        $this->assertDatabaseHas('gift_vouchers', [
            'recipient_email' => 'recipient@example.com',
            'buyer_email' => 'buyer@example.com',
            'buyer_name' => 'Alice Buyer',
            'recipient_name' => 'Bob Recipient',
            'school_id' => $this->school->id,
            'status' => 'active',
            'is_paid' => true,
            'is_delivered' => true,
        ]);

        $giftVoucher = GiftVoucher::where('recipient_email', 'recipient@example.com')->firstOrFail();
        $this->assertNotNull($giftVoucher->voucher);
        $this->assertEquals($giftVoucher->voucher->id, $giftVoucher->voucher_id);
        $this->assertEquals($giftVoucher->balance, $giftVoucher->amount);

        Mail::assertQueued(GiftVoucherDeliveredMail::class, function (GiftVoucherDeliveredMail $mail) use ($giftVoucher) {
            return $mail->giftVoucher->is($giftVoucher)
                && $mail->hasTo('recipient@example.com');
        });
    }

    public function test_public_purchase_requires_mandatory_fields(): void
    {
        $response = $this->postJson('/api/public/gift-vouchers/purchase', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'amount',
                'currency',
                'school_id',
                'buyer_name',
                'buyer_email',
                'recipient_name',
                'recipient_email',
            ]);
    }

    public function test_public_purchase_rejects_same_buyer_and_recipient_email(): void
    {
        $payload = [
            'amount' => 80,
            'currency' => 'CHF',
            'school_id' => $this->school->id,
            'buyer_name' => 'Same Person',
            'buyer_email' => 'same@example.com',
            'recipient_name' => 'Same Person',
            'recipient_email' => 'same@example.com',
        ];

        $this->postJson('/api/public/gift-vouchers/purchase', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_email']);
    }

    public function test_public_purchase_validates_currency_length(): void
    {
        $payload = [
            'amount' => 50,
            'currency' => 'INVALID',
            'school_id' => $this->school->id,
            'buyer_name' => 'Alice Buyer',
            'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Bob Recipient',
            'recipient_email' => 'recipient@example.com',
        ];

        $this->postJson('/api/public/gift-vouchers/purchase', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency']);
    }

    public function test_public_purchase_validates_amount_range(): void
    {
        $payload = [
            'amount' => 5,
            'currency' => 'CHF',
            'school_id' => $this->school->id,
            'buyer_name' => 'Alice Buyer',
            'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Bob Recipient',
            'recipient_email' => 'recipient@example.com',
        ];

        $this->postJson('/api/public/gift-vouchers/purchase', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_templates_endpoint_returns_data(): void
    {
        $this->getJson('/api/public/gift-vouchers/templates')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'message',
            ]);
    }
}
