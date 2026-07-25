<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAgentContextAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('agent_context.enabled') !== true) {
            return $this->error(
                status: Response::HTTP_NOT_FOUND,
                code: 'not_found',
                message: 'Not found.',
            );
        }

        $configuredToken = config('agent_context.token');
        $minimumLength = max(32, (int) config('agent_context.minimum_token_length', 32));

        if (! is_string($configuredToken) || strlen($configuredToken) < $minimumLength) {
            return $this->error(
                status: Response::HTTP_SERVICE_UNAVAILABLE,
                code: 'agent_context_not_configured',
                message: 'The agent context API is enabled but does not have a valid server token.',
            );
        }

        $providedToken = $request->bearerToken();
        if (! is_string($providedToken)
            || ! hash_equals(hash('sha256', $configuredToken), hash('sha256', $providedToken))) {
            return $this->error(
                status: Response::HTTP_UNAUTHORIZED,
                code: 'invalid_bearer_token',
                message: 'A valid bearer token is required.',
                headers: ['WWW-Authenticate' => 'Bearer realm="Hospitrainity Agent Context"'],
            );
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Agent-Context-Version', '1.0.0');

        return $response;
    }

    /** @param array<string, string> $headers */
    private function error(int $status, string $code, string $message, array $headers = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status, array_merge([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ], $headers));
    }
}
