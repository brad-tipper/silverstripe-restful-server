<?php

namespace BradTipper\RestfulServer\Api;

use BradTipper\RestfulServer\Auth\AuthSession;
use BradTipper\RestfulServer\Auth\JwtCodec;
use BradTipper\RestfulServer\Security\RestfulApiMemberGroup;
use DomainException;
use InvalidArgumentException;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use UnexpectedValueException;

trait CurrentMemberTrait
{
    protected function currentMember(): Member
    {
        $header = $this->getRequest()->getHeader('Authorization');
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            throw new AuthException('Missing bearer token');
        }

        try {
            $payload = JwtCodec::singleton()->decode(substr($header, 7));
        } catch (DomainException | InvalidArgumentException | UnexpectedValueException) {
            throw new AuthException('Invalid token');
        }

        $session = AuthSession::get()->filter([
            'UUID' => $payload['sid'] ?? '',
            'RevokedAt' => null,
        ])->first();

        if (
            !$session
            || ($session->ExpiresAt && strtotime((string) $session->ExpiresAt) < time())
        ) {
            throw new AuthException('Invalid token');
        }

        $member = Member::get()->filter('UUID', $payload['sub'] ?? '')->first();
        if (!$member || !AuthSession::belongsToMemberId($session->MemberID, $member->ID)) {
            throw new AuthException('Invalid token');
        }
        if (!RestfulApiMemberGroup::isApiUser($member)) {
            AuthSession::revokeAllForMember((int) $session->MemberID);
            throw new AuthException('API access has been revoked');
        }

        Security::setCurrentUser($member);
        return $member;
    }
}
