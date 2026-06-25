<?php

namespace App\Http\Controllers;

use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        $clients = Client::latest()->paginate(15);

        return $this->success($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return $this->success($client, 'Client created successfully', 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load('cases');

        return $this->success($client);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return $this->success($client->fresh(), 'Client updated successfully');
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return $this->success(null, 'Client deleted successfully');
    }
}
