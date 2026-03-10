<?php

namespace App\Http\Controllers\BookingPage;

use App\Models\Client;
use App\Models\ClientsSchool;
use App\Models\Language;
use App\Models\RentalItem;
use App\Models\RentalPickupPoint;
use App\Models\RentalPolicy;
use App\Models\RentalReservation;
use App\Models\RentalVariant;
use App\Services\RentalPricingService;
use App\Services\RentalReservationCreateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class RentalStoreController extends SlugAuthController
{
    public function __construct(
        Request $request,
        private readonly RentalPricingService $rentalPricingService,
        private readonly RentalReservationCreateService $rentalReservationCreateService
    ) {
        parent::__construct($request);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        $schoolId = (int) $this->school->id;
        $policy = RentalPolicy::forSchool($schoolId);
        $pickupPoints = $this->pickupPoints($schoolId);
        $catalog = $this->catalog($schoolId);
        $available = (bool) $policy->enabled && $pickupPoints->isNotEmpty() && $catalog->isNotEmpty();

        return $this->sendResponse([
            'available' => $available,
            'enabled' => (bool) $policy->enabled,
            'currency' => 'CHF',
            'school' => [
                'id' => $this->school->id,
                'slug' => $this->school->slug,
                'name' => $this->school->name,
            ],
            'pickup_points' => $pickupPoints->values()->all(),
            'items' => $catalog->values()->all(),
        ], 'Rental store bootstrap retrieved successfully');
    }

    public function quote(Request $request): JsonResponse
    {
        if (!$this->isRentalStoreAvailable()) {
            return $this->sendError('Rental store is not available for this school', [], 404);
        }

        try {
            $quote = $this->rentalPricingService->quote((int) $this->school->id, $request->all());
            return $this->sendResponse($quote, 'Rental pricing calculated successfully');
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            return $this->sendError('Error calculating rental pricing: ' . $e->getMessage(), [], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isRentalStoreAvailable()) {
            return $this->sendError('Rental store is not available for this school', [], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'pickup_point_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string',
            'accepts_newsletter' => 'nullable|boolean',
            'lines' => 'required|array|min:1',
        ]);

        try {
            $client = $this->resolveClient($request);
            $payload = $request->all();
            $payload['client_id'] = $client->id;
            $payload['school_id'] = (int) $this->school->id;
            $payload['return_point_id'] = $payload['return_point_id'] ?? $payload['pickup_point_id'];
            $payload['status'] = RentalReservation::STATUS_PENDING;

            $result = $this->rentalReservationCreateService->create((int) $this->school->id, $payload);
            $summary = $this->buildReservationSummary((int) $result['reservation']->id);

            return $this->sendResponse($summary, 'Rental reservation created successfully');
        } catch (InvalidArgumentException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            return $this->sendError('Error creating rental reservation: ' . $e->getMessage(), [], 500);
        }
    }

    private function isRentalStoreAvailable(): bool
    {
        $policy = RentalPolicy::forSchool((int) $this->school->id);
        if (!$policy->enabled) {
            return false;
        }

        return $this->pickupPoints((int) $this->school->id)->isNotEmpty()
            && $this->catalog((int) $this->school->id)->isNotEmpty();
    }

    private function pickupPoints(int $schoolId): Collection
    {
        return RentalPickupPoint::query()
            ->where('school_id', $schoolId)
            ->when(Schema::hasColumn('rental_pickup_points', 'active'), fn ($query) => $query->where('active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'address'])
            ->map(fn ($point) => [
                'id' => (int) $point->id,
                'name' => $point->name,
                'address' => $point->address,
            ]);
    }

    private function catalog(int $schoolId): Collection
    {
        $items = RentalItem::query()
            ->where('school_id', $schoolId)
            ->when(Schema::hasColumn('rental_items', 'active'), fn ($query) => $query->where('active', true))
            ->with(['variants' => function ($query) use ($schoolId) {
                $query->where('school_id', $schoolId);
                if (Schema::hasColumn('rental_variants', 'active')) {
                    $query->where('active', true);
                }
            }])
            ->orderBy('name')
            ->get();

        $rules = DB::table('rental_pricing_rules')
            ->where('school_id', $schoolId)
            ->when(Schema::hasColumn('rental_pricing_rules', 'active'), fn ($query) => $query->where('active', true))
            ->when(Schema::hasColumn('rental_pricing_rules', 'deleted_at'), fn ($query) => $query->whereNull('deleted_at'))
            ->get()
            ->map(function ($rule) {
                $row = (array) $rule;
                $row['period_type'] = strtolower((string) ($row['period_type'] ?? 'full_day'));
                $row['pricing_mode'] = $this->normalizePricingMode($row);
                $row['priority'] = isset($row['priority']) ? (int) $row['priority'] : 100;
                $row['min_days'] = isset($row['min_days']) && $row['min_days'] !== '' ? (int) $row['min_days'] : null;
                $row['max_days'] = isset($row['max_days']) && $row['max_days'] !== '' ? (int) $row['max_days'] : null;
                return $row;
            });

        return $items->map(function ($item) use ($rules) {
            $variants = $item->variants
                ->map(function ($variant) use ($rules, $item) {
                    $variantRules = $this->variantPricingOptions((int) $item->id, (int) $variant->id, $rules);
                    if (empty($variantRules)) {
                        return null;
                    }

                    return [
                        'id' => (int) $variant->id,
                        'name' => $variant->name,
                        'size_label' => $variant->size_label,
                        'pricing_options' => $variantRules,
                        'default_period_type' => $variantRules[0]['period_type'] ?? 'full_day',
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if (empty($variants)) {
                return null;
            }

            return [
                'id' => (int) $item->id,
                'name' => $item->name,
                'brand' => $item->brand,
                'model' => $item->model,
                'description' => $item->description,
                'variants' => $variants,
            ];
        })->filter();
    }

    private function variantPricingOptions(int $itemId, int $variantId, Collection $rules): array
    {
        $candidates = $rules->filter(function (array $rule) use ($itemId, $variantId) {
            $matchesVariant = isset($rule['variant_id']) && (int) $rule['variant_id'] === $variantId;
            $matchesItemFallback = empty($rule['variant_id']) && isset($rule['item_id']) && (int) $rule['item_id'] === $itemId;
            return $matchesVariant || $matchesItemFallback;
        })->sort(function (array $left, array $right) use ($variantId) {
            $leftSpecific = (int) (($left['variant_id'] ?? null) === $variantId);
            $rightSpecific = (int) (($right['variant_id'] ?? null) === $variantId);
            if ($leftSpecific !== $rightSpecific) {
                return $rightSpecific <=> $leftSpecific;
            }
            if (($left['priority'] ?? 100) !== ($right['priority'] ?? 100)) {
                return ($left['priority'] ?? 100) <=> ($right['priority'] ?? 100);
            }
            return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
        });

        $grouped = [];
        foreach ($candidates as $rule) {
            $periodType = $rule['period_type'];
            if (!isset($grouped[$periodType])) {
                $grouped[$periodType] = $this->mapPricingOption($rule);
            }
        }

        return array_values($grouped);
    }

    private function mapPricingOption(array $rule): array
    {
        $price = round((float) ($rule['price'] ?? 0), 2);
        $currency = strtoupper((string) ($rule['currency'] ?? 'CHF'));
        $periodType = (string) ($rule['period_type'] ?? 'full_day');
        $pricingMode = $this->normalizePricingMode($rule);

        return [
            'period_type' => $periodType,
            'pricing_mode' => $pricingMode,
            'price' => $price,
            'currency' => $currency,
            'label' => $this->pricingOptionLabel($price, $currency, $periodType, $pricingMode),
        ];
    }

    private function normalizePricingMode(array $rule): string
    {
        $mode = strtolower(trim((string) ($rule['pricing_mode'] ?? '')));
        if (in_array($mode, ['per_day', 'flat'], true)) {
            return $mode;
        }

        return in_array(($rule['period_type'] ?? ''), ['week', 'season'], true) ? 'flat' : 'per_day';
    }

    private function pricingOptionLabel(float $price, string $currency, string $periodType, string $pricingMode): string
    {
        $suffix = match ($periodType) {
            'half_day' => '/half-day',
            'week' => '/week',
            'season' => '/season',
            default => $pricingMode === 'flat' ? '' : '/day',
        };

        return trim(number_format($price, 2, '.', '') . ' ' . $currency . $suffix);
    }

    private function resolveClient(Request $request): Client
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $client = Client::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereHas('clientsSchools', fn ($query) => $query->where('school_id', $this->school->id))
            ->first();

        $payload = [
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
            'email' => $email,
            'phone' => trim((string) $request->input('phone')),
            'birth_date' => $request->input('birth_date'),
            'language1_id' => $this->resolveLanguageId((string) $request->input('language', app()->getLocale())),
        ];

        if ($client) {
            $updates = [];
            foreach (['first_name', 'last_name', 'phone', 'birth_date', 'language1_id'] as $field) {
                $incoming = $payload[$field] ?? null;
                if (empty($client->{$field}) && !empty($incoming)) {
                    $updates[$field] = $incoming;
                }
            }
            if (!empty($updates)) {
                $client->fill($updates);
                $client->save();
            }
            return $client;
        }

        $client = Client::create($payload);
        ClientsSchool::create([
            'client_id' => $client->id,
            'school_id' => $this->school->id,
            'accepts_newsletter' => (bool) $request->boolean('accepts_newsletter'),
        ]);

        return $client;
    }

    private function resolveLanguageId(string $preferredCode): ?int
    {
        $normalized = trim(mb_strtolower($preferredCode));
        if ($normalized !== '') {
            $language = Language::query()
                ->whereRaw('LOWER(code) = ?', [$normalized])
                ->orWhereRaw('LOWER(name) = ?', [$normalized])
                ->first();
            if ($language) {
                return (int) $language->id;
            }
        }

        return Language::query()->orderBy('id')->value('id');
    }

    private function buildReservationSummary(int $reservationId): array
    {
        $reservation = RentalReservation::query()
            ->with(['client:id,first_name,last_name,email,phone', 'pickupPoint:id,name,address', 'lines'])
            ->where('school_id', $this->school->id)
            ->findOrFail($reservationId);

        $lines = $reservation->lines->map(function ($line) {
            $meta = is_array($line->meta) ? $line->meta : [];
            $pricing = is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [];

            $variant = null;
            if (!empty($line->variant_id)) {
                $variant = RentalVariant::query()->find($line->variant_id, ['id', 'name', 'size_label']);
            }

            return [
                'id' => (int) $line->id,
                'item_id' => (int) ($line->item_id ?? 0),
                'variant_id' => (int) ($line->variant_id ?? 0),
                'variant_name' => $variant?->name,
                'size_label' => $variant?->size_label,
                'quantity' => (int) ($line->quantity ?? 0),
                'period_type' => $line->period_type,
                'unit_price' => (float) ($line->unit_price ?? 0),
                'line_total' => (float) ($line->line_total ?? 0),
                'pricing_mode' => $pricing['pricing_mode'] ?? null,
                'pricing_basis_key' => $pricing['pricing_basis_key'] ?? $line->period_type ?? $pricing['pricing_mode'] ?? null,
                'rental_days' => isset($pricing['rental_days']) ? (int) $pricing['rental_days'] : null,
            ];
        })->values()->all();

        return [
            'id' => (int) $reservation->id,
            'reference' => $reservation->reference,
            'status' => $reservation->status,
            'currency' => $reservation->currency ?? 'CHF',
            'start_date' => optional($reservation->start_date)->format('Y-m-d'),
            'end_date' => optional($reservation->end_date)->format('Y-m-d'),
            'start_time' => $reservation->start_time,
            'end_time' => $reservation->end_time,
            'subtotal' => (float) $reservation->subtotal,
            'total' => (float) $reservation->total,
            'items_count' => collect($lines)->sum('quantity'),
            'pickup_point' => $reservation->pickupPoint ? [
                'id' => (int) $reservation->pickupPoint->id,
                'name' => $reservation->pickupPoint->name,
                'address' => $reservation->pickupPoint->address,
            ] : null,
            'client' => $reservation->client ? [
                'id' => (int) $reservation->client->id,
                'first_name' => $reservation->client->first_name,
                'last_name' => $reservation->client->last_name,
                'email' => $reservation->client->email,
                'phone' => $reservation->client->phone,
            ] : null,
            'lines' => $lines,
        ];
    }
}
