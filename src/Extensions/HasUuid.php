<?php

namespace BradTipper\RestfulServer\Extensions;

use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Extension;

class HasUuid extends Extension
{
    private static array $db = [
        'UUID' => 'Varchar(36)',
    ];

    private static array $indexes = [
        'UniqueUUID' => [
            'type' => 'unique',
            'columns' => ['UUID'],
        ],
    ];

    public function onBeforeWrite(): void
    {
        $owner = $this->getOwner();
        if (!$owner->UUID) {
            $owner->UUID = Uuid::uuid4()->toString();
        }
    }
}