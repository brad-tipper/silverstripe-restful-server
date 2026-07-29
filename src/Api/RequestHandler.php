<?php

namespace BradTipper\RestfulServer\Api;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Top-level request router for the RESTful API.
 *
 * Routes:
 *   /api/auth/*          → AuthController
 *   /api/schema          → SchemaController
 *   /api/{resource}/*    → ResourceController (auto-discovered)
 */
class RequestHandler extends Controller
{
    use JsonResponseTrait;

    private static array $allowed_actions = [];

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $handler = strtolower((string) $request->param('ApiHandler'));

        switch ($handler) {
            case 'auth':
                return AuthController::create()->handleRequest($request);
            case 'healthcheck':
                return HealthcheckController::create()->handleRequest($request);
            default:
                $this->normaliseResourceRoute($request, $handler);
                return ResourceController::create()->handleRequest($request);
        }
    }

    private function normaliseResourceRoute(HTTPRequest $request, string $resource): void
    {
        $route = $request->routeParams() ?: [];
        $pathAction = trim((string) ($route['Action'] ?? ''));
        $method = strtoupper($request->httpMethod());

        if ($pathAction === '') {
            $action = match ($method) {
                'GET' => 'index',
                'POST' => 'create',
                default => '',
            };
        } else {
            $route['ID'] = $pathAction;
            $action = match ($method) {
                'GET' => 'show',
                'PATCH' => 'update',
                'DELETE' => 'delete',
                default => '',
            };
        }

        $route['ResourceName'] = $resource;
        $route['Action'] = $action;
        $request->setRouteParams($route);
    }
}
