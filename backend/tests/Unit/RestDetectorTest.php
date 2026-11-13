<?php

namespace Tests\Unit;

use App\Services\RestDetector;
use PHPUnit\Framework\TestCase;

/** Pure logic: GPS points go in, rests come out (no database). */
class RestDetectorTest extends TestCase
{
    private const START = 1_700_000_000;

    private function detector(): RestDetector
    {
        // 100 m circle, 15 minutes, and a phone silent for over 10 minutes breaks a rest.
        return new RestDetector(100, 15 * 60, 10 * 60);
    }

    private function ping(int $minute, float $lat): array
    {
        return ['lat' => $lat, 'lng' => 121.24, 'time' => self::START + $minute * 60];
    }

    /** Parked: small GPS wobble (about 20 m) around one spot. */
    private function stop(int $from, int $to, float $lat): array
    {
        $pings = [];
        for ($m = $from; $m <= $to; $m++) {
            $pings[] = $this->ping($m, $lat + ($m % 2 === 0 ? 0.0002 : -0.0002));
        }

        return $pings;
    }

    /** Driving: about 550 m every minute. */
    private function drive(int $from, int $to, float $lat): array
    {
        $pings = [];
        for ($m = $from; $m <= $to; $m++) {
            $pings[] = $this->ping($m, $lat + ($m - $from) * 0.005);
        }

        return $pings;
    }

    public function test_a_long_stop_is_one_rest(): void
    {
        $points = [
            ...$this->drive(0, 9, 14.10),
            ...$this->stop(10, 29, 14.15),
            ...$this->drive(30, 34, 14.155),
        ];

        $rests = $this->detector()->detect($points);

        $this->assertCount(1, $rests);
        $this->assertSame(self::START + 10 * 60, $rests[0]['started_at']);
        $this->assertSame(self::START + 29 * 60, $rests[0]['ended_at']);
        $this->assertSame(20, $rests[0]['count']);
        $this->assertEqualsWithDelta(14.15, $rests[0]['lat'], 0.001);
    }

    public function test_a_short_stop_is_not_a_rest(): void
    {
        $points = [
            ...$this->drive(0, 9, 14.10),
            ...$this->stop(10, 19, 14.15),
            ...$this->drive(20, 24, 14.155),
        ];

        $this->assertSame([], $this->detector()->detect($points));
    }

    public function test_a_silent_phone_is_not_counted_as_rest(): void
    {
        $points = [
            $this->ping(0, 14.10), $this->ping(1, 14.10), $this->ping(2, 14.10),
            $this->ping(40, 14.10), $this->ping(41, 14.10), $this->ping(42, 14.10),
        ];

        $this->assertSame([], $this->detector()->detect($points));
    }

    public function test_two_separate_stops_are_two_rests(): void
    {
        $points = [
            ...$this->stop(0, 19, 14.10),
            ...$this->drive(20, 24, 14.105),
            ...$this->stop(25, 44, 14.13),
        ];

        $this->assertCount(2, $this->detector()->detect($points));
    }

    public function test_exactly_the_minimum_time_counts(): void
    {
        $this->assertCount(1, $this->detector()->detect($this->stop(0, 15, 14.10)));
        $this->assertCount(0, $this->detector()->detect($this->stop(0, 14, 14.10)));
    }

    public function test_slow_traffic_is_not_a_rest(): void
    {
        $points = [];
        for ($m = 0; $m <= 30; $m++) {
            $points[] = $this->ping($m, 14.10 + $m * 0.00018); // about 20 m per minute
        }

        $this->assertSame([], $this->detector()->detect($points));
    }

    public function test_no_points_or_one_point_gives_no_rest(): void
    {
        $this->assertSame([], $this->detector()->detect([]));
        $this->assertSame([], $this->detector()->detect([$this->ping(0, 14.10)]));
    }
}