<?php

namespace BradTipper\RestfulServer\Security;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Core\Manifest\ModuleManifest;

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
        $member->addToGroupByCode(self::GROUP_CODE);
    }
}