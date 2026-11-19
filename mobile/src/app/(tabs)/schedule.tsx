import { TripList } from "../../components/TripList";
import { useTrips } from "../../trips";

export default function ScheduleScreen() {
  const { active } = useTrips();

  return (
    <TripList
      title="Schedule"
      list={active}
      emptyIcon="calendar-outline"
      emptyTitle="No active trips"
      emptyText="New trips from your dispatcher appear here. Pull down to refresh."
    />
  );
}
