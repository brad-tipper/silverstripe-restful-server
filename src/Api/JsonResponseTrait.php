<?php

namespace BradTipper\RestfulServer\Api;

use SilverStripe\Control\HTTPResponse;

trait JsonResponseTrait
{
    protected function respond(mixed $data, int $status = 200): HTTPResponse
    {
        $response = new HTTPResponse();
        $response->setBody(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');
        $response->setStatusCode($status);
        return $response;
    }

    protected function respondError(string $message, int $status = 400, array $details = []): HTTPResponse
    {
        $payload = ['error' => $message];
        if ($details !== []) {
            $payload['details'] = $details;
        }
        return $this->respond($payload, $status);
    }

    protected function respondValidationError(string $message, array $fieldErrors = []): HTTPResponse
    {
        return $this->respondError($message, 422, ['errors' => $fieldErrors]);
    }

    protected function respondForbidden(string $message = 'Forbidden'): HTTPResponse
    {
        return $this->respondError($message, 403);
    }

    protected function respondNotFound(string $message = 'Not found'): HTTPResponse
    {
        return $this->respondError($message, 404);
    }
}