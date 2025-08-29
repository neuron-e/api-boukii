<?php

namespace App\V5\Modules\Client\Controllers;

use App\Http\Controllers\Controller;
use App\V5\Modules\Client\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(private ClientService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $filters = $request->except(['page', 'limit', 'school_id']);
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);
        $clients = $this->service->getClients($schoolId, $filters, $page, $limit);

        return response()->json($clients->toArray());
    }

    public function show(int $clientId, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $client = $this->service->findClientById($clientId, $schoolId);

        return response()->json(['data' => $client->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $client = $this->service->createClient($request->all(), $schoolId);

        return response()->json(['data' => $client->toArray()], 201);
    }

    public function update(int $clientId, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $client = $this->service->updateClient($clientId, $request->all(), $schoolId);

        return response()->json(['data' => $client->toArray()]);
    }

    public function destroy(int $clientId, Request $request): JsonResponse
    {
        $schoolId = (int) $request->get('context_school_id');
        $deleted = $this->service->deleteClient($clientId, $schoolId);

        return response()->json(['deleted' => $deleted]);
    }

    public function storeUtilizador(int $clientId, Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function updateUtilizador(int $clientId, int $utilizadorId, Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroyUtilizador(int $clientId, int $utilizadorId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function storeSport(int $clientId, Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function updateSport(int $clientId, int $sportId, Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroySport(int $clientId, int $sportId): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
