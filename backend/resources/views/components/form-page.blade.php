@props(['crumb', 'title', 'subtitle' => null, 'action', 'method' => 'POST', 'cancel', 'submit' => 'Save'])
<x-app-layout>
    <div class="px-4 py-5 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <x-page-header :crumb="$crumb" :title="$title" :subtitle="$subtitle" />

            <x-card class="mt-4 p-6">
                <form method="POST" action="{{ $action }}">
                    @csrf
                    @if (strtoupper($method) !== 'POST')
                        @method($method)
                    @endif

                    {{ $slot }}

                    <div class="mt-8 flex items-center gap-2 border-t border-gray-100 pt-5">
                        <x-btn>{{ $submit }}</x-btn>
                        <x-btn variant="secondary" :href="$cancel">Cancel</x-btn>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
