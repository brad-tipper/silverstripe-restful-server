<?php

declare(strict_types=1);

namespace BradTipper\RestfulServer\Tests;

use BradTipper\RestfulServer\Api\ResourceController;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FirstResource
{
    public static function resourceName(): string
    {
        return 'tenant-records';
    }
}

final class SecondResource
{
    public static function resourceName(): string
    {
        return 'tenant-records';
    }
}

final class UniqueResource
{
    public static function resourceName(): string
    {
        return 'unique-records';
    }
}

final class ResourceControllerTest extends TestCase
{
    public function testResourceMapAcceptsUniqueNames(): void
    {
        self::assertSame([
            'tenant-records' => FirstResource::class,
            'unique-records' => UniqueResource::class,
        ], ResourceController::resourceMapForClasses([
            FirstResource::class,
            UniqueResource::class,
        ]));
    }

    public function testResourceMapRejectsDuplicateNames(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Duplicate REST resource name 'tenant-records'");

        ResourceController::resourceMapForClasses([
            FirstResource::class,
            SecondResource::class,
        ]);
    }
}
