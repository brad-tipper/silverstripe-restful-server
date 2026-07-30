<?php

namespace BradTipper\RestfulServer\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use RuntimeException;
use UnexpectedValueException;

class JwtCodec
{
    use Injectable;

    private const DEFAULT_EXPIRY = 900; // 15 minutes
    private const DEFAULT_ISSUER = 'restful-server';

    public function encode(array $payload): string
    {
        $payload['iss'] ??= $this->issuer();
        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    public function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret(), 'HS256'));
        $payload = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        if (($payload['iss'] ?? null) !== $this->issuer()) {
            throw new UnexpectedValueException('Invalid JWT issuer');
        }
        return $payload;
    }

    public function secret(): string
    {
        $secret = Environment::getEnv('RESTFUL_JWT_SECRET');
        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('Missing RESTFUL_JWT_SECRET environment variable');
        }
        return $secret;
    }

    public function expiry(): int
    {
        $expiry = Environment::getEnv('RESTFUL_JWT_EXPIRY');
        if (is_string($expiry) && $expiry !== '') {
            return (int) $expiry;
        }
        return self::DEFAULT_EXPIRY;
    }

    public function issuer(): string
    {
        $issuer = Environment::getEnv('RESTFUL_JWT_ISSUER');
        if (is_string($issuer) && $issuer !== '') {
            return $issuer;
        }
        return self::DEFAULT_ISSUER;
    }
}
