<x-filament-panels::page>

    <div class="visible-print text-center">
        {!! QrCode::size(100)->generate($record->email); !!}
        <p>{{ $record->email }}</p>
    </div>

</x-filament-panels::page>
