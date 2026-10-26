<?php

namespace Tests\Unit\Support;

use App\Support\Lot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LotTest extends TestCase
{
    #[DataProvider('zoneFloors')]
    public function test_floor_is_derived_from_the_zone_letter_in_groups_of_four(string $zone, int $floor): void
    {
        $this->assertSame($floor, Lot::floorForZone($zone));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function zoneFloors(): array
    {
        return [
            'first zone on floor 1' => ['Zone A', 1],
            'fourth zone on floor 1' => ['Zone D', 1],
            'first zone on floor 2' => ['Zone E', 2],
            'fourth zone on floor 2' => ['Zone H', 2],
            'first zone on floor 3' => ['Zone I', 3],
        ];
    }

    public function test_each_floor_exposes_at_most_four_lettered_zones(): void
    {
        $this->assertSame(['Zone A', 'Zone B', 'Zone C', 'Zone D'], Lot::zonesForFloor(1));
        $this->assertSame(['Zone E', 'Zone F', 'Zone G', 'Zone H'], Lot::zonesForFloor(2));
        $this->assertSame(4, Lot::maxZonesPerFloor());
        $this->assertSame([], Lot::zonesForFloor(0));
        $this->assertNull(Lot::floorForZone('North Lot'));
        $this->assertNull(Lot::zoneLetter('Zone AA'));
        $this->assertSame([1, 2], Lot::occupiedFloors(['Zone A', 'Zone E', 'Zone B']));
        $this->assertSame([], Lot::occupiedFloors([]));
    }
}
