<?php

namespace BradTipper\RestfulServer\Security;

use BradTipper\RestfulServer\Auth\AuthSession;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

/**
 * Ensures a "REST API Users" group exists.
 *
 * Only members in this group can authenticate via the REST API.
 * CMS/admin users who are not in this group cannot get JWT tokens.
 *
 * This creates a clean separation: the API auth system is independent
 * of CMS/content admin logins.
 */
class RestfulApiMemberGroup
{
    public const GROUP_CODE = 'restful-api-users';
    public const GROUP_NAME = 'REST API Users';

    /**
     * Get or create the REST API Users group.
     */
    public static function ensure(): Group
    {
        $group = Group::get()->filter('Code', self::GROUP_CODE)->first();
        if (!$group) {
            $group = Group::create([
                'Code' => self::GROUP_CODE,
                'Title' => self::GROUP_NAME,
            ]);
            $group->write();
        }
        return $group;
    }

    /**
     * Check whether a member belongs to the REST API Users group.
     */
    public static function isApiUser(Member $member): bool
    {
        if (!$member->exists() || !$member->RestfulApiEnabled) {
            return false;
        }
        $group = Group::get()->filter('Code', self::GROUP_CODE)->first();
        if (!$group) {
            return false;
        }
        return $member->inGroup($group);
    }

    /**
     * Add a member to the REST API Users group.
     */
    public static function addMember(Member $member): void
    {
        $group = self::ensure();
        $member->RestfulApiEnabled = true;
        $member->write();
        $member->addToGroupByCode(self::GROUP_CODE);
    }

    /**
     * Remove API authorization and revoke every refresh/access session.
     *
     * Authorization-management code must use this method instead of mutating
     * the Groups relation directly.
     */
    public static function removeMember(Member $member): void
    {
        AuthSession::revokeAllForMember($member);
        $member->removeFromGroupByCode(self::GROUP_CODE);
    }
}
