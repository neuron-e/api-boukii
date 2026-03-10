<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class RentalPricingService
{
    private const PERIOD_HALF_DAY = 'half_day';
    private const PERIOD_FULL_DAY = 'full_day';
    private const PERIOD_MULTI_DAY = 'multi_day';
    private const PERIOD_WEEK = 'week';
    private const PERIOD_SEASON = 'season';

    private const MODE_PER_DAY = 'per_day';
    private const MODE_FLAT = 'flat';

    public function quote(int $schoolId, array $payload): array
    {
        $requestLines = $payload['lines'] ?? $payload['items'] ?? null;
        if (!is_array($requestLines) || empty($requestLines)) {
            throw new InvalidArgumentException('lines is required');
        }

        $currency = strtoupper((string) ($payload['currency'] ?? 'CHF'));
        $quotedLines = [];
        $subtotal = 0.0;

        foreach (array_values($requestLines) as $index => $line) {
            if (!is_array($line)) {
                continue;
            }
            $quotedLine = $this->quoteLine($schoolId, $payload, $line, $currency, $index);
            $quotedLines[] = $quotedLine;
            $subtotal += (float) $quotedLine['line_total'];
        }

        if (empty($quotedLines)) {
            throw new InvalidArgumentException('No valid lines to price');
        }

        $grouped = $this->groupQuotedLines($quotedLines);
        $reservationPeriod = $this->buildReservationPeriod($quotedLines, $payload);
        $discountTotal = (float) ($payload['discount_total'] ?? 0);
        $taxTotal = (float) ($payload['tax_total'] ?? 0);

        return [
            'currency' => $currency,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal - $discountTotal + $taxTotal, 2),
            'total_quantity' => array_sum(array_map(fn ($line) => (int) $line['quantity'], $quotedLines)),
            'period' => $reservationPeriod,
            'lines' => $quotedLines,
            'groups' => array_values($grouped),
        ];
    }

    private function quoteLine(int $schoolId, array $reservationPayload, array $line, string $currency, int $index): array
    {
        $variantId = (int) ($line['variant_id'] ?? 0);
        if ($variantId <= 0) {
            throw new InvalidArgumentException("variant_id is required for line {$index}");
        }

        $variant = $this->findVariant($schoolId, $variantId);
        if (!$variant) {
            throw new InvalidArgumentException("variant_id {$variantId} is invalid");
        }

        $quantity = max(1, (int) ($line['quantity'] ?? 1));
        $startDate = (string) ($line['start_date'] ?? $reservationPayload['start_date'] ?? '');
        $endDate = (string) ($line['end_date'] ?? $reservationPayload['end_date'] ?? $startDate);
        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException("start_date and end_date are required for line {$index}");
        }

        $days = $this->calculateRentalDays($startDate, $endDate);
        $periodType = $this->normalizePeriodType((string) ($line['period_type'] ?? $reservationPayload['period_type'] ?? ''), $days);
        $pricingRule = $this->resolveApplicableRule($schoolId, $variant, $periodType, $days);
        if (!$pricingRule) {
            throw new InvalidArgumentException("No applicable pricing rule found for variant {$variantId}");
        }

        $pricingMode = $this->normalizePricingMode($pricingRule);
        $unitPrice = round((float) ($pricingRule['price'] ?? 0), 2);
        $lineSubtotal = $pricingMode === self::MODE_FLAT
            ? ($unitPrice * $quantity)
            : ($unitPrice * $quantity * $days);

        return [
            'item_id' => (int) $variant['item_id'],
            'item_name' => (string) ($variant['item_name'] ?? $variant['variant_name']),
            'brand' => (string) ($variant['brand'] ?? ''),
            'model' => (string) ($variant['model'] ?? ''),
            'variant_id' => $variantId,
            'variant_name' => (string) ($variant['variant_name'] ?? ''),
            'size_label' => (string) ($variant['size_label'] ?? ''),
            'quantity' => $quantity,
            'currency' => (string) ($pricingRule['currency'] ?? $currency),
            'period_type' => $periodType,
            'pricing_mode' => $pricingMode,
            'pricing_rule_id' => (int) ($pricingRule['id'] ?? 0),
            'pricing_basis_key' => $this->pricingBasisKey($pricingMode, $periodType),
            'rental_days' => $days,
            'unit_price' => $unitPrice,
            'line_subtotal' => round($lineSubtotal, 2),
            'line_total' => round($lineSubtotal, 2),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => (string) ($line['start_time'] ?? $reservationPayload['start_time'] ?? '09:00'),
            'end_time' => (string) ($line['end_time'] ?? $reservationPayload['end_time'] ?? '17:00'),
            'pricing_source' => (string) ($pricingRule['_pricing_source'] ?? 'default'),
        ];
    }

    private function findVariant(int $schoolId, int $variantId): ?array
    {
        $query = DB::table('rental_variants as rv')
            ->leftJoin('rental_items as ri', 'ri.id', '=', 'rv.item_id')
            ->select([
                'rv.id',
                'rv.item_id',
                'rv.name as variant_name',
                'rv.size_label',
                'rv.sku',
                'ri.name as item_name',
                'ri.brand',
                'ri.model',
            ])
            ->where('rv.id', $variantId)
            ->where('rv.school_id', $schoolId);

        if (Schema::hasColumn('rental_variants', 'deleted_at')) {
            $query->whereNull('rv.deleted_at');
        }
        if (Schema::hasColumn('rental_items', 'deleted_at')) {
            $query->whereNull('ri.deleted_at');
        }

        $variant = $query->first();
        return $variant ? (array) $variant : null;
    }

    private function resolveApplicableRule(int $schoolId, array $variant, string $requestedPeriodType, int $days): ?array
    {
        $rules = $this->loadPricingRules($schoolId, (int) $variant['item_id'], (int) $variant['id']);
        if (empty($rules)) {
            return null;
        }

        $durationOverride = $this->pickRule(
            array_values(array_filter($rules, function (array $rule) use ($days) {
                $hasRange = $rule['min_days'] !== null || $rule['max_days'] !== null;
                return $hasRange && $this->ruleMatchesDuration($rule, $days);
            })),
            (int) $variant['id'],
            [$requestedPeriodType]
        );
        if ($durationOverride) {
            $durationOverride['_pricing_source'] = 'duration_override';
            return $durationOverride;
        }

        if (in_array($requestedPeriodType, [self::PERIOD_WEEK, self::PERIOD_SEASON], true)) {
            $packageRule = $this->pickRule(
                array_values(array_filter($rules, fn (array $rule) => $rule['period_type'] === $requestedPeriodType)),
                (int) $variant['id'],
                [$requestedPeriodType]
            );
            if ($packageRule) {
                $packageRule['_pricing_source'] = 'package_rule';
                return $packageRule;
            }
        }

        $fallbackPeriods = match ($requestedPeriodType) {
            self::PERIOD_HALF_DAY => [self::PERIOD_HALF_DAY, self::PERIOD_FULL_DAY, self::PERIOD_MULTI_DAY],
            self::PERIOD_MULTI_DAY => [self::PERIOD_FULL_DAY, self::PERIOD_MULTI_DAY, self::PERIOD_HALF_DAY],
            self::PERIOD_WEEK, self::PERIOD_SEASON => [self::PERIOD_FULL_DAY, self::PERIOD_HALF_DAY, self::PERIOD_MULTI_DAY],
            default => [self::PERIOD_FULL_DAY, self::PERIOD_HALF_DAY, self::PERIOD_MULTI_DAY],
        };

        $dailyFallback = $this->pickRule(
            array_values(array_filter($rules, function (array $rule) use ($fallbackPeriods) {
                return in_array($rule['period_type'], $fallbackPeriods, true)
                    && $this->normalizePricingMode($rule) === self::MODE_PER_DAY;
            })),
            (int) $variant['id'],
            $fallbackPeriods
        );
        if ($dailyFallback) {
            $dailyFallback['_pricing_source'] = 'default_daily';
            return $dailyFallback;
        }

        return null;
    }

    private function loadPricingRules(int $schoolId, int $itemId, int $variantId): array
    {
        $query = DB::table('rental_pricing_rules')
            ->where('school_id', $schoolId)
            ->where(function ($builder) use ($itemId, $variantId) {
                $builder->where('variant_id', $variantId)
                    ->orWhere(function ($fallback) use ($itemId) {
                        $fallback->whereNull('variant_id')
                            ->where('item_id', $itemId);
                    });
            });

        if (Schema::hasColumn('rental_pricing_rules', 'active')) {
            $query->where('active', true);
        }
        if (Schema::hasColumn('rental_pricing_rules', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $rules = $query->get()->map(function ($rule) {
            $row = (array) $rule;
            $row['period_type'] = strtolower((string) ($row['period_type'] ?? self::PERIOD_FULL_DAY));
            $row['pricing_mode'] = $row['pricing_mode'] ?? null;
            $row['min_days'] = isset($row['min_days']) && $row['min_days'] !== '' ? (int) $row['min_days'] : null;
            $row['max_days'] = isset($row['max_days']) && $row['max_days'] !== '' ? (int) $row['max_days'] : null;
            $row['priority'] = isset($row['priority']) ? (int) $row['priority'] : 100;
            return $row;
        })->all();

        return $rules;
    }

    private function pickRule(array $rules, int $variantId, array $periodPriority): ?array
    {
        if (empty($rules)) {
            return null;
        }

        usort($rules, function (array $left, array $right) use ($variantId, $periodPriority) {
            $leftVariantScore = (int) (($left['variant_id'] ?? null) === $variantId);
            $rightVariantScore = (int) (($right['variant_id'] ?? null) === $variantId);
            if ($leftVariantScore !== $rightVariantScore) {
                return $rightVariantScore <=> $leftVariantScore;
            }

            $leftPeriod = array_search($left['period_type'] ?? '', $periodPriority, true);
            $rightPeriod = array_search($right['period_type'] ?? '', $periodPriority, true);
            $leftPeriod = $leftPeriod === false ? PHP_INT_MAX : $leftPeriod;
            $rightPeriod = $rightPeriod === false ? PHP_INT_MAX : $rightPeriod;
            if ($leftPeriod !== $rightPeriod) {
                return $leftPeriod <=> $rightPeriod;
            }

            if (($left['priority'] ?? 100) !== ($right['priority'] ?? 100)) {
                return ($left['priority'] ?? 100) <=> ($right['priority'] ?? 100);
            }

            return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
        });

        return $rules[0] ?? null;
    }

    private function ruleMatchesDuration(array $rule, int $days): bool
    {
        $min = $rule['min_days'];
        $max = $rule['max_days'];
        if ($min !== null && $days < $min) {
            return false;
        }
        if ($max !== null && $days > $max) {
            return false;
        }
        return true;
    }

    private function normalizePeriodType(string $periodType, int $days): string
    {
        $normalized = strtolower(trim($periodType));
        $allowed = [
            self::PERIOD_HALF_DAY,
            self::PERIOD_FULL_DAY,
            self::PERIOD_MULTI_DAY,
            self::PERIOD_WEEK,
            self::PERIOD_SEASON,
        ];
        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }
        return $days > 1 ? self::PERIOD_MULTI_DAY : self::PERIOD_FULL_DAY;
    }

    private function normalizePricingMode(array $rule): string
    {
        $mode = strtolower(trim((string) ($rule['pricing_mode'] ?? '')));
        if (in_array($mode, [self::MODE_PER_DAY, self::MODE_FLAT], true)) {
            return $mode;
        }

        return in_array($rule['period_type'] ?? '', [self::PERIOD_WEEK, self::PERIOD_SEASON], true)
            ? self::MODE_FLAT
            : self::MODE_PER_DAY;
    }

    private function calculateRentalDays(string $startDate, string $endDate): int
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);
        if ($start === false || $end === false) {
            throw new InvalidArgumentException('Invalid rental date range');
        }
        if ($end < $start) {
            throw new InvalidArgumentException('end_date cannot be before start_date');
        }
        return max(1, (int) floor(($end - $start) / 86400) + 1);
    }

    private function pricingBasisKey(string $pricingMode, string $periodType): string
    {
        if ($pricingMode === self::MODE_FLAT) {
            return $periodType;
        }

        return $periodType === self::PERIOD_HALF_DAY ? self::PERIOD_HALF_DAY : 'day';
    }

    private function buildReservationPeriod(array $quotedLines, array $payload): array
    {
        $startDates = array_values(array_filter(array_map(fn ($line) => $line['start_date'] ?? null, $quotedLines)));
        $endDates = array_values(array_filter(array_map(fn ($line) => $line['end_date'] ?? null, $quotedLines)));

        sort($startDates);
        sort($endDates);

        return [
            'start_date' => $payload['start_date'] ?? ($startDates[0] ?? null),
            'end_date' => $payload['end_date'] ?? (!empty($endDates) ? $endDates[count($endDates) - 1] : null),
            'start_time' => $payload['start_time'] ?? ($quotedLines[0]['start_time'] ?? '09:00'),
            'end_time' => $payload['end_time'] ?? ($quotedLines[0]['end_time'] ?? '17:00'),
            'period_type' => $payload['period_type'] ?? ($quotedLines[0]['period_type'] ?? self::PERIOD_FULL_DAY),
        ];
    }

    private function groupQuotedLines(array $quotedLines): array
    {
        $groups = [];

        foreach ($quotedLines as $line) {
            $itemId = (int) $line['item_id'];
            if (!isset($groups[$itemId])) {
                $groups[$itemId] = [
                    'item_id' => $itemId,
                    'name' => $line['item_name'],
                    'brand' => $line['brand'],
                    'model' => $line['model'],
                    'totalQty' => 0,
                    'subtotal' => 0,
                    'variants' => [],
                ];
            }

            $groups[$itemId]['totalQty'] += (int) $line['quantity'];
            $groups[$itemId]['subtotal'] += (float) $line['line_total'];
            $groups[$itemId]['variants'][] = [
                'variant_id' => (int) $line['variant_id'],
                'name' => $line['variant_name'],
                'sizeLabel' => $line['size_label'] ?: $line['variant_name'],
                'quantity' => (int) $line['quantity'],
                'subtotal' => (float) $line['line_total'],
                'rentalDays' => (int) $line['rental_days'],
                'unitPrice' => (float) $line['unit_price'],
                'currency' => $line['currency'],
                'pricingMode' => $line['pricing_mode'],
                'appliedPeriodType' => $line['period_type'],
                'pricingBasisKey' => $line['pricing_basis_key'],
            ];
        }

        foreach ($groups as &$group) {
            $group['subtotal'] = round((float) $group['subtotal'], 2);
        }

        return $groups;
    }
}
