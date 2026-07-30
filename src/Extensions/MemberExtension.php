<?php

namespace BradTipper\RestfulServer\Extensions;

use BradTipper\RestfulServer\Auth\AuthSession;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Core\Extension;

/**
 * Adds an AuthSessions tab to the Member CMS view, replacing
 * the need for a standalone AuthSessionAdmin.
 */
class MemberExtension extends Extension
{
    private static array $db = [
        'RestfulApiEnabled' => 'Boolean',
    ];

    private static array $defaults = [
        'RestfulApiEnabled' => true,
    ];

    private static array $has_many = [
        'RestfulAuthSessions' => AuthSession::class . '.Member',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->addFieldToTab(
            'Root.Main',
            CheckboxField::create(
                'RestfulApiEnabled',
                'Allow REST API access'
            )
        );
        $fields->addFieldToTab(
            'Root.AuthSessions',
            GridField::create(
                'RestfulAuthSessions',
                'Auth Sessions',
                $this->owner->RestfulAuthSessions(),
                GridFieldConfig_RecordViewer::create()
            )
        );
    }

    public function onAfterWrite(): void
    {
        if (
            ($this->owner->isChanged('RestfulApiEnabled') && !$this->owner->RestfulApiEnabled)
            || $this->owner->isChanged('Password')
        ) {
            AuthSession::revokeAllForMember($this->owner);
        }
    }

    public function onBeforeDelete(): void
    {
        AuthSession::revokeAllForMember($this->owner);
    }
}
