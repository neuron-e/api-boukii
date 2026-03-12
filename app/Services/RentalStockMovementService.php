<?php

namespace App\Services;

use App\Models\RentalStockMovement;
use Illuminate\Support\Facades\Schema;

class RentalStockMovementService
{
    public function log(array $payload): void
    {
        if (!Schema::hasTable('rental_stock_movements')) {
            return;
        }

        if (empty($payload['school_id']) || empty($payload['movement_type'])) {
            return;
        }

        RentalStockMovement::create([
            'school_id' => (int) $payload['school_id'],
            'rental_reservation_id' => isset($payload['rental_reservation_id']) ? (int) $payload['rental_reservation_id'] : null,
            'rental_reservation_line_id' => isset($payload['rental_reservation_line_id']) ? (int) $payload['rental_reservation_line_id'] : null,
            'rental_unit_id' => isset($payload['rental_unit_id']) ? (int) $payload['rental_unit_id'] : null,
            'variant_id' => isset($payload['variant_id']) ? (int) $payload['variant_id'] : null,
            'item_id' => isset($payload['item_id']) ? (int) $payload['item_id'] : null,
            'warehouse_id_from' => isset($payload['warehouse_id_from']) ? (int) $payload['warehouse_id_from'] : null,
            'warehouse_id_to' => isset($payload['warehouse_id_to']) ? (int) $payload['warehouse_id_to'] : null,
            'movement_type' => (string) $payload['movement_type'],
            'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
            'reason' => isset($payload['reason']) ? (string) $payload['reason'] : null,
            'payload' => is_array($payload['payload'] ?? null) ? $payload['payload'] : null,
            'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : auth()->id(),
            'occurred_at' => $payload['occurred_at'] ?? now(),
        ]);
    }
}

