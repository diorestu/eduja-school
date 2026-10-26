<section class="portal-card mt-4"><h2>Pembayaran transfer</h2><p class="portal-muted">Tagihan diperbarui setelah bukti disetujui oleh bendahara atau teller.</p>
    @foreach($invoices as $invoice)
    <details class="mt-4"><summary>{{ $invoice->invoice_number ?: 'Tagihan #'.$invoice->id }} · {{ $invoice->status }} · Rp {{ number_format($invoice->remaining_amount,0,',','.') }}</summary>
        <p>Jatuh tempo {{ $invoice->due_date?->format('d M Y') ?: 'Belum ditentukan' }}</p>
        @if($invoice->remaining_amount > 0 && $bankAccounts->isNotEmpty())
        <form method="POST" action="{{ route('portal.payments.store') }}" enctype="multipart/form-data" class="work-fields mt-3">@csrf<input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
            <label>Rekening tujuan<select name="account_id" required>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->bank_name }} · {{ $account->account_number }} · {{ $account->name }}</option>@endforeach</select></label>
            <label>Nominal transfer<input type="number" name="amount" min="0.01" step="0.01" max="{{ $invoice->remaining_amount }}" value="{{ $invoice->remaining_amount }}" required></label>
            <label>Tanggal transfer<input type="date" name="payment_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></label>
            <label>Bukti transfer<input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" required></label><p class="portal-muted">PDF/JPG/PNG maksimal 5 MB.</p><button class="portal-action">Kirim bukti transfer</button>
        </form>
        @elseif($invoice->remaining_amount > 0)<p class="portal-muted">Rekening bank sekolah belum tersedia. Hubungi bendahara.</p>@endif
    </details>
    @endforeach
    <h3 class="mt-4">Status verifikasi</h3>@forelse($paymentSubmissions as $payment)<p class="mt-2">{{ $payment->payment_date->format('d M Y') }} · Rp {{ number_format($payment->amount,0,',','.') }} · {{ ['pending'=>'Menunggu verifikasi','approved'=>'Disetujui','rejected'=>'Ditolak'][$payment->status] ?? $payment->status }} @if($payment->review_note) · {{ $payment->review_note }} @endif</p>@empty<p class="portal-muted">Belum ada bukti transfer yang dikirim.</p>@endforelse
</section>
