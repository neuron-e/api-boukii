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

        $bookingUsers = self::normalizeBookingUsersCollection($bookingUsers);
        $bookingUsers->loadMissing('courseDate');

        $datesByInterval = [];

        foreach ($bookingUsers as $bookingUser) {
            if ($bookingUser->status === 2) {
                continue;
            }

            $intervalId = null;
            if ($bookingUser->relationLoaded('courseDate') && $bookingUser->courseDate) {
                $intervalId = $bookingUser->courseDate->interval_id
                    ?? $bookingUser->courseDate->course_interval_id;
            } elseif (property_exists($bookingUser, 'interval_id') && $bookingUser->interval_id) {
                $intervalId = $bookingUser->interval_id;
            } elseif (property_exists($bookingUser, 'course_interval_id') && $bookingUser->course_interval_id) {
                $intervalId = $bookingUser->course_interval_id;
            }

            $intervalKey = $intervalId !== null ? (string) $intervalId : 'default';
            if (!isset($datesByInterval[$intervalKey])) {
                $datesByInterval[$intervalKey] = [];
            }

            $dateValue = $bookingUser->date instanceof \Carbon\Carbon
                ? $bookingUser->date->format('Y-m-d')
                : (string) $bookingUser->date;

            if ($dateValue === '') {
                continue;
            }

            if (!array_key_exists($dateValue, $datesByInterval[$intervalKey])) {
                $basePrice = (float) ($course->price ?? 0);
                if ($basePrice <= 0) {
                    $basePrice = (float) ($bookingUser->price ?? 0);
                }
                $datesByInterval[$intervalKey][$dateValue] = $basePrice;
            }
        }

        $total = 0.0;

        foreach ($datesByInterval as $intervalKey => $dates) {
            if (empty($dates)) {
                continue;
            }

            $baseTotal = array_sum($dates);
            $datesCount = count($dates);
            $intervalId = $intervalKey !== 'default' ? (int) $intervalKey : null;
            $discounts = self::getApplicableDiscounts($course, $intervalId);
            $total += self::applyFlexibleDiscount($baseTotal, $datesCount, $discounts);
        }

        return round(max(0, $total), 2);
    }

    private static function getApplicableDiscounts(Course $course, ?int $intervalId): array
    {
        if ($intervalId !== null) {
            $intervalDiscounts = self::getIntervalDiscountsFromSettings($course);
            if (array_key_exists($intervalId, $intervalDiscounts) && !empty($intervalDiscounts[$intervalId])) {
                return self::normalizeDiscountSource($intervalDiscounts[$intervalId]);
            }
        }

        return self::normalizeDiscountSource($course->discounts ?? []);
    }

    private static function getIntervalDiscountsFromSettings(Course $course): array
    {
        $settings = $course->settings;
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($settings)) {
            return [];
        }

        $intervals = $settings['intervals'] ?? [];
        if (!is_array($intervals)) {
            return [];
        }

        $result = [];
        foreach ($intervals as $interval) {
            if (!is_array($interval)) {
                continue;
            }
            $intervalId = $interval['id'] ?? null;
            if ($intervalId === null) {
                continue;
            }
            $discounts = $interval['discounts'] ?? [];
            if (is_string($discounts)) {
                $decoded = json_decode($discounts, true);
                $discounts = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($discounts) || empty($discounts)) {
                continue;
            }
            $result[(int) $intervalId] = $discounts;
        }

        return $result;
    }

    private static function applyFlexibleDiscount(float $baseTotal, int $selectedDatesCount, $rawDiscounts): float
    {
        $discounts = self::parseFlexibleDiscounts($rawDiscounts);
        if ($baseTotal <= 0 || $selectedDatesCount <= 0 || empty($discounts)) {
            return max(0, $baseTotal);
        }

        $applicable = null;
        foreach ($discounts as $discount) {
            if ($selectedDatesCount >= $discount['threshold']) {
                if (!$applicable || $discount['threshold'] > $applicable['threshold']) {
                    $applicable = $discount;
                }
            }
        }

        if (!$applicable || $applicable['value'] <= 0) {
            return max(0, $baseTotal);
        }

        if ($applicable['type'] === 'percentage') {
            $bounded = max(0, min(100, $applicable['value']));
            return max(0, $baseTotal * (1 - $bounded / 100));
        }

        return max(0, $baseTotal - $applicable['value']);
    }

    private static function parseFlexibleDiscounts($raw): array
    {
        if (!$raw) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $threshold = (int) ($item['date'] ?? $item['dates'] ?? $item['count'] ?? $item['n'] ?? $item['days'] ?? 0);
            $value = (float) ($item['discount'] ?? $item['percentage'] ?? $item['percent'] ?? $item['value'] ?? 0);
            if ($threshold <= 0 || $value <= 0) {
                continue;
            }

            $type = 'percentage';
            $rawType = $item['type'] ?? null;
            if (is_string($rawType)) {
                $type = strtolower($rawType) === 'fixed' ? 'fixed' : 'percentage';
            } elseif (is_numeric($rawType)) {
                $type = (int) $rawType === 2 ? 'fixed' : 'percentage';
            }

            $normalized[] = [
                'threshold' => $threshold,
                'value' => $value,
                'type' => $type,
            ];
        }

        return $normalized;
    }

    private static function normalizeDiscountSource(mixed $discounts): array
    {
        if (is_string($discounts)) {
            $decoded = json_decode($discounts, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($discounts) ? $discounts : [];
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
