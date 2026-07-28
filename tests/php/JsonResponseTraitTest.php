<?php

declare(strict_types=1);

namespace BradTipper\RestfulServer\Tests;

use BradTipper\RestfulServer\Api\JsonResponseTrait;
use PHPUnit\Framework\TestCase;
use SilverStripe\Control\HTTPResponse;

final class JsonResponseTraitTest extends TestCase
{
    public function testSerialisesPayloadsAsJson(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function buildResponse(mixed $data, int $status = 200): HTTPResponse
            {
                return $this->respond($data, $status);
            }
        };

        $response = $controller->buildResponse([
            'message' => 'ok',
            'nested' => ['uuid' => 'abc-123'],
        ], 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
        self::assertSame('{"message":"ok","nested":{"uuid":"abc-123"}}', $response->getBody());
    }

    public function testWrapsErrorsInStandardEnvelope(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function buildError(string $message, int $status = 400, array $details = []): HTTPResponse
            {
                return $this->respondError($message, $status, $details);
            }
        };

        $response = $controller->buildError('Invalid credentials', 401, ['field' => 'email']);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(
            '{"error":"Invalid credentials","details":{"field":"email"}}',
            $response->getBody(),
        );
    }

    public function testErrorWithoutDetailsOmitsKey(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function buildError(string $message, int $status = 400): HTTPResponse
            {
                return $this->respondError($message, $status);
            }
        };

        $response = $controller->buildError('Not found', 404);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('{"error":"Not found"}', $response->getBody());
    }

    public function testRespondWithDefaultStatus(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function send(mixed $data): HTTPResponse
            {
                return $this->respond($data);
            }
        };

        $response = $controller->send(['ok' => true]);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
    }

    public function testRespondNotFoundWithDefaultMessage(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function notFound(): HTTPResponse
            {
                return $this->respondNotFound();
            }
        };

        $response = $controller->notFound();
        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Not found', (string) $response->getBody());
    }

    public function testRespondNotFoundWithCustomMessage(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function notFound(string $msg): HTTPResponse
            {
                return $this->respondNotFound($msg);
            }
        };

        $response = $controller->notFound('User not found');
        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('User not found', (string) $response->getBody());
    }

    public function testRespondForbiddenReturns403(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function forbidden(): HTTPResponse
            {
                return $this->respondForbidden();
            }
        };

        $response = $controller->forbidden();
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Forbidden', (string) $response->getBody());
    }

    public function testRespondForbiddenWithCustomMessage(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function forbidden(string $msg): HTTPResponse
            {
                return $this->respondForbidden($msg);
            }
        };

        $response = $controller->forbidden('Access denied');
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('Access denied', (string) $response->getBody());
    }

    public function testRespondValidationErrorProduces422(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function invalid(array $fieldErrors): HTTPResponse
            {
                return $this->respondValidationError('Validation failed', $fieldErrors);
            }
        };

        $response = $controller->invalid(['email' => 'Required', 'password' => 'Too short']);

        self::assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('Validation failed', $body['error'] ?? '');
        self::assertSame('Required', $body['details']['errors']['email'] ?? '');
        self::assertSame('Too short', $body['details']['errors']['password'] ?? '');
    }

    public function testEmptyStringIsValidJson(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function send(mixed $data): HTTPResponse
            {
                return $this->respond($data);
            }
        };

        $response = $controller->send('');
        json_decode((string) $response->getBody(), true);
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testUnicodeIsPreserved(): void
    {
        $controller = new class () {
            use JsonResponseTrait;

            public function send(mixed $data): HTTPResponse
            {
                return $this->respond($data);
            }
        };

        $response = $controller->send(['message' => 'kōwhai']);
        self::assertStringContainsString('kōwhai', (string) $response->getBody());
    }
}