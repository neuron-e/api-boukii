<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalPricingRuleController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_pricing_rules', [
            'item_id' => $request->input('item_id'),
            'variant_id' => $request->input('variant_id'),
            'period_type' => $request->input('period_type'),
        ]);
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_pricing_rules', [
            'school_id', 'item_id', 'variant_id', 'period_type', 'price', 'currency', 'active',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_pricing_rules', $id, [
            'item_id', 'variant_id', 'period_type', 'price', 'currency', 'active',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_pricing_rules', $id);
    }
}

