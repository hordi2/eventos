<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Support\Assistant\McpTools;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serveur MCP (D4) : il permet à l'organisateur de brancher l'assistant IA
 * de son choix sur son compte Itaza, avec sa propre clé API.
 *
 * Le protocole est du JSON-RPC 2.0 ; trois méthodes suffisent à un client
 * MCP — initialize, tools/list, tools/call. Tout est en lecture : un
 * assistant répond à des questions sur l'événement, il ne crée ni ne
 * supprime rien.
 *
 * Aucun modèle de langage n'est appelé ici : c'est l'assistant de
 * l'organisateur qui raisonne, Itaza ne fait que lui ouvrir ses données.
 */
final class McpController extends Controller
{
    private const PROTOCOL_VERSION = '2025-06-18';

    public function __construct(
        private readonly McpTools $tools,
    ) {}

    public function handle(Request $request): JsonResponse|Response
    {
        $method = (string) $request->input('method');
        $id = $request->input('id');

        // Une notification (sans identifiant) n'attend pas de réponse.
        if ($id === null) {
            return response()->noContent();
        }

        return match ($method) {
            'initialize' => $this->result($id, [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'itaza-invitation', 'version' => '1.0.0'],
            ]),
            'tools/list' => $this->result($id, ['tools' => $this->tools->definitions()]),
            'tools/call' => $this->callTool($request, $id),
            'ping' => $this->result($id, []),
            default => $this->error($id, -32601, "Méthode inconnue : {$method}."),
        };
    }

    private function callTool(Request $request, mixed $id): JsonResponse
    {
        $name = (string) $request->input('params.name');
        $arguments = $request->input('params.arguments', []);

        if (! is_array($arguments)) {
            return $this->error($id, -32602, 'Les arguments doivent former un objet.');
        }

        $known = array_column($this->tools->definitions(), 'name');

        if (! in_array($name, $known, true)) {
            return $this->error($id, -32602, "Outil inconnu : {$name}.");
        }

        $text = $this->tools->call($name, $arguments, $this->organization());

        return $this->result($id, ['content' => [['type' => 'text', 'text' => $text]]]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function result(mixed $id, array $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function error(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]]);
    }

    private function organization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
