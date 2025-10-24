<?php

namespace Tests\Unit\Support;

use App\Models\CourseIntervalDiscount;
use App\Support\IntervalDiscountHelper;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class IntervalDiscountHelperTest extends TestCase
{
    public function test_normalize_interval_discounts_maps_rules_by_day(): void
    {
        $discount = new CourseIntervalDiscount([
            'min_days' => 2,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'active' => true,
        ]);

        $result = $this->invokePrivateMethod(
            IntervalDiscountHelper::class,
            'normalizeIntervalDiscounts',
            [new Collection([$discount])]
        );

        $this->assertArrayHasKey(2, $result);
        $this->assertSame('percentage', $result[2]['type']);
        $this->assertSame(10.0, $result[2]['value']);
    }

    public function test_normalize_global_discounts_supports_legacy_formats(): void
    {
        $rawDiscounts = json_encode([
            ['date' => 3, 'percentage' => 20],
            ['day' => 4, 'reduccion' => 5],
            ['date' => 5, 'type' => 2, 'discount' => 12],
        ]);

        $result = $this->invokePrivateMethod(
            IntervalDiscountHelper::class,
            'normalizeGlobalDiscounts',
            [$rawDiscounts]
        );

        $this->assertCount(3, $result);
        $this->assertSame('percentage', $result[3]['type']);
        $this->assertSame(20.0, $result[3]['value']);
        $this->assertSame(5.0, $result[4]['value']);
        $this->assertSame('fixed', $result[5]['type']);
        $this->assertSame(12.0, $result[5]['value']);
    }

    private function invokePrivateMethod(string $class, string $method, array $parameters)
    {
        $reflection = new ReflectionClass($class);
        $target = $reflection->getMethod($method);
        $target->setAccessible(true);

        return $target->invokeArgs(null, $parameters);
    }
}
