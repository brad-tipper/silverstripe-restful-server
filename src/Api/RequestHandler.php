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
            case 'schema':
                return SchemaController::create()->handleRequest($request);
            default:
                // Treat as a resource name for ResourceController.
                // We pass the resource name via a request attribute since
                // param() is read-only. ResourceController reads it from $request->param('ResourceName').
                $request->setRouteParams(array_merge(
                    $request->routeParams() ?: [],
                    ['ResourceName' => $handler]
                ));
                return ResourceController::create()->handleRequest($request);
        }
    }
}