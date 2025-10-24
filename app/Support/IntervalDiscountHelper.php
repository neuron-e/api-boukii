<?php

namespace App\Support;

use App\Models\BookingUser;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Helper utilities to manage interval-based discount rules across the platform.
 */
class IntervalDiscountHelper
{
    /**
     * Calculate the total price for a flexible collective course taking into account
     * interval-specific and global discount rules.
     */
    public static function calculateFlexibleCollectivePrice(
        Course $course,
        Collection $bookingUsers
    ): float {
        if ($bookingUsers->isEmpty()) {
            return 0.0;
        }

        // Ensure we are working with BookingUser models.
        $bookingUsers = self::normalizeBookingUsersCollection($bookingUsers);
        $bookingUsers->loadMissing('courseDate');

        [$globalRules, $intervalRules] = self::getDiscountRulesForCourse($course);

        $dateEntries = self::buildUniqueDateEntries($bookingUsers);

        $intervalCounters = [];
        $total = 0.0;

        foreach ($dateEntries as $entry) {
            $intervalId = $entry['interval_id'];
            $key = $intervalId !== null ? (int) $intervalId : 'global';
            $intervalCounters[$key] = ($intervalCounters[$key] ?? 0) + 1;
            $dayIndex = $intervalCounters[$key];

            $price = self::applyDiscount(
                (float) $course->price,
                $intervalId,
                $dayIndex,
                $globalRules,
                $intervalRules
            );

            $total += $price;
        }

        return round($total, 2);
    }

    /**
     * Apply discount rules (interval-specific first, then global fallback) to a base price.
     */
    public static function applyDiscount(
        float $basePrice,
        ?int $intervalId,
        int $dayIndex,
        array $globalRules,
        array $intervalRules
    ): float {
        $rule = null;

        if ($intervalId !== null) {
            $intervalKey = (int) $intervalId;
            $rule = $intervalRules[$intervalKey][$dayIndex] ?? null;
        }

        if ($rule === null) {
            $rule = $globalRules[$dayIndex] ?? null;
        }

        if ($rule === null) {
            return $basePrice;
        }

        if ($rule['type'] === 'percentage') {
            return max(0, $basePrice - ($basePrice * $rule['value'] / 100));
        }

        if ($rule['type'] === 'fixed') {
            return max(0, $basePrice - $rule['value']);
        }

        return $basePrice;
    }

    /**
     * Return discount rules keyed by booking day for both global course rules and per-interval rules.
     *
     * @return array{0: array<int, array{type:string,value:float}>, 1: array<int, array<int, array{type:string,value:float}>>}
     */
    public static function getDiscountRulesForCourse(Course $course): array
    {
        $course->loadMissing([
            'courseIntervals.discounts' => function ($query) {
                $query->active()->orderBy('min_days');
            },
        ]);

        $globalRules = self::normalizeGlobalDiscounts($course->discounts);

        $intervalRules = [];
        foreach ($course->courseIntervals as $interval) {
            $intervalRules[(int) $interval->id] = self::normalizeIntervalDiscounts(
                $interval->discounts
            );
        }

        return [$globalRules, $intervalRules];
    }

    /**
     * Normalize the course-level discounts to an array keyed by day index.
     *
     * @param mixed $discounts
     * @return array<int, array{type:string,value:float}>
     */
    private static function normalizeGlobalDiscounts($discounts): array
    {
        if (empty($discounts)) {
            return [];
        }

        if (is_string($discounts)) {
            $decoded = json_decode($discounts, true);
            $discounts = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($discounts)) {
            return [];
        }

        $normalized = [];

        foreach ($discounts as $discount) {
            $day = $discount['date'] ?? $discount['day'] ?? null;
            if ($day === null) {
                continue;
            }

            $day = (int) $day;
            if ($day <= 0) {
                continue;
            }

            if (array_key_exists('type', $discount)) {
                $type = (int) $discount['type'] === 2 ? 'fixed' : 'percentage';
                $value = (float) ($discount['discount'] ?? 0);
            } elseif (array_key_exists('discount', $discount)) {
                // Newer format but without explicit type (assume percentage)
                $type = 'percentage';
                $value = (float) $discount['discount'];
            } elseif (array_key_exists('percentage', $discount)) {
                $type = 'percentage';
                $value = (float) $discount['percentage'];
            } elseif (array_key_exists('reduccion', $discount)) {
                $type = 'percentage';
                $value = (float) $discount['reduccion'];
            } else {
                continue;
            }

            $normalized[$day] = [
                'type' => $type,
                'value' => $value,
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Normalize interval-specific discounts keyed by booking day.
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\CourseIntervalDiscount> $discounts
     * @return array<int, array{type:string,value:float}>
     */
    private static function normalizeIntervalDiscounts(Collection $discounts): array
    {
        if ($discounts->isEmpty()) {
            return [];
        }

        $normalized = [];

        foreach ($discounts as $discount) {
            $day = (int) ($discount->min_days ?? 0);
            if ($day <= 0) {
                continue;
            }

            $type = $discount->discount_type === 'fixed_amount' ? 'fixed' : 'percentage';
            $value = (float) $discount->discount_value;

            $normalized[$day] = [
                'type' => $type,
                'value' => $value,
            ];
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * Build a sorted list of unique date entries with interval mapping.
     *
     * @param Collection<int, BookingUser> $bookingUsers
     * @return Collection<int, array{date:string,interval_id:?int}>
     */
    private static function buildUniqueDateEntries(Collection $bookingUsers): Collection
    {
        return $bookingUsers
            ->filter(function ($bookingUser) {
                return optional($bookingUser)->status !== 2;
            })
            ->map(function ($bookingUser) {
                /** @var BookingUser $bookingUser */
                $intervalId = null;

                if ($bookingUser->relationLoaded('courseDate') && $bookingUser->courseDate) {
                    $intervalId = $bookingUser->courseDate->course_interval_id;
                } elseif (property_exists($bookingUser, 'course_interval_id') && $bookingUser->course_interval_id) {
                    $intervalId = $bookingUser->course_interval_id;
                } elseif (property_exists($bookingUser, 'interval_id') && $bookingUser->interval_id) {
                    $intervalId = $bookingUser->interval_id;
                }

                return [
                    'date' => $bookingUser->date,
                    'interval_id' => $intervalId !== null ? (int) $intervalId : null,
                ];
            })
            ->unique('date')
            ->sortBy('date')
            ->values();
    }

    /**
     * Ensure we are always handling an Eloquent collection of BookingUser models.
     *
     * @param Collection $bookingUsers
     * @return EloquentCollection<int, BookingUser>
     */
    private static function normalizeBookingUsersCollection(Collection $bookingUsers): EloquentCollection
    {
        if ($bookingUsers instanceof EloquentCollection) {
            return $bookingUsers;
        }

        $models = $bookingUsers->filter(function ($item) {
            return $item instanceof BookingUser;
        });

        return EloquentCollection::make($models->values());
    }
}
