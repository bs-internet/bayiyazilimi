<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookEvents;
use App\Support\Webhooks\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * REST API webhook yönetimi (webhooks:manage): adres ekleme (imza anahtarı
 * yalnız bu yanıtta döner), listeleme, açma / kapatma, silme, deneme gönderimi
 * ve son gönderimler.
 */
class WebhooksController extends Controller
{
    public function events(): JsonResponse
    {
        return response()->json(['data' => collect(WebhookEvents::all())->map(fn (string $label, string $key) => ['event' => $key, 'label' => $label])->values()]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => WebhookEndpoint::query()->latest()->get()->map(fn (WebhookEndpoint $e) => self::endpoint($e))->values()]);
    }

    public function store(Request $request, WebhookService $webhooks): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2000'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(['*', ...WebhookEvents::keys()])],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['url' => __('adres'), 'events' => __('olaylar')]);

        ['endpoint' => $endpoint, 'secret' => $secret] = $webhooks->create($data['url'], $data['events'], $data['description'] ?? null, $request->user());

        return response()->json(['data' => [...self::endpoint($endpoint), 'secret' => $secret]], 201);
    }

    public function update(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $data = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(['*', ...WebhookEvents::keys()])],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        // Yeniden açılan adresin başarısız sayacı sıfırlanır.
        $webhook->fill($data)->forceFill(isset($data['active']) && $data['active'] ? ['failure_count' => 0] : [])->save();

        return response()->json(['data' => self::endpoint($webhook)]);
    }

    public function destroy(WebhookEndpoint $webhook): Response
    {
        $webhook->delete();

        return response()->noContent();
    }

    /** Deneme gönderimi: `ping` olayı hemen kuyruğa girer. */
    public function ping(WebhookEndpoint $webhook, WebhookService $webhooks): JsonResponse
    {
        $delivery = $webhooks->queue($webhook, WebhookEvents::PING, ['message' => __('Bayi Yazılımı webhook denemesi.')]);

        return response()->json(['data' => self::delivery($delivery->fresh() ?? $delivery)], 202);
    }

    public function deliveries(Request $request, WebhookEndpoint $webhook): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        return response()->json(['data' => $webhook->deliveries()->latest()->limit($limit)->get()->map(fn (WebhookDelivery $d) => self::delivery($d))->values()]);
    }

    /** @return array<string, mixed> */
    public static function endpoint(WebhookEndpoint $e): array
    {
        return [
            'id' => $e->id,
            'url' => $e->url,
            'events' => $e->events,
            'description' => $e->description,
            'active' => $e->active,
            'failure_count' => $e->failure_count,
            'last_delivery_at' => $e->last_delivery_at?->toIso8601String(),
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function delivery(WebhookDelivery $d): array
    {
        return [
            'id' => $d->id,
            'event' => $d->event,
            'status' => $d->status,
            'attempts' => $d->attempts,
            'response_status' => $d->response_status,
            'error' => $d->getAttribute('error'),
            'delivered_at' => $d->delivered_at?->toIso8601String(),
            'created_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
