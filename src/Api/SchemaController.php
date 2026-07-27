<?php

namespace BradTipper\RestfulServer\Api;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;

/**
 * GET /api/schema
 *
 * Introspects all registered RestfulDataObject subclasses and returns
 * their field/type definitions. This is the contract consumed by the
 * companion client package's codegen tool.
 */
class SchemaController extends ApiController
{
    private static array $allowed_actions = ['index'];

    public function index(HTTPRequest $request): HTTPResponse
    {
        $resources = [];
        $classes = ClassInfo::subclassesFor(RestfulDataObject::class);

        foreach ($classes as $class) {
            if ($class === RestfulDataObject::class) {
                continue; // skip the base class itself
            }

            /** @var RestfulDataObject $singleton */
            $singleton = Injector::inst()->get($class);
            $resources[] = $singleton->schemaDefinition();
        }

        return $this->respond([
            'version' => '1.0',
            'resources' => $resources,
        ]);
    }
}