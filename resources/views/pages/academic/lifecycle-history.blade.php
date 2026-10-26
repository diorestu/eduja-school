@if($history->isNotEmpty())
<details class="mb-3 text-xs"><summary class="cursor-pointer font-medium">Riwayat status ({{ $history->count() }})</summary>
    @foreach($history as $change)
    @php($metadata = json_decode($change->metadata ?? '{}', true) ?? [])
    <div class="my-2 border-l pl-2">
        <p>{{ $change->from_status }} → {{ $change->to_status }} · {{ $change->effective_date }}</p>
        @if($change->reason)<p>{{ $change->reason }}</p>@endif
        @if(!empty($metadata['transfer_destination']))<p>Tujuan: {{ $metadata['transfer_destination'] }}</p>@endif
        @if(!empty($metadata['contract_start_date']))<p>Kontrak: {{ $metadata['contract_start_date'] }} — {{ $metadata['contract_end_date'] ?? '—' }}</p>@endif
        @foreach(['transfer_document_path' => 'Surat pindah', 'status_document_path' => 'Dokumen pendukung'] as $kind => $label)
            @if(!empty($metadata[$kind]))
                @if($personType === 'student' && Route::has('siswa.lifecycle-document'))<a class="mr-2 underline" href="{{ route('siswa.lifecycle-document', ['student' => $person->id, 'history' => $change->id, 'kind' => $kind]) }}">{{ $label }}</a>
                @elseif($personType === 'teacher' && Route::has('guru.document'))<a class="underline" href="{{ route('guru.document', ['teacher' => $person->id, 'record' => $change->id, 'kind' => 'lifecycle']) }}">{{ $label }}</a>@endif
            @endif
        @endforeach
    </div>
    @endforeach
</details>
@endif
