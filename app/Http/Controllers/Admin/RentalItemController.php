<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalItemController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_items', [
            'category_id' => $request->input('category_id'),
        ]);
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_items', $id);
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_items', [
            'school_id', 'category_id', 'name', 'brand', 'model', 'description', 'active',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_items', $id, [
            'category_id', 'name', 'brand', 'model', 'description', 'active',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_items', $id);
    }
}

