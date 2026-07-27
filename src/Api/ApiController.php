<?php

namespace BradTipper\RestfulServer\Api;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;

abstract class ApiController extends Controller
{
    use CurrentMemberTrait;
    use JsonResponseTrait;

    private static array $allowed_actions = ['index'];

    /** Actions which require a valid bearer token on this controller. */
    private static array $authenticated_actions = [];

    public function handleRequest(HTTPRequest $request): HTTPResponse
    {
        $this->setRequest($request);
        $action = (string) $request->param('Action');
        if (!in_array(strtolower($action), $this->allowedActions() ?? [], true)) {
            return $this->respondError('Unknown API action', 404);
        }

        try {
            if ($this instanceof RequiresAuth) {
                if (in_array($action, $this->config()->get('authenticated_actions') ?? [], true)) {
                    $this->currentMember();
                }
            }

            return $this->$action($request);
        } catch (AuthException $e) {
            return $this->respondError($e->getMessage(), 401);
        }
    }

    protected function jsonResponse(mixed $data, int $status = 200): HTTPResponse
    {
        return $this->respond($data, $status);
    }

    protected function methodNotAllowed(string ...$allowedMethods): HTTPResponse
    {
        $response = $this->respondError('Method not allowed', 405);
        $response->addHeader('Allow', implode(', ', $allowedMethods));
        return $response;
    }

    /** Accept both browser form posts and JSON clients. */
    protected function requestData(HTTPRequest $request): array
    {
        $contentType = strtolower((string) $request->getHeader('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) $request->getBody(), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $request->postVars();
    }

    /**
     * Standard pagination helper.
     * Returns [$page, $perPage, $offset].
     */
    protected function pagination(HTTPRequest $request, int $defaultPerPage = 100): array
    {
        $page = max(1, (int) ($request->getVar('page') ?: 1));
        $perPage = max(1, min(100, (int) ($request->getVar('perPage') ?: $defaultPerPage)));
        return [$page, $perPage, ($page - 1) * $perPage];
    }

    /**
     * Standard pagination response envelope.
     */
    protected function paginationArray(int $total, int $page, int $perPage): array
    {
        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hasMore' => $page * $perPage < $total,
        ];
    }
}