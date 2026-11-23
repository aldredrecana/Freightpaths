<?php

namespace Tests\Feature;

use App\Models\LocationPing;
use App\Models\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The driven route follows the roads, lost signal is drawn as a gap, and OSRM failures are safe. */
class RoadSnapTest extends TestCase
{
    private function ping(Schedule $trip, int $minutesAgo, float $lat, ?float $accuracy = 10, int $secondsExtra = 0): void
    {
        LocationPing::create([
            'schedule_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'latitude' => $lat,
            'longitude' => 121.24,
            'accuracy' => $accuracy,
            'recorded_at' => now()->subMinutes($minutesAgo)->addSeconds($secondsExtra),
        ]);
    }

    private function trip(): Schedule
    {
        return $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'en_route']);
    }

    public function test_without_snapping_the_lost_signal_part_is_a_separate_gap(): void
    {
        $trip = $this->trip();
        // Driving: points 15 s apart, about 100 m apart.
        $this->ping($trip, 30, 14.1000);
        $this->ping($trip, 30, 14.1009, 10, 15);
        // 20 minutes of silence and 5 km further: lost signal.
        $this->ping($trip, 10, 14.1460);
        $this->ping($trip, 10, 14.1469, 10, 15);

        $segments = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->json('segments');

        $this->assertSame(['driven', 'gap', 'driven'], array_column($segments, 'type'));
        $this->assertSame([[14.1009, 121.24], [14.146, 121.24]], $segments[1]['points']);
    }

    public function test_rough_points_are_left_out_of_the_line(): void
    {
        $trip = $this->trip();
        $this->ping($trip, 5, 14.1000);
        $this->ping($trip, 5, 14.1050, 400, 15); // a 400 m cell-tower fix
        $this->ping($trip, 5, 14.1009, 10, 30);

        $segments = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->json('segments');

        $this->assertSame([[14.1, 121.24], [14.1009, 121.24]], $segments[0]['points']);
    }

    public function test_with_snapping_the_line_and_marker_follow_the_road(): void
    {
        config(['freightpath.snap_to_roads' => true]);
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/match/')) {
                return Http::response(['code' => 'Ok', 'matchings' => [[
                    'geometry' => ['coordinates' => [[121.2401, 14.1], [121.2402, 14.1005], [121.2401, 14.1009]]],
                ]]]);
            }
            if (str_contains($request->url(), '/route/')) {
                return Http::response(['code' => 'Ok', 'routes' => [[
                    'geometry' => ['coordinates' => [[121.2401, 14.1009], [121.25, 14.12], [121.24, 14.146]]],
                ]]]);
            }

            return Http::response(['code' => 'Ok', 'waypoints' => [['location' => [121.2403, 14.1469], 'distance' => 12]]]);
        });

        $trip = $this->trip();
        $this->ping($trip, 30, 14.1000);
        $this->ping($trip, 30, 14.1009, 10, 15);
        $this->ping($trip, 10, 14.1460);
        $this->ping($trip, 10, 14.1469, 10, 15);

        $data = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->json();

        $this->assertSame([14.1005, 121.2402], $data['segments'][0]['points'][1]); // on the road
        $this->assertCount(3, $data['segments'][1]['points']); // the gap follows a road route
        $this->assertSame(14.1469, $data['last']['lat']);
        $this->assertSame(121.2403, $data['last']['lng']); // marker moved onto the road
    }

    public function test_if_osrm_is_down_the_raw_gps_line_is_used(): void
    {
        config(['freightpath.snap_to_roads' => true]);
        Http::fake(fn () => Http::response('Service unavailable', 503));

        $trip = $this->trip();
        $this->ping($trip, 5, 14.1000);
        $this->ping($trip, 5, 14.1009, 10, 15);

        $data = $this->actingAs($this->makeUser('dispatcher'))
            ->getJson(route('schedules.tracking', $trip))
            ->assertOk()
            ->json();

        $this->assertSame([[14.1, 121.24], [14.1009, 121.24]], $data['segments'][0]['points']);
        $this->assertSame(14.1009, $data['last']['lat']);
    }
}