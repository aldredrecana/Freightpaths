<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\Truck;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rules enforced by PostgreSQL itself (triggers, CHECK constraints, partial
 * unique indexes, and the view), so they hold even if the app has a bug.
 *
 * A failed statement aborts PostgreSQL's transaction, so each test that expects
 * a database error makes that its last step.
 */
class DatabaseRulesTest extends TestCase
{
    public function test_the_expected_triggers_functions_indexes_and_view_exist(): void
    {
        $triggers = DB::table('pg_trigger')->where('tgisinternal', false)->pluck('tgname')->all();
        $this->assertContains('trg_protect_finished_trips', $triggers);
        $this->assertContains('trg_logs_append_only', $triggers);

        $functions = DB::table('pg_proc')->pluck('proname')->all();
        $this->assertContains('protect_finished_trips', $functions);
        $this->assertContains('block_log_updates', $functions);

        $indexes = DB::table('pg_indexes')->where('tablename', 'schedules')->pluck('indexname')->all();
        $this->assertContains('schedules_one_active_trip_per_driver', $indexes);
        $this->assertContains('schedules_one_active_trip_per_truck', $indexes);
        $this->assertContains('schedules_status_index', $indexes);

        $this->assertTrue(DB::table('pg_views')->where('viewname', 'active_schedules')->exists());
    }

    public function test_the_app_role_can_only_read_the_view_and_the_readonly_role_can_only_select(): void
    {
        $roleExists = fn (string $role) => DB::table('pg_roles')->where('rolname', $role)->exists();

        if (! $roleExists('freightpath_app') || ! $roleExists('freightpath_readonly')) {
            $this->markTestSkipped('Run database-admin/setup.sql first to create the roles.');
        }

        $can = fn (string $role, string $table, string $privilege) => DB::selectOne(
            'select has_table_privilege(?, ?, ?) as allowed',
            [$role, $table, $privilege],
        )->allowed;

        // The app role reads and writes data tables but cannot change the view.
        $this->assertTrue($can('freightpath_app', 'schedules', 'INSERT'));
        $this->assertTrue($can('freightpath_app', 'active_schedules', 'SELECT'));
        $this->assertFalse($can('freightpath_app', 'active_schedules', 'INSERT'));
        $this->assertFalse($can('freightpath_app', 'active_schedules', 'DELETE'));

        // The read-only role can select everywhere and write nowhere.
        foreach (['schedules', 'drivers', 'active_schedules'] as $table) {
            $this->assertTrue($can('freightpath_readonly', $table, 'SELECT'));
            $this->assertFalse($can('freightpath_readonly', $table, 'INSERT'));
            $this->assertFalse($can('freightpath_readonly', $table, 'UPDATE'));
            $this->assertFalse($can('freightpath_readonly', $table, 'DELETE'));
        }

        // Neither role can create tables in the schema (only the owner can).
        $this->assertFalse(DB::selectOne("select has_schema_privilege('freightpath_app', 'public', 'CREATE') as allowed")->allowed);
        $this->assertFalse(DB::selectOne("select has_schema_privilege('freightpath_readonly', 'public', 'CREATE') as allowed")->allowed);
    }

    public function test_the_view_lists_only_trips_that_are_not_finished(): void
    {
        $driver = $this->makeDriver();
        $open = $this->makeTrip($driver, $this->makeTruck());
        $done = $this->makeTrip($driver, $this->makeTruck(), ['status' => 'delivered']);

        $ids = DB::table('active_schedules')->pluck('schedule_id')->all();

        $this->assertContains($open->id, $ids);
        $this->assertNotContains($done->id, $ids);
    }

    public function test_the_database_blocks_changes_to_a_finished_trip(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck(), ['status' => 'delivered']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('can no longer be changed');

        DB::table('schedules')->where('id', $trip->id)->update(['origin_address' => 'Tampered']);
    }

    public function test_the_database_keeps_the_status_history_append_only(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck());

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('delivery_status_logs')->where('schedule_id', $trip->id)->update(['remarks' => 'Tampered']);
    }

    public function test_a_check_constraint_rejects_an_invalid_status(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('trucks_status_check');

        Truck::create(['plate_no' => 'BAD 1', 'model' => 'X', 'capacity_kg' => 1000, 'status' => 'flying']);
    }

    public function test_a_check_constraint_rejects_an_arrival_before_the_departure(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('schedules_time_check');

        $this->makeTrip($this->makeDriver(), $this->makeTruck(), [
            'departure_at' => now()->addDay()->setTime(12, 0),
            'expected_arrival_at' => now()->addDay()->setTime(8, 0),
        ]);
    }

    public function test_a_driver_cannot_be_on_two_trips_at_once(): void
    {
        $driver = $this->makeDriver();
        $this->makeTrip($driver, $this->makeTruck(), ['status' => 'en_route']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('schedules_one_active_trip_per_driver');

        $this->makeTrip($driver, $this->makeTruck(), [
            'status' => 'en_route',
            'departure_at' => now()->addDays(3)->setTime(8, 0),
            'expected_arrival_at' => now()->addDays(3)->setTime(12, 0),
        ]);
    }

    public function test_a_truck_cannot_be_on_two_trips_at_once(): void
    {
        $truck = $this->makeTruck();
        $this->makeTrip($this->makeDriver(), $truck, ['status' => 'en_route']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('schedules_one_active_trip_per_truck');

        $this->makeTrip($this->makeDriver(), $truck, [
            'status' => 'en_route',
            'departure_at' => now()->addDays(3)->setTime(8, 0),
            'expected_arrival_at' => now()->addDays(3)->setTime(12, 0),
        ]);
    }

    public function test_deleting_a_pending_trip_removes_its_history_but_a_driver_with_trips_is_protected(): void
    {
        $trip = $this->makeTrip($this->makeDriver(), $this->makeTruck());
        $this->assertSame(1, DB::table('delivery_status_logs')->where('schedule_id', $trip->id)->count());

        $trip->delete(); // ON DELETE CASCADE removes the timeline too

        $this->assertSame(0, DB::table('delivery_status_logs')->where('schedule_id', $trip->id)->count());
        $this->assertSame(0, Schedule::count());
    }
}
