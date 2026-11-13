<?php

declare(strict_types=1);

namespace App\Jobs\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Bir webhook gönderimi (bağımsız yaşam döngüsü: yeniden deneme). 2xx yanıt
 * başarıdır; değilse artan aralıklarla (1 dk, 5 dk, 30 dk, 2 sa, 6 sa) yeniden
 * denenir. Son deneme de başarısızsa gönderim "failed" olur. Adres art arda
 * çok kez başarısız olursa kapatılır.
 */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const BACKOFF = [60, 300, 1800, 7200, 21600];

    public int $tries = 6;

    public function __construct(
        public readonly string $deliveryId,
    ) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== WebhookDelivery::PENDING || ! $delivery->endpoint->active) {
            return;
        }

        $endpoint = $delivery->endpoint;
        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $status = null;
        $responseBody = null;

        try {
            $response = Http::withHeaders(WebhookService::headers($delivery, $body, $endpoint->secret))
                ->withBody($body, 'application/json')
                ->timeout(10)
                ->withoutRedirecting()
                ->post($endpoint->url);
            $status = $response->status();
            $responseBody = mb_substr($response->body(), 0, 2000);
            $ok = $response->successful();
            $error = $ok ? null : __('Yanıt kodu :status.', ['status' => $status]);
        } catch (ConnectionException $e) {
            $ok = false;
            $error = mb_substr($e->getMessage(), 0, 1000);
        }

        $attempts = $delivery->attempts + 1;
        $last = $attempts >= $this->tries;

        $delivery->forceFill([
            'attempts' => $attempts,
            'response_status' => $status,
            'response_body' => $responseBody,
            'error' => $error,
            'status' => $ok ? WebhookDelivery::SUCCEEDED : ($last ? WebhookDelivery::FAILED : WebhookDelivery::PENDING),
            'delivered_at' => $ok ? now() : null,
        ])->save();

        $this->recordOnEndpoint($endpoint, $ok);

        if (! $ok && ! $last) {
            $this->release(self::BACKOFF[$attempts - 1] ?? 21600);
        }
    }

    public function failed(?Throwable $e): void
    {
        WebhookDelivery::query()->whereKey($this->deliveryId)->where('status', WebhookDelivery::PENDING)
            ->update(['status' => WebhookDelivery::FAILED, 'error' => mb_substr((string) $e?->getMessage(), 0, 1000)]);
    }

    private function recordOnEndpoint(WebhookEndpoint $endpoint, bool $ok): void
    {
        $failures = $ok ? 0 : $endpoint->failure_count + 1;

        $endpoint->forceFill([
            'failure_count' => $failures,
            'last_delivery_at' => now(),
            'active' => $endpoint->active && $failures < WebhookEndpoint::DISABLE_AFTER_FAILURES,
        ])->save();
    }
}
