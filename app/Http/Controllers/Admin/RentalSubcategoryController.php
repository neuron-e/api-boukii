<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalSubcategoryController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_subcategories', [
            'category_id' => $request->input('category_id'),
        ]);
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_subcategories', $id);
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_subcategories', [
            'school_id', 'category_id', 'name', 'slug', 'active', 'sort_order',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_subcategories', $id, [
            'category_id', 'name', 'slug', 'active', 'sort_order',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_subcategories', $id);
    }
}

