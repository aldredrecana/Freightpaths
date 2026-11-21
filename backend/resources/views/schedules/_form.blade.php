@php
    $isEdit = isset($schedule);
    $driverOptions = $drivers->mapWithKeys(fn($d) => [$d->id => $d->user->name . ' (' . $d->license_no . ')'])->all();
    $truckOptions = $trucks->mapWithKeys(fn($t) => [$t->id => $t->plate_no . ' - ' . $t->model])->all();

    // Map pins: the old value after a failed save, else the saved trip, else none.
    $editing = $isEdit ? $schedule : null;
    $pin = function (string $kind) use ($editing) {
        $lat = old($kind . '_lat', $editing?->{$kind . '_lat'});
        $lng = old($kind . '_lng', $editing?->{$kind . '_lng'});

        return filled($lat) && filled($lng) ? [(float) $lat, (float) $lng] : null;
    };
@endphp

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <x-form-select name="driver_id" label="Driver" placeholder="Select a driver" :options="$driverOptions" :value="$isEdit ? $schedule->driver_id : null"
        hint="Off-duty drivers are hidden. Overlapping trips are blocked when you save." />
    <x-form-select name="truck_id" label="Truck" placeholder="Select a truck" :options="$truckOptions" :value="$isEdit ? $schedule->truck_id : null"
        hint="Trucks in maintenance are hidden. Overlapping trips are blocked when you save." />
    <x-form-field name="departure_at" label="Departure" type="datetime-local" :value="$isEdit ? $schedule->departure_at->format('Y-m-d\TH:i') : null" />
    <x-form-field name="expected_arrival_at" label="Expected arrival" type="datetime-local" :value="$isEdit ? $schedule->expected_arrival_at->format('Y-m-d\TH:i') : null" />
    <x-form-textarea name="origin_address" label="Origin address" class="md:col-span-2" rows="2"
        :value="$isEdit ? $schedule->origin_address : null" />
    <x-form-textarea name="destination_address" label="Destination address" class="md:col-span-2" rows="2"
        :value="$isEdit ? $schedule->destination_address : null" />

    {{-- Map pins for pickup (A) and drop-off (B). Optional. --}}
    <div class="md:col-span-2" x-data="routePicker(@js([
        'origin' => $pin('origin'),
        'destination' => $pin('destination'),
        'geocodeUrl' => route('geocode'),
        'tileUrl' => config('freightpath.map_tile_url'),
        'attribution' => config('freightpath.map_attribution'),
    ]))">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-gray-700">Map pins <span class="font-normal text-gray-400">(optional)</span></p>
                <p class="text-xs text-gray-500">Find each address, then drag the pin to the exact gate or warehouse.
                    You can also click on the map.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-btn type="button" variant="secondary" class="!px-3 !py-2" @click="find('origin')">Find pickup (A)</x-btn>
                <x-btn type="button" variant="secondary" class="!px-3 !py-2" @click="find('destination')">Find drop-off (B)</x-btn>
            </div>
        </div>

        <p x-show="message" x-text="message" class="mt-2 text-xs text-gray-600" style="display: none;"></p>

        <div x-ref="map" class="mt-3 w-full rounded-lg border border-gray-200" style="height: 20rem; z-index: 0;"></div>

        <div class="mt-2 flex gap-4 text-xs">
            <button type="button" x-show="origin" @click="clear('origin')" class="text-gray-500 hover:underline">Remove pickup pin</button>
            <button type="button" x-show="destination" @click="clear('destination')" class="text-gray-500 hover:underline">Remove drop-off pin</button>
        </div>

        <input type="hidden" name="origin_lat" :value="value('origin', 0)">
        <input type="hidden" name="origin_lng" :value="value('origin', 1)">
        <input type="hidden" name="destination_lat" :value="value('destination', 0)">
        <input type="hidden" name="destination_lng" :value="value('destination', 1)">
        <x-input-error :messages="$errors->get('origin_lat')" class="mt-2" />
        <x-input-error :messages="$errors->get('destination_lat')" class="mt-2" />
    </div>
</div>