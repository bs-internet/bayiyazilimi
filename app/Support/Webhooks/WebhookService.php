<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

use App\Exceptions\Shop\ShopException;
use App\Jobs\Webhooks\DeliverWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\BayiYazilimi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Webhook kaydı ve gönderimi. Olay olunca abone olan her etkin adres için bir
 * gönderim kaydı açılır ve kuyruğa verilir (DeliverWebhook). Gövde:
 * {id, event, created_at, data}. İmza başlığı:
 * `X-Bayi-Signature: t={unix zaman},v1={hex(HMAC-SHA256(secret, "{t}.{gövde}"))}`.
 */
final class WebhookService
{
    /**
     * @param  list<string>  $events
     * @return array{endpoint: WebhookEndpoint, secret: string}
     */
    public function create(string $url, array $events, ?string $description, ?User $causer): array
    {
        self::assertUrl($url);
        $events = array_values(array_unique($events));

        foreach ($events as $event) {
            if ($event !== '*' && ! in_array($event, WebhookEvents::keys(), true)) {
                throw new ShopException(__('Bilinmeyen webhook olayı: :event.', ['event' => $event]));
            }
        }

        $secret = 'whsec_'.Str::random(40);
        $endpoint = WebhookEndpoint::query()->create([
            'url' => $url,
            'secret' => $secret,
            'events' => $events,
            'description' => $description,
            'active' => true,
            'created_by' => $causer?->id,
        ]);

        return ['endpoint' => $endpoint, 'secret' => $secret];
    }

    /**
     * Olayı abone olan etkin adreslere gönderir (kuyrukta). Veri, olay anındaki hâldir.
     *
     * @param  array<string, mixed>  $data
     */
    public function dispatch(string $event, array $data): int
    {
        $endpoints = WebhookEndpoint::query()->where('active', true)->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->listensTo($event));

        foreach ($endpoints as $endpoint) {
            $this->queue($endpoint, $event, $data);
        }

        return $endpoints->count();
    }

    /**
     * Tek adrese gönderim (deneme gönderimi dahil). İş, kayıt veritabanına yazıldıktan sonra kuyruğa girer.
     *
     * @param  array<string, mixed>  $data
     */
    public function queue(WebhookEndpoint $endpoint, string $event, array $data): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $event,
            'payload' => [],
        ]);
        $delivery->forceFill(['payload' => [
            'id' => $delivery->id,
            'event' => $event,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ]])->save();

        DB::afterCommit(fn () => DeliverWebhook::dispatch($delivery->id));

        return $delivery;
    }

    /** @return array<string, string> */
    public static function headers(WebhookDelivery $delivery, string $body, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'BayiYazilimi-Webhook/'.BayiYazilimi::VERSION,
            'X-Bayi-Event' => $delivery->event,
            'X-Bayi-Delivery' => $delivery->id,
            'X-Bayi-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
    }

    /**
     * Yalnız herkese açık https adresi (yerel geliştirmede ve testte http de olur).
     * Özel / ayrılmış IP'lere giden adres reddedilir (sunucu içi ağa istek açılmasın).
     */
    public static function assertUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (filter_var($url, FILTER_VALIDATE_URL) === false || $host === ''
            || ! ($scheme === 'https' || ($scheme === 'http' && app()->environment('local', 'testing')))) {
            throw new ShopException(__('Webhook adresi https:// ile başlayan geçerli bir adres olmalı.'));
        }

        if (app()->environment('local', 'testing')) {
            return;
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);

        if ($addresses === []) {
            throw new ShopException(__('Webhook adresinin sunucusu bulunamadı.'));
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new ShopException(__('Webhook adresi herkese açık bir sunucuya gitmeli.'));
            }
        }
    }
}
