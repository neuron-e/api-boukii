<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalVariantServiceController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_variant_services', [
            'variant_id' => $request->input('variant_id'),
            'active' => $request->input('active'),
        ]);
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_variant_services', $id);
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_variant_services', [
            'school_id', 'variant_id', 'name', 'description', 'price', 'currency', 'active', 'sort_order',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_variant_services', $id, [
            'variant_id', 'name', 'description', 'price', 'currency', 'active', 'sort_order',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_variant_services', $id);
    }
}

