<?php

namespace BradTipper\RestfulServer\Auth;

use BradTipper\RestfulServer\Extensions\HasUuid;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class AuthSession extends DataObject
{
    private static string $table_name = 'RestfulServer_AuthSession';

    private static array $db = [
        'RefreshTokenHash' => 'Varchar(255)',
        'Label' => 'Varchar(255)',
        'ExpiresAt' => 'DBDatetime',
        'RevokedAt' => 'DBDatetime',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $extensions = [
        HasUuid::class,
    ];

    public function canView($member = null, $context = []): bool
    {
        $member = $member ?: Security::getCurrentUser();
        return ($member && (int) $this->MemberID === (int) $member->ID);
    }

    public function canEdit($member = null, $context = []): bool
    {
        return $this->canView($member, $context);
    }

    public function canDelete($member = null, $context = []): bool
    {
        return $this->canView($member, $context);
    }

    public function toApiArray(): array
    {
        return [
            'uuid' => $this->UUID,
            'label' => $this->Label,
            'expiresAt' => $this->ExpiresAt,
            'revokedAt' => $this->RevokedAt,
        ];
    }
}