<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;

class RentalPickupPointController extends RentalBaseController
{
    public function index(Request $request)
    {
        return $this->indexByTable($request, 'rental_pickup_points');
    }

    public function store(Request $request)
    {
        return $this->storeByTable($request, 'rental_pickup_points', [
            'school_id', 'name', 'code', 'address', 'active',
        ]);
    }

    public function update(Request $request, int $id)
    {
        return $this->updateByTable($request, 'rental_pickup_points', $id, [
            'name', 'code', 'address', 'active',
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        return $this->destroyByTable($request, 'rental_pickup_points', $id);
    }
}

