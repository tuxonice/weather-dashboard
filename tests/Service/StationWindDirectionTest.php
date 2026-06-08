<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Observation\Meteorology\StationWindDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StationWindDirectionTest extends TestCase
{
    #[DataProvider('cases')]
    public function testCodeAndLabel(?int $id, string $expectedCode, string $expectedLabel): void
    {
        self::assertSame($expectedCode, StationWindDirection::code($id));
        self::assertSame($expectedLabel, StationWindDirection::label($id));
    }

    #[DataProvider('bearingCases')]
    public function testBearing(?int $id, ?int $expectedBearing): void
    {
        self::assertSame($expectedBearing, StationWindDirection::bearing($id));
    }

    /**
     * @return iterable<string, array{0:?int,1:?int}>
     */
    public static function bearingCases(): iterable
    {
        yield 'null id'     => [null, null];
        yield 'calm (0)'    => [0,    null];
        yield 'north (1)'   => [1,    0];
        yield 'north-east'  => [2,    45];
        yield 'east'        => [3,    90];
        yield 'south-east'  => [4,    135];
        yield 'south'       => [5,    180];
        yield 'south-west'  => [6,    225];
        yield 'west'        => [7,    270];
        yield 'north-west'  => [8,    315];
        yield 'north (9)'   => [9,    0];
        yield 'unknown id'  => [42,   null];
    }

    /**
     * @return iterable<string, array{0:?int,1:string,2:string}>
     */
    public static function cases(): iterable
    {
        yield 'null id'     => [null, '',   'wind.direction.unknown'];
        yield 'no wind (0)' => [0,    '',   'wind.direction.none'];
        yield 'north (1)'   => [1,    'N',  'wind.direction.n'];
        yield 'north-east'  => [2,    'NE', 'wind.direction.ne'];
        yield 'east'        => [3,    'E',  'wind.direction.e'];
        yield 'south-east'  => [4,    'SE', 'wind.direction.se'];
        yield 'south'       => [5,    'S',  'wind.direction.s'];
        yield 'south-west'  => [6,    'SW', 'wind.direction.sw'];
        yield 'west'        => [7,    'W',  'wind.direction.w'];
        yield 'north-west'  => [8,    'NW', 'wind.direction.nw'];
        yield 'north (9)'   => [9,    'N',  'wind.direction.n'];
        yield 'unknown id'  => [42,   '',   'wind.direction.unknown'];
    }
}
