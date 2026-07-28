<?php

declare(strict_types=1);

namespace BradTipper\RestfulServer\Tests;

use BradTipper\RestfulServer\Auth\AuthSession;
use PHPUnit\Framework\TestCase;

final class AuthSessionTest extends TestCase
{
    public function testAnonymousMemberCannotCreateSession(): void
    {
        $session = (new \ReflectionClass(AuthSession::class))->newInstanceWithoutConstructor();

        self::assertFalse($session->canCreate());
    }

    public function testBelongsToMemberIdMatchesEqualIds(): void
    {
        self::assertTrue(AuthSession::belongsToMemberId(123, 123));
        self::assertFalse(AuthSession::belongsToMemberId(123, 456));
        self::assertTrue(AuthSession::belongsToMemberId('42', 42));
        self::assertTrue(AuthSession::belongsToMemberId(42, '42'));
        self::assertFalse(AuthSession::belongsToMemberId(null, 1));
        self::assertFalse(AuthSession::belongsToMemberId(1, null));
    }

    public function testToApiArrayExcludesInternalProperties(): void
    {
        $session = (new \ReflectionClass(AuthSession::class))->newInstanceWithoutConstructor();
        $session->UUID = 'test-uuid-123';
        $session->MemberID = 42;
        $session->RefreshTokenHash = 'super-secret-hash';
        $session->Label = 'Chrome on macOS';
        $session->ExpiresAt = '2026-12-01 00:00:00';
        $session->RevokedAt = null;

        $result = $session->toApiArray();

        self::assertSame([
            'uuid' => 'test-uuid-123',
            'label' => 'Chrome on macOS',
            'expiresAt' => '2026-12-01 00:00:00',
            'revokedAt' => null,
        ], $result);

        // Ensure internal DB fields are not leaked
        self::assertArrayNotHasKey('memberId', $result);
        self::assertArrayNotHasKey('memberID', $result);
        self::assertArrayNotHasKey('refreshTokenHash', $result);
        self::assertArrayNotHasKey('RefreshTokenHash', $result);
    }

    public function testToApiArrayExposesRevokedAtWhenSet(): void
    {
        $session = (new \ReflectionClass(AuthSession::class))->newInstanceWithoutConstructor();
        $session->UUID = 'revoked-session';
        $session->Label = 'Old device';
        $session->ExpiresAt = '2026-06-01 00:00:00';
        $session->RevokedAt = '2026-05-15 12:00:00';

        $result = $session->toApiArray();

        self::assertSame('revoked-session', $result['uuid']);
        self::assertSame('Old device', $result['label']);
        self::assertSame('2026-05-15 12:00:00', $result['revokedAt']);
    }
}