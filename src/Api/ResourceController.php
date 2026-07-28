<?php

namespace BradTipper\RestfulServer\Api;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Security;

/**
 * Auto-discovered CRUD controller for RestfulDataObject subclasses.
 *
 * Maps URL resource names (derived from the class's resourceName() method)
 * to the appropriate DataObject class and provides standard CRUD actions:
 *   GET    /api/resource          → index (list, paginated)
 *   GET    /api/resource/{uuid}   → show (single)
 *   POST   /api/resource          → create
 *   PATCH  /api/resource/{uuid}   → update
 *   DELETE /api/resource/{uuid}   → delete
 */
class ResourceController extends ApiController implements RequiresAuth
{
    private static array $allowed_actions = [
        'index',
        'show',
        'create',
        'update',
        'delete',
    ];

    private static array $authenticated_actions = [
        'index',
        'show',
        'create',
        'update',
        'delete',
    ];

    /** Maps resourceName => FQCN of RestfulDataObject subclass. */
    private static array $resource_map = [];

    /**
     * Discover all RestfulDataObject subclasses and build the resource map.
     */
    public static function buildResourceMap(): void
    {
        $map = [];
        $classes = ClassInfo::subclassesFor(RestfulDataObject::class);
        foreach ($classes as $class) {
            if ($class === RestfulDataObject::class) continue;
            $name = $class::resourceName();
            $map[$name] = $class;
        }
        self::$resource_map = $map;
    }

    /**
     * GET /api/{resource}
     * Lists all records for the given resource with pagination.
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        $class = $this->resolveResource($request);
        if (!$class) return $this->respondNotFound('Unknown resource');

        $member = $this->currentMember();
        /** @var RestfulDataObject $singleton */
        $singleton = Injector::inst()->get($class);
        if (!$singleton->canView($member)) {
            return $this->respondForbidden();
        }

        $list = $class::get();

        // Apply optional filter param
        $filter = $request->getVar('filter');
        if (is_string($filter) && $filter !== '') {
            $parts = explode(',', $filter);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') continue;
                // Support: field:value, field:PartialMatch:value, etc.
                if (str_contains($part, ':')) {
                    $segments = explode(':', $part);
                    $field = array_shift($segments);
                    $modifier = count($segments) > 1 ? array_shift($segments) : 'ExactMatch';
                    $val = implode(':', $segments);
                    $list = $list->filter("{$field}:{$modifier}", $val);
                } else {
                    $list = $list->filter('UUID', $part);
                }
            }
        }

        // Apply sort
        $sort = $request->getVar('sort');
        if (is_string($sort) && $sort !== '') {
            $dir = 'ASC';
            if (str_starts_with($sort, '-')) {
                $dir = 'DESC';
                $sort = substr($sort, 1);
            }
            // Whitelist sortable fields to the DB columns
            $dbFields = array_keys($singleton->config()->get('db') ?: []);
            if (in_array($sort, $dbFields, true) || $sort === 'UUID') {
                $list = $list->sort($sort, $dir);
            }
        }

        [$page, $perPage, $offset] = $this->pagination($request);
        $total = $list->count();
        $items = [];
        foreach ($list->limit($perPage, $offset) as $item) {
            /** @var RestfulDataObject $item */
            if ($item->canView($member)) {
                $items[] = $item->toApiArray();
            }
        }

        return $this->respond([
            'data' => $items,
            'pagination' => $this->paginationArray($total, $page, $perPage),
        ]);
    }

    /**
     * GET /api/{resource}/{uuid}
     */
    public function show(HTTPRequest $request): HTTPResponse
    {
        $class = $this->resolveResource($request);
        if (!$class) return $this->respondNotFound('Unknown resource');

        $member = $this->currentMember();
        $uuid = (string) $request->param('ID');
        /** @var RestfulDataObject $item */
        $item = $class::get()->filter('UUID', $uuid)->first();

        if (!$item || !$item->canView($member)) {
            return $this->respondNotFound();
        }

        return $this->respond(['data' => $item->toApiArray()]);
    }

    /**
     * POST /api/{resource}
     */
    public function doCreate(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isPOST()) return $this->methodNotAllowed('POST');

        $class = $this->resolveResource($request);
        if (!$class) return $this->respondNotFound('Unknown resource');

        $member = $this->currentMember();
        /** @var RestfulDataObject $singleton */
        $singleton = Injector::inst()->get($class);
        if (!$singleton->canCreate($member)) {
            return $this->respondForbidden();
        }

        $data = $this->requestData($request);
        $writable = $singleton->getWritableFields($member);
        $dbFields = array_keys($singleton->config()->get('db') ?: []);

        /** @var RestfulDataObject $item */
        $item = Injector::inst()->create($class);
        foreach ($data as $key => $value) {
            if (in_array($key, $dbFields, true)) {
                if ($writable !== null && !in_array($key, $writable, true)) continue;
                $item->$key = $value;
            }
        }
        $item->write();

        return $this->respond(['data' => $item->toApiArray()], 201);
    }

    /**
     * PATCH /api/{resource}/{uuid}
     */
    public function update(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() !== 'PATCH') return $this->methodNotAllowed('PATCH');

        $class = $this->resolveResource($request);
        if (!$class) return $this->respondNotFound('Unknown resource');

        $member = $this->currentMember();
        $uuid = (string) $request->param('ID');
        /** @var RestfulDataObject $item */
        $item = $class::get()->filter('UUID', $uuid)->first();

        if (!$item || !$item->canView($member)) {
            return $this->respondNotFound();
        }
        if (!$item->canEdit($member)) {
            return $this->respondForbidden();
        }

        $data = $this->requestData($request);
        $writable = $item->getWritableFields($member);
        $dbFields = array_keys($item->config()->get('db') ?: []);

        foreach ($data as $key => $value) {
            if (in_array($key, $dbFields, true)) {
                if ($writable !== null && !in_array($key, $writable, true)) continue;
                $item->$key = $value;
            }
        }
        $item->write();

        return $this->respond(['data' => $item->toApiArray()]);
    }

    /**
     * DELETE /api/{resource}/{uuid}
     */
    public function delete(HTTPRequest $request): HTTPResponse
    {
        if (!$request->isDELETE()) return $this->methodNotAllowed('DELETE');

        $class = $this->resolveResource($request);
        if (!$class) return $this->respondNotFound('Unknown resource');

        $member = $this->currentMember();
        $uuid = (string) $request->param('ID');
        /** @var RestfulDataObject $item */
        $item = $class::get()->filter('UUID', $uuid)->first();

        if (!$item || !$item->canView($member)) {
            return $this->respondNotFound();
        }
        if (!$item->canDelete($member)) {
            return $this->respondForbidden();
        }

        $item->delete();
        return $this->respond(['ok' => true]);
    }

    /**
     * Resolve the resource class from the URL parameter.
     */
    private function resolveResource(HTTPRequest $request): ?string
    {
        $resourceName = strtolower((string) $request->param('ResourceName'));
        if (empty(self::$resource_map)) {
            self::buildResourceMap();
        }
        return self::$resource_map[$resourceName] ?? null;
    }
}