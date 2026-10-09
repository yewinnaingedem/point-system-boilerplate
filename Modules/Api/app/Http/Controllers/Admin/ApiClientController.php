<?php

namespace Modules\Api\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Api\Gateway\GatewayMethods;
use Modules\Api\Http\Requests\ApiClientRequest;
use Modules\Api\Models\ApiClient;
use Modules\Api\Services\ApiClientService;

/**
 * Systems allowed to call the signed gateway. The secret key is shown once, right after it is
 * created or rotated (flashed to the next page only).
 */
class ApiClientController extends Controller
{
    public function __construct(private readonly ApiClientService $clients) {}

    public function index(GatewayMethods $methods): View
    {
        return view('api::clients.index', [
            'clients' => ApiClient::query()->orderBy('name')->get(),
            'methods' => $methods->names(),
            'secret' => session('api_client_secret'),
        ]);
    }

    public function create(): View
    {
        return view('api::clients.form', ['client' => new ApiClient(['is_active' => true])]);
    }

    public function store(ApiClientRequest $request): RedirectResponse
    {
        [$client, $secret] = $this->clients->create($request->validated());

        return $this->withSecret($client, $secret, __('API client ":name" created.', ['name' => $client->name]));
    }

    public function edit(ApiClient $client): View
    {
        return view('api::clients.form', ['client' => $client]);
    }

    public function update(ApiClientRequest $request, ApiClient $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('admin.api-clients.index')->with('success', __('API client ":name" saved.', ['name' => $client->name]));
    }

    public function rotate(ApiClient $client): RedirectResponse
    {
        $secret = $this->clients->rotate($client);

        return $this->withSecret($client, $secret, __('New secret key for ":name". The old key no longer works.', ['name' => $client->name]));
    }

    public function destroy(ApiClient $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('admin.api-clients.index')->with('success', __('API client ":name" deleted.', ['name' => $client->name]));
    }

    private function withSecret(ApiClient $client, string $secret, string $message): RedirectResponse
    {
        return redirect()->route('admin.api-clients.index')
            ->with('success', $message)
            ->with('api_client_secret', ['name' => $client->name, 'app_id' => $client->app_id, 'secret' => $secret]);
    }
}
