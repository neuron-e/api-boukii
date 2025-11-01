<?php

namespace App\Http\Requests\API;

use InfyOm\Generator\Request\APIRequest;

/**
 * Request para actualizar descuentos globales de curso
 *
 * Similar a CreateCourseDiscountAPIRequest pero con campos opcionales
 * ya que se permite actualización parcial (PATCH).
 */
class UpdateCourseDiscountAPIRequest extends APIRequest
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
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'discount_type' => 'sometimes|required|in:percentage,fixed_amount',
            'discount_value' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    // Si es porcentaje, validar que no sea mayor a 100
                    $discountType = $this->input('discount_type');

                    // Si no se está actualizando el tipo, obtenerlo del descuento existente
                    if (!$discountType && $this->route('id')) {
                        $discount = \App\Models\CourseDiscount::find($this->route('id'));
                        $discountType = $discount ? $discount->discount_type : null;
                    }

                    if ($discountType === 'percentage' && $value > 100) {
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
