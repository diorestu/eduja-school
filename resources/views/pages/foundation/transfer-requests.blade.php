@extends('layouts.app')
@section('content')
<div class="work work-stack"><h1>Permohonan pindah sekolah</h1><x-work.feedback />
@forelse($transfers as $transfer)<article class="work-panel"><h2>{{ $transfer->student?->name }}</h2><p>{{ $transfer->destination }} · {{ $transfer->transfer_date->format('d M Y') }} · {{ $transfer->status }}</p><p>{{ $transfer->reason }}</p><a class="work-link" href="{{ route('student-transfers.document',$transfer) }}">Unduh surat pindah</a>
@if($transfer->status==='pending')<form method="POST" action="{{ route('student-transfers.review',$transfer) }}" class="work-fields">@csrf<label>Catatan keputusan<textarea name="note" maxlength="1000"></textarea></label><div class="work-actions"><button class="work-btn" name="decision" value="approved">Setujui pindah</button><button class="work-btn" name="decision" value="rejected">Tolak</button></div></form>@else<p>{{ $transfer->review_note }}</p>@endif
</article>@empty<p class="work-empty">Belum ada permohonan pindah.</p>@endforelse
{{ $transfers->links() }}</div>
@endsection
