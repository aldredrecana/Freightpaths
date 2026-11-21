<?php

namespace Tests\Feature;

use App\Models\RestPeriod;
use Tests\TestCase;

/** Downloading the trip history as a CSV file for Excel. */
class ExportTest extends TestCase
{
    public function test_staff_can_download_finished_trips_as_csv(): void
    {
        $driver = $this->makeDriver();
        $truck = $this->makeTruck();

        $done = $this->makeTrip($driver, $truck, [
            'status' => 'delivered',
            'destination_address' => 'Lipa City, Batangas',
        ]);
        RestPeriod::create([
            'schedule_id' => $done->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(2)->addMinutes(30),
            'duration_seconds' => 1800,
            'latitude' => 14.1,
            'longitude' => 121.2,
            'ping_count' => 10,
        ]);

        // A trip that is not finished must not be in the export.
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'destination_address' => 'Not Finished Town',
        ]);

        $response = $this->actingAs($this->makeUser('dispatcher'))
            ->get(route('transactions.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Reference,Status,Driver', $csv);
        $this->assertStringContainsString($done->reference, $csv);
        $this->assertStringContainsString('Lipa City, Batangas', $csv);
        $this->assertStringContainsString(',1,30', $csv); // 1 rest stop, 30 minutes
        $this->assertStringNotContainsString('Not Finished Town', $csv);
    }

    public function test_the_export_uses_the_search_filter(): void
    {
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'status' => 'delivered', 'destination_address' => 'Lipa City',
        ]);
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'status' => 'cancelled', 'destination_address' => 'Naga City',
        ]);

        $csv = $this->actingAs($this->makeUser('admin'))
            ->get(route('transactions.export', ['q' => 'Naga']))
            ->streamedContent();

        $this->assertStringContainsString('Naga City', $csv);
        $this->assertStringNotContainsString('Lipa City', $csv);
    }

    public function test_cells_that_look_like_formulas_are_made_safe(): void
    {
        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'status' => 'delivered', 'origin_address' => '=HYPERLINK("http://bad.example")',
        ]);

        $csv = $this->actingAs($this->makeUser('admin'))
            ->get(route('transactions.export'))
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_drivers_cannot_export(): void
    {
        $driver = $this->makeDriver();

        $this->actingAs($driver->user)
            ->get(route('transactions.export'))
            ->assertForbidden();
    }
}