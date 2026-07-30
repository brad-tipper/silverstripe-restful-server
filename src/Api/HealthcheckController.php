<?php

namespace BradTipper\RestfulServer\Api;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

/**
 * Lightweight healthcheck endpoint that confirms the API is online.
 *
 * GET /api/healthcheck → {"status":"ok"}
 */
class HealthcheckController extends Controller
{
    use JsonResponseTrait;

    private static array $allowed_actions = [
        'index',
    ];

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        return $this->respond(['status' => 'ok']);
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->respond(['status' => 'ok']);
    }
}
