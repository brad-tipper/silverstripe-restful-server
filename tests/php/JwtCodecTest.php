<?php

declare(strict_types=1);

namespace BradTipper\RestfulServer\Tests;

use BradTipper\RestfulServer\Auth\JwtCodec;
use PHPUnit\Framework\TestCase;

final class JwtCodecTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('RESTFUL_JWT_SECRET=test-secret-test-secret-test-secret-1234567890');
    }

    public function testEncodesAndDecodesPayloads(): void
    {
        $codec = new JwtCodec();
        $payload = [
            'sub' => 'member-uuid',
            'sid' => 'session-uuid',
            'iat' => time(),
            'exp' => time() + 3600,
        ];

        $token = $codec->encode($payload);
        $decoded = $codec->decode($token);

        self::assertSame($payload, $decoded);
    }

    public function testSecretThrowsWhenNotConfigured(): void
    {
        putenv('RESTFUL_JWT_SECRET=');
        $codec = new JwtCodec();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing RESTFUL_JWT_SECRET');

        $codec->secret();
    }

    public function testExpiryDefaultsTo900Seconds(): void
    {
        putenv('RESTFUL_JWT_EXPIRY=');
        $codec = new JwtCodec();

        self::assertSame(900, $codec->expiry());
    }

    public function testExpiryRespectsEnvironmentVariable(): void
    {
        putenv('RESTFUL_JWT_EXPIRY=1800');
        $codec = new JwtCodec();

        self::assertSame(1800, $codec->expiry());
    }

    public function testIssuerDefaultsToRestfulServer(): void
    {
        putenv('RESTFUL_JWT_ISSUER=');
        $codec = new JwtCodec();

        self::assertSame('restful-server', $codec->issuer());
    }

    public function testIssuerRespectsEnvironmentVariable(): void
    {
        putenv('RESTFUL_JWT_ISSUER=forwardfi');
        $codec = new JwtCodec();

        self::assertSame('forwardfi', $codec->issuer());
    }
}