import { TripList } from "../../components/TripList";
import { useTrips } from "../../trips";

export default function HistoryScreen() {
  const { history } = useTrips();

  return (
    <TripList
      title="History"
      list={history}
      emptyIcon="time-outline"
      emptyTitle="No finished trips yet"
      emptyText="Delivered and declined trips show up here."
    />
  );
}
