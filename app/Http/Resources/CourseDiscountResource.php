<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear respuestas de CourseDiscount API
 *
 * Transforma el modelo CourseDiscount en un formato JSON consistente
 * para las respuestas de la API.
 */
class CourseDiscountResource extends JsonResource
{
    /**
     * Transformar el recurso en un array
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'min_days' => $this->min_days,
            'valid_from' => $this->valid_from?->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'priority' => $this->priority,
            'active' => $this->active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Campos calculados útiles para el frontend
            'display_value' => $this->getDisplayValue(),
            'display_text' => $this->getDisplayText(),
            'is_currently_valid' => $this->isCurrentlyValid(),
        ];
    }

    /**
     * Obtener el valor del descuento formateado para mostrar
     *
     * @return string
     */
    private function getDisplayValue(): string
    {
        if ($this->discount_type === 'percentage') {
            return "{$this->discount_value}%";
        }

        return "CHF {$this->discount_value}";
    }

    /**
     * Obtener el texto completo del descuento para mostrar en UI
     *
     * @return string
     */
    private function getDisplayText(): string
    {
        $valueText = $this->getDisplayValue();
        $conditionsText = [];

        if ($this->min_days) {
            $conditionsText[] = "al reservar {$this->min_days}+ días";
        }

        $conditions = !empty($conditionsText) ? ' ' . implode(' y ', $conditionsText) : '';

        return "Descuento {$valueText}{$conditions}";
    }

    /**
     * Verificar si el descuento está actualmente válido (dentro del rango de fechas)
     *
     * @return bool
     */
    private function isCurrentlyValid(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = now()->format('Y-m-d');

        if ($this->valid_from && $now < $this->valid_from->format('Y-m-d')) {
            return false;
        }

        if ($this->valid_to && $now > $this->valid_to->format('Y-m-d')) {
            return false;
        }

        return true;
    }
}
