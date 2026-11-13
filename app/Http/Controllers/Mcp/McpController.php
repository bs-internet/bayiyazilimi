<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Support\BayiYazilimi;
use App\Support\Branding;
use App\Support\Mcp\McpTools;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * MCP (Model Context Protocol) sunucusu, Streamable HTTP taşıma (oturumsuz,
 * yalnız JSON yanıt). İstemci JSON-RPC 2.0 iletisini POST eder; kimlik REST
 * API ile aynı Bearer anahtarıdır. Desteklenen yöntemler: initialize, ping,
 * tools/list, tools/call. Araç çağrısı REST API'ye iç istek olarak iletilir
 * (aynı doğrulama, yetki ve yanıt); yetkisi olmayan araç listelenmez.
 */
class McpController extends Controller
{
    public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __invoke(Request $request): JsonResponse|Response
    {
        if (! $request->isMethod('post')) {
            // Sunucudan istemciye akış (SSE) ve oturum yoktur.
            return response('', 405, ['Allow' => 'POST']);
        }

        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(self::error(null, -32700, 'Parse error'), 400);
        }

        $batch = array_is_list($payload);
        $responses = [];

        foreach ($batch ? $payload : [$payload] as $message) {
            $response = $this->handle(is_array($message) ? $message : [], $request);

            if ($response !== null) {
                $responses[] = $response;
            }
        }

        if ($responses === []) {
            return response('', 202);
        }

        return response()->json($batch ? $responses : $responses[0]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function handle(array $message, Request $request): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;
        $isNotification = ! array_key_exists('id', $message);

        if (($message['jsonrpc'] ?? null) !== '2.0' || ! is_string($method)) {
            return $isNotification ? null : self::error($id, -32600, 'Invalid Request');
        }

        if ($isNotification) {
            return null; // notifications/initialized, notifications/cancelled …
        }

        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($method) {
            'initialize' => self::result($id, $this->initialize($params)),
            'ping' => self::result($id, new \stdClass),
            'tools/list' => self::result($id, ['tools' => $this->tools($request)]),
            'tools/call' => $this->call($id, $params, $request),
            default => self::error($id, -32601, 'Method not found: '.$method),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function initialize(array $params): array
    {
        $requested = (string) ($params['protocolVersion'] ?? '');

        return [
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'bayiyazilimi', 'title' => Branding::name(), 'version' => BayiYazilimi::VERSION],
            'instructions' => __('Bayi Yazılımı B2B sistemi: ürün, stok, fiyat, sipariş ve cari hesap araçları. Tutarlar string olarak döner; ürün fiyatları KDV hariç ve ürünün para birimindedir, sipariş ve cari tutarları ana para birimindedir. Yazan araçlar gerçek kayıt oluşturur; kullanıcıya sormadan çağırmayın.'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tools(Request $request): array
    {
        $list = [];

        foreach (McpTools::all() as $name => $tool) {
            if (! $this->allowed($tool, $request)) {
                continue;
            }

            $list[] = [
                'name' => $name,
                'title' => $tool['title'],
                'description' => $tool['description'],
                'inputSchema' => $tool['schema'],
                'annotations' => ['title' => $tool['title'], 'readOnlyHint' => $tool['readOnly'], 'destructiveHint' => false, 'openWorldHint' => false],
            ];
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(mixed $id, array $params, Request $request): array
    {
        $name = (string) ($params['name'] ?? '');
        $tool = McpTools::all()[$name] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($tool === null || ! $this->allowed($tool, $request)) {
            return self::error($id, -32602, 'Unknown tool: '.$name);
        }

        $call = ($tool['request'])($arguments);
        [$status, $body] = $this->forward($request, $call[0], $call[1], $call[2] ?? []);
        $text = $body === '' ? '{}' : $body;
        $decoded = json_decode($text, true);

        return self::result($id, array_filter([
            'content' => [['type' => 'text', 'text' => $text]],
            'structuredContent' => is_array($decoded) && ! array_is_list($decoded) ? $decoded : null,
            'isError' => $status >= 400,
        ], fn ($v) => $v !== null));
    }

    /** @param  array{ability: ?string}  $tool */
    private function allowed(array $tool, Request $request): bool
    {
        $token = $request->user()?->currentAccessToken();

        return $tool['ability'] === null || ($token !== null && $token->can($tool['ability']));
    }

    /**
     * REST API'ye iç istek: aynı anahtar, JSON gövde. Ana isteğin bağlamı geri yüklenir.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: string}
     */
    private function forward(Request $request, string $method, string $path, array $data): array
    {
        $uri = '/api/'.ltrim($path, '/');
        $sub = $method === 'GET'
            ? Request::create($uri.($data === [] ? '' : '?'.http_build_query($data)), 'GET')
            : Request::create($uri, $method, server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode($data));

        $sub->headers->set('Authorization', (string) $request->header('Authorization'));
        $sub->headers->set('Accept', 'application/json');
        $sub->server->set('REMOTE_ADDR', $request->ip());

        $app = app();
        $original = $app->make('request');

        try {
            $response = $app->make(Kernel::class)->handle($sub);

            return [$response->getStatusCode(), (string) $response->getContent()];
        } catch (Throwable $e) {
            report($e);

            return [500, (string) json_encode(['message' => __('Araç çalıştırılamadı.')])];
        } finally {
            $app->instance('request', $original);
        }
    }

    /** @return array<string, mixed> */
    private static function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /** @return array<string, mixed> */
    private static function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
