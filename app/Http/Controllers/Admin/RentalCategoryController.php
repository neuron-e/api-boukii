<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalCategoryController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_categories');
    }

    public function show(Request $request, int $id)
    {
        return $this->showByTable($request, 'rental_categories', $id);
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_categories', [
            'school_id', 'name', 'slug', 'icon', 'active', 'sort_order',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_categories', $id, [
            'name', 'slug', 'icon', 'active', 'sort_order',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_categories', $id);
    }
}

