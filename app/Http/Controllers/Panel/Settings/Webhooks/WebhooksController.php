<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel\Settings\Webhooks;

use App\Http\Controllers\Api\V1\WebhooksController as ApiWebhooks;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookEvents;
use App\Support\Webhooks\WebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ayarlar → Webhook'lar: olay olunca bildirim alacak adresler. İmza anahtarı
 * yalnız ekleme sonrası bir kez gösterilir. Aynı işler REST API'de de vardır
 * (/api/v1/webhooks).
 */
class WebhooksController extends Controller
{
    public function index(Request $request): Response
    {
        $endpoints = WebhookEndpoint::query()->latest()->get();
        $deliveries = WebhookDelivery::query()->whereIn('webhook_endpoint_id', $endpoints->modelKeys())->latest()->limit(200)->get()->groupBy('webhook_endpoint_id');

        return Inertia::render('panel/settings/webhooks/index', [
            'webhooks' => $endpoints->map(fn (WebhookEndpoint $e) => [
                ...ApiWebhooks::endpoint($e),
                'deliveries' => ($deliveries->get($e->id) ?? collect())->take(10)->map(fn (WebhookDelivery $d) => ApiWebhooks::delivery($d))->values()->all(),
            ])->values()->all(),
            'events' => collect(WebhookEvents::all())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'plainSecret' => $request->session()->get('webhook_secret'),
        ]);
    }

    public function store(Request $request, WebhookService $webhooks): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(['*', ...WebhookEvents::keys()])],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['url' => __('adres'), 'events' => __('olaylar')]);

        ['secret' => $secret] = $webhooks->create($data['url'], $data['events'], $data['description'] ?? null, $request->user());

        return to_route('panel.settings.webhooks.index')
            ->with('webhook_secret', $secret)
            ->with('toast', ['type' => 'success', 'message' => __('Webhook eklendi. İmza anahtarını şimdi kopyalayın.')]);
    }

    /** Açma / kapatma: hedef durumu alır. */
    public function update(Request $request, WebhookEndpoint $webhook): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];
        $webhook->forceFill(['active' => (bool) $active, 'failure_count' => $active ? 0 : $webhook->failure_count])->save();

        return back()->with('toast', ['type' => 'success', 'message' => $active ? __('Webhook açıldı.') : __('Webhook kapatıldı.')]);
    }

    public function test(WebhookEndpoint $webhook, WebhookService $webhooks): RedirectResponse
    {
        $webhooks->queue($webhook, WebhookEvents::PING, ['message' => __('Bayi Yazılımı webhook denemesi.')]);

        return back()->with('toast', ['type' => 'info', 'message' => __('Deneme gönderimi kuyruğa alındı; sonuç gönderimler listesinde görünür.')]);
    }

    public function destroy(WebhookEndpoint $webhook): RedirectResponse
    {
        $webhook->delete();

        return back()->with('toast', ['type' => 'success', 'message' => __('Webhook silindi.')]);
    }
}
