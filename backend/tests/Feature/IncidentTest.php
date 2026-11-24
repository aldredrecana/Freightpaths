<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\LocationPing;
use App\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Problem reports: the driver reports from the app, the dispatcher is alerted and resolves it. */
class IncidentTest extends TestCase
{
    private function trip(string $status = 'en_route'): Schedule
    {
        return $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => $status]);
    }

    public function test_a_driver_reports_a_problem_with_the_location(): void
    {
        $trip = $this->trip();
        Sanctum::actingAs($trip->driver->user);

        $this->postJson("/api/trips/{$trip->id}/incidents", [
            'type' => 'flat_tire',
            'note' => 'Left rear tire, near Calauan market.',
            'lat' => 14.1500,
            'lng' => 121.3150,
            'accuracy' => 8,
        ])->assertCreated()->assertJsonPath('incident.label', 'Flat tire');

        $incident = Incident::sole();
        $this->assertSame($trip->id, $incident->schedule_id);
        $this->assertSame(14.15, $incident->latitude);
        $this->assertNull($incident->resolved_at);
    }

    public function test_without_a_phone_location_the_last_gps_point_is_used(): void
    {
        $trip = $this->trip();
        LocationPing::create([
            'schedule_id' => $trip->id, 'driver_id' => $trip->driver_id,
            'latitude' => 14.2, 'longitude' => 121.2, 'recorded_at' => now()->subMinute(),
        ]);
        Sanctum::actingAs($trip->driver->user);

        $this->postJson("/api/trips/{$trip->id}/incidents", ['type' => 'breakdown'])->assertCreated();

        $this->assertSame(14.2, Incident::sole()->latitude);
    }

    public function test_other_needs_a_description_and_bad_types_are_rejected(): void
    {
        $trip = $this->trip();
        Sanctum::actingAs($trip->driver->user);

        $this->postJson("/api/trips/{$trip->id}/incidents", ['type' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson("/api/trips/{$trip->id}/incidents", ['type' => 'aliens'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_reports_only_for_own_trips_that_are_accepted_or_on_the_road(): void
    {
        $pending = $this->trip('pending');
        Sanctum::actingAs($pending->driver->user);
        $this->postJson("/api/trips/{$pending->id}/incidents", ['type' => 'breakdown'])->assertStatus(422);

        $someoneElse = $this->trip();
        $this->postJson("/api/trips/{$someoneElse->id}/incidents", ['type' => 'breakdown'])->assertForbidden();

        $this->assertSame(0, Incident::count());
    }

    public function test_staff_are_alerted_and_can_resolve_it(): void
    {
        $trip = $this->trip();
        Sanctum::actingAs($trip->driver->user);
        $this->postJson("/api/trips/{$trip->id}/incidents", ['type' => 'breakdown', 'note' => 'Engine overheating.']);
        $incident = Incident::sole();

        $dispatcher = $this->makeUser('dispatcher');

        // The bell shows it first, the dashboard counts it, the map flags the trip.
        $this->actingAs($dispatcher)->getJson(route('alerts.index'))
            ->assertJsonPath('items.0.type', 'incident')
            ->assertJsonPath('items.0.title', 'Breakdown: '.$trip->driver->user->name);
        $this->get(route('dashboard'))->assertSee('open problem report(s) from drivers');
        $this->getJson(route('map.data'))->assertJsonPath('trips.0.incident', 'Breakdown');
        $this->get(route('incidents.index'))->assertOk()->assertSee('Engine overheating.');
        $this->get(route('schedules.show', $trip))->assertSee('Problem reports');

        $this->post(route('incidents.resolve', $incident), ['resolution_note' => 'Mechanic sent.'])
            ->assertRedirect();

        $incident->refresh();
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame($dispatcher->id, $incident->resolved_by);
        $this->getJson(route('alerts.index'))->assertJsonPath('count', 0);

        // The driver sees that it was handled.
        Sanctum::actingAs($trip->driver->user);
        $this->getJson("/api/trips/{$trip->id}/incidents")
            ->assertOk()
            ->assertJsonPath('incidents.0.resolution_note', 'Mechanic sent.');
    }

    public function test_drivers_cannot_open_or_resolve_problem_reports_on_the_website(): void
    {
        $trip = $this->trip();
        $incident = Incident::create([
            'schedule_id' => $trip->id, 'driver_id' => $trip->driver_id,
            'type' => 'accident', 'reported_at' => now(),
        ]);

        $this->actingAs($trip->driver->user)->get(route('incidents.index'))->assertForbidden();
        $this->post(route('incidents.resolve', $incident))->assertForbidden();
        $this->assertNull($incident->fresh()->resolved_at);
    }
}