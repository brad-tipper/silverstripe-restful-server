<?php

namespace BradTipper\RestfulServer\Api;

use BradTipper\RestfulServer\Extensions\HasUuid;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Security;
use SilverStripe\Security\Member;

/**
 * Base DataObject that exposes a model over REST with zero additional wiring.
 *
 * Extend this class directly (e.g. `class Invoice extends RestfulDataObject {}`)
 * and define your $db, $has_one, $has_many, $many_many fields as normal.
 * CRUD list/show/create/update/delete endpoints are automatically available
 * via the parent RESTful controller routing.
 *
 * Field-level protection:
 *   - Return an array of field names from canRead() to allow read, or null for all
 *   - Return an array of field names from canEdit() to allow edit, or null for all
 *   - Return false from canView()/canEdit() (the DataObject methods) to deny entirely
 */
class RestfulDataObject extends DataObject
{
    private static array $extensions = [
        HasUuid::class,
    ];

    /**
     * Subclasses can override to restrict which DB fields are readable.
     * Return an array of field names that ARE readable, or null for "all".
     */
    public function getReadableFields(?Member $member = null): ?array
    {
        return null; // all fields readable by default
    }

    /**
     * Subclasses can override to restrict which DB fields are writable.
     * Return an array of field names that ARE writable, or null for "all".
     */
    public function getWritableFields(?Member $member = null): ?array
    {
        return null; // all fields writable by default
    }

    /**
     * Serialize this object to a standard API response array.
     * Includes UUID, all DB fields, and a summary of relations.
     * Override in subclasses to customise the shape.
     */
    public function toApiArray(): array
    {
        $data = [
            'uuid' => $this->UUID,
        ];

        $readable = $this->getReadableFields();

        foreach ($this->config()->get('db') as $field => $type) {
            if ($readable !== null && !in_array($field, $readable, true)) {
                continue;
            }
            $data[$field] = $this->$field;
        }

        // Include has_one relation UUIDs
        foreach ($this->config()->get('has_one') as $relName => $relClass) {
            if ($readable !== null && !in_array("{$relName}ID", $readable, true) && !in_array($relName, $readable, true)) {
                continue;
            }
            $relObj = $this->$relName();
            if ($relObj && $relObj->exists()) {
                $data["{$relName}Uuid"] = $relObj->UUID;
            }
        }

        return $data;
    }

    /**
     * Returns the schema definition for this resource: field names, types,
     * whether they're required, read-only, and relation info.
     */
    public function schemaDefinition(): array
    {
        $db = $this->config()->get('db') ?: [];
        $fields = [];

        foreach ($db as $field => $type) {
            $parts = explode('(', $type);
            $baseType = strtolower($parts[0]);
            $fields[$field] = [
                'type' => $this->normaliseType($baseType),
                'required' => $field === 'UUID',
                'readOnly' => $field === 'UUID',
            ];
        }

        foreach ($this->config()->get('has_one') ?: [] as $relName => $relClass) {
            $fields["{$relName}Uuid"] = [
                'type' => 'uuid',
                'required' => false,
                'readOnly' => false,
                'relation' => [
                    'type' => 'has_one',
                    'class' => $relClass,
                ],
            ];
        }

        return [
            'class' => static::class,
            'table' => $this->config()->get('table_name'),
            'fields' => $fields,
        ];
    }

    /**
     * The API resource name for this class (plural, kebab-case).
     * By default, derives from the short class name.
     */
    public static function resourceName(): string
    {
        $parts = explode('\\', static::class);
        $short = end($parts);
        return self::camelToKebab($short) . 's';
    }

    private static function camelToKebab(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $input) ?: $input);
    }

    private function normaliseType(string $baseType): string
    {
        $map = [
            'varchar' => 'string',
            'text' => 'string',
            'dbdatetime' => 'datetime',
            'date' => 'date',
            'decimal' => 'number',
            'int' => 'integer',
            'boolean' => 'boolean',
            'enum' => 'string',
            'currency' => 'number',
            'float' => 'number',
            'double' => 'number',
            'percentage' => 'number',
        ];

        // Check for partial matches (e.g. DBDatetime starts with DBDate)
        foreach ($map as $needle => $mapped) {
            if (str_starts_with($baseType, $needle)) {
                return $mapped;
            }
        }

        return 'string';
    }
}