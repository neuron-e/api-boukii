<?php

namespace App\V5\Requests\Season;

use App\V5\Requests\BaseV5Request;
use App\V5\Validation\V5ValidationRules;

/**
 * @deprecated Use App\\Http\\Requests\\API\\V5\\CreateSeasonV5Request instead.
 */
class CreateSeasonRequest extends BaseV5Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return array_merge(
            V5ValidationRules::seasonRules(),
            V5ValidationRules::localizationRules()
        );
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateSeasonDateRange($validator);
            $this->validateOverlappingSeasons($validator);
        });
    }

    /**
     * Validate season date range
     */
    protected function validateSeasonDateRange($validator): void
    {
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        if ($startDate && $endDate && $startDate >= $endDate) {
            $validator->errors()->add('end_date', __('exceptions.season.invalid_date_range'));
        }
    }

    /**
     * Validate overlapping seasons for the same school
     */
    protected function validateOverlappingSeasons($validator): void
    {
        $schoolId = $this->input('school_id');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        if (! $schoolId || ! $startDate || ! $endDate) {
            return;
        }

        $overlapping = \App\V5\Models\Season::where('school_id', $schoolId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->exists();

        if ($overlapping) {
            $validator->errors()->add('start_date', __('exceptions.season.overlapping_seasons'));
        }
    }

    /**
     * Get data to be validated from the request.
     */
    public function validationData(): array
    {
        return array_merge(
            $this->all(),
            $this->route() ? $this->route()->parameters() : []
        );
    }
}
