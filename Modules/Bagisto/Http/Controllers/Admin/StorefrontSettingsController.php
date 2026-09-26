<?php

namespace Modules\Bagisto\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Bagisto\Services\StorefrontFeatureService;

/**
 * İş Ortakları › Bayi Portalı: Bagisto mağazasındaki özelliklerin açılıp kapandığı yönetim sayfası.
 */
class StorefrontSettingsController extends Controller
{
    public function __construct(private StorefrontFeatureService $features) {}

    public function index(): Response
    {
        $values = $this->features->values();

        return Inertia::render('Bagisto::StorefrontSettings', [
            'features' => collect($this->features->catalog())
                ->map(fn (array $feature, string $key) => [
                    'key' => $key,
                    'group' => $feature['group'],
                    'label' => $feature['label'],
                    'description' => $feature['description'],
                    'enabled' => $values[$key],
                ])
                ->values(),
            'sync' => $this->features->syncStatus(),
            'storeUrl' => config('bagisto.sync.base_url'),
            'configured' => filled(config('bagisto.sync.base_url')) && filled(config('bagisto.sync.webhook_secret')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $keys = array_keys($this->features->catalog());

        $data = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ]);

        $this->features->update(collect($data['features'])->only($keys)->all());

        return back();
    }

    public function sync(): RedirectResponse
    {
        $this->features->push();

        return back();
    }
}
