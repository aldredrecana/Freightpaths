<x-form-page crumb="Schedules" :title="isset($backloadOf) && $backloadOf ? 'Offer backload' : 'Assign trip'" :subtitle="isset($backloadOf) && $backloadOf
    ? 'A return load after ' .
        $backloadOf->reference .
        ', so the truck does not drive back empty. The driver can accept or decline it.'
    : 'Choose a driver and a truck, then set the route and times.'" :action="route('schedules.store')" :cancel="isset($backloadOf) && $backloadOf ? route('schedules.show', $backloadOf) : route('schedules.index')" :submit="isset($backloadOf) && $backloadOf ? 'Offer backload' : 'Assign trip'">
    @if (isset($backloadOf) && $backloadOf)
        <input type="hidden" name="backload_of_id" value="{{ $backloadOf->id }}">
        <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            <span class="font-semibold">Backload for {{ $backloadOf->reference }}</span> &middot;
            {{ $backloadOf->driver->user->name }} &middot; {{ $backloadOf->truck->plate_no }}.
            We filled in the return route (from the drop-off back toward the pickup) and a start time after
            {{ $backloadOf->reference }} should arrive. Change anything you need.
        </div>
    @endif
    @include('schedules._form')
</x-form-page>
