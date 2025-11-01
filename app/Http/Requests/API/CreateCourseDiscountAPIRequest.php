<?php

namespace App\Http\Requests\API;

use InfyOm\Generator\Request\APIRequest;

/**
 * Request para crear descuentos globales de curso
 *
 * Validaciones:
 * - name: requerido, máximo 100 caracteres
 * - discount_type: requerido, percentage o fixed_amount
 * - discount_value: requerido, numérico, mínimo 0
 * - min_days: opcional, entero, mínimo 1
 * - valid_from y valid_to: fechas opcionales
 * - priority: entero opcional
 * - active: booleano opcional
 */
class CreateCourseDiscountAPIRequest extends APIRequest
{
    /**
     * Determinar si el usuario está autorizado para esta petición
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en Policies o middleware
    }

    /**
     * Reglas de validación
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    // Si es porcentaje, validar que no sea mayor a 100
                    if ($this->input('discount_type') === 'percentage' && $value > 100) {
                        $fail('El porcentaje de descuento no puede ser mayor a 100.');
                    }
                },
            ],
            'min_days' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date|date_format:Y-m-d',
            'valid_to' => 'nullable|date|date_format:Y-m-d|after_or_equal:valid_from',
            'priority' => 'nullable|integer',
            'active' => 'nullable|boolean',
        ];
    }

    /**
     * Mensajes personalizados de validación
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del descuento es requerido.',
            'name.max' => 'El nombre no puede exceder 100 caracteres.',
            'discount_type.required' => 'El tipo de descuento es requerido.',
            'discount_type.in' => 'El tipo de descuento debe ser "percentage" o "fixed_amount".',
            'discount_value.required' => 'El valor del descuento es requerido.',
            'discount_value.numeric' => 'El valor del descuento debe ser numérico.',
            'discount_value.min' => 'El valor del descuento debe ser mayor o igual a 0.',
            'min_days.integer' => 'El mínimo de días debe ser un número entero.',
            'min_days.min' => 'El mínimo de días debe ser al menos 1.',
            'valid_from.date' => 'La fecha de inicio debe ser una fecha válida.',
            'valid_to.date' => 'La fecha de fin debe ser una fecha válida.',
            'valid_to.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
        ];
    }

    /**
     * Preparar datos antes de validación
     */
    protected function prepareForValidation(): void
    {
        // Convertir active a booleano si viene como string
        if ($this->has('active')) {
            $this->merge([
                'active' => filter_var($this->active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
    }
}
