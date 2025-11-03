<?php

namespace Tests\Unit\Services;

use App\Models\DiscountCode;
use App\Services\DiscountCodeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DiscountCodeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');
        $schema->dropAllTables();
        $schema->dropIfExists('discounts_codes');

        $schema->create('discounts_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->json('course_ids')->nullable();
            $table->json('sport_ids')->nullable();
            $table->json('client_ids')->nullable();
            $table->json('degree_ids')->nullable();
            $table->string('discount_type');
            $table->decimal('discount_value', 10, 2);
            $table->integer('total')->nullable();
            $table->integer('remaining')->nullable();
            $table->integer('max_uses_per_user')->nullable();
            $table->decimal('min_purchase_amount', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->string('applicable_to')->default('all');
            $table->boolean('active')->default(true);
            $table->boolean('stackable')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_validate_code_accepts_allowed_course_list(): void
    {
        $discountCode = DiscountCode::withoutEvents(function () {
            return DiscountCode::query()->create([
                'code' => 'PROMO_OK',
                'discount_type' => 'fixed_amount',
                'discount_value' => 15,
                'applicable_to' => 'specific_courses',
                'course_ids' => [101],
                'active' => true,
                'remaining' => 5,
                'stackable' => false,
            ]);
        });

        $service = app(DiscountCodeService::class);

        $result = $service->validateCode($discountCode->code, [
            'course_id' => 101,
            'course_ids' => [101],
            'amount' => 120,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(15.0, $result['discount_amount']);
    }

    public function test_validate_code_rejects_courses_not_allowed(): void
    {
        $discountCode = DiscountCode::withoutEvents(function () {
            return DiscountCode::query()->create([
                'code' => 'PROMO_ONLY_ONE',
                'discount_type' => 'fixed_amount',
                'discount_value' => 5,
                'applicable_to' => 'specific_courses',
                'course_ids' => [202],
                'active' => true,
                'remaining' => 5,
                'stackable' => true,
            ]);
        });

        $service = app(DiscountCodeService::class);

        $result = $service->validateCode($discountCode->code, [
            'course_ids' => [202, 303],
            'amount' => 95,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertEquals(0.0, $result['discount_amount']);
    }
}

