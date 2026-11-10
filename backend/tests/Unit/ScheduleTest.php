<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\Schedule;
use App\Models\User;
use Tests\TestCase;

/** Pure model logic (no database rows needed). */
class ScheduleTest extends TestCase
{
    private function userWithRole(string $role): User
    {
        return (new User)->setRelation('role', new Role(['name' => $role]));
    }

    public function test_the_reference_is_zero_padded(): void
    {
        $trip = new Schedule;
        $trip->id = 5;

        $this->assertSame('FP-0005', $trip->reference);
    }

    public function test_drivers_move_forward_and_staff_can_only_cancel(): void
    {
        $trip = new Schedule(['status' => 'pending']);

        $this->assertSame(['accepted'], $trip->allowedNext($this->userWithRole('driver')));
        $this->assertSame(['cancelled'], $trip->allowedNext($this->userWithRole('dispatcher')));
        $this->assertSame(['cancelled'], $trip->allowedNext($this->userWithRole('admin')));
    }

    public function test_finished_trips_have_no_next_status(): void
    {
        foreach (Schedule::FINISHED as $status) {
            $trip = new Schedule(['status' => $status]);

            $this->assertSame([], $trip->allowedNext($this->userWithRole('admin')));
            $this->assertTrue($trip->isFinished());
        }

        $this->assertFalse((new Schedule(['status' => 'en_route']))->isFinished());
    }

    public function test_urgency_depends_on_how_close_the_departure_is(): void
    {
        $this->assertNull((new Schedule(['status' => 'pending', 'departure_at' => now()->addDay()]))->urgency);
        $this->assertSame('urgent', (new Schedule(['status' => 'pending', 'departure_at' => now()->addHour()]))->urgency);
        $this->assertSame('overdue', (new Schedule(['status' => 'pending', 'departure_at' => now()->subHour()]))->urgency);
        $this->assertSame('overdue', (new Schedule(['status' => 'needs_reassignment', 'departure_at' => now()->subHour()]))->urgency);

        // Only trips still waiting for a driver can be urgent.
        $this->assertNull((new Schedule(['status' => 'en_route', 'departure_at' => now()->subHour()]))->urgency);
    }

    public function test_every_status_has_a_defined_next_step(): void
    {
        foreach (Schedule::ACTIVE as $status) {
            $this->assertArrayHasKey($status, Schedule::NEXT);
        }

        foreach (Schedule::FINISHED as $status) {
            $this->assertSame([], Schedule::NEXT[$status]);
        }
    }
}
