<dialog x-ref="paymentModal" class="account-dialog work max-w-lg w-full" aria-labelledby="committee-payment-title" @close="savingPay = false; confirmingPayment = false">
    <div class="account-dialog-head">
        <h2 id="committee-payment-title" class="font-semibold">Pembayaran Tagihan</h2>
        <button type="button" class="work-btn" @click="closePaymentModal()" :disabled="savingPay" aria-label="Tutup pembayaran">Tutup</button>
    </div>
    <form x-ref="payForm" method="POST" :action="'/spp/transaksi/' + payInvoiceId + '/bayar'" class="space-y-4 py-4 text-sm">
        @csrf
        <input type="hidden" name="invoice_id" :value="payInvoiceId">
        <input type="hidden" name="_billing_action" value="payment">
        <p class="font-medium" x-text="payStudent?.name || ''"></p>
        @if($errors->any() && old('_billing_action') === 'payment')
            <div role="alert" class="text-red-700 dark:text-red-300">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <p x-text="paySelectedInvoice?.item_name || ''"></p>
            <p x-text="'Pokok: ' + formatRupiah(Number(paySelectedInvoice?.principal_amount || paySelectedInvoice?.total_amount || 0))"></p>
            <p x-text="'Denda: ' + formatRupiah(Number(paySelectedInvoice?.late_fee_amount || 0))"></p>
            <p class="font-semibold" x-text="'Sisa: ' + formatRupiah(Number(paySelectedInvoice?.remaining_amount || 0))"></p>
        </div>
        <div>
            <label for="committee-pay-amount" class="block mb-1">Nominal pembayaran</label>
            <input id="committee-pay-amount" name="amount_paid" x-model="payAmount" data-mask="currency" inputmode="numeric" required class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
        </div>
        <div>
            <label for="committee-pay-method" class="block mb-1">Metode pembayaran</label>
            <select id="committee-pay-method" name="payment_method" x-model="payMethod" class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"><option>Tunai</option><option>Transfer Bank</option><option>E-Wallet</option></select>
        </div>
        <div>
            <label for="committee-pay-account" class="block mb-1">Rekening sekolah</label>
            <select id="committee-pay-account" name="account_id" x-model="payAccountId" class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                <option value="">Pilih otomatis sesuai metode</option>
                @foreach($schoolAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="committee-pay-date" class="block mb-1">Tanggal pembayaran</label>
            <input id="committee-pay-date" type="date" name="payment_date" x-model="payDate" required class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
        </div>
        <p x-show="confirmingPayment" role="status">Periksa nominal dan rekening sebelum mencatat pembayaran.</p>
        <div class="flex flex-wrap justify-end gap-2">
            <button type="button" class="work-btn" @click="closePaymentModal()" :disabled="savingPay">Batal</button>
            <button type="button" class="work-btn work-btn-primary" x-show="!confirmingPayment" @click="proceedToConfirmation()">Periksa Pembayaran</button>
            <button type="button" class="work-btn work-btn-primary" x-show="confirmingPayment" @click="submitPayment()" :disabled="savingPay" x-text="savingPay ? 'Menyimpan…' : 'Catat Pembayaran'"></button>
        </div>
    </form>
</dialog>
@if(session('payment_success_modal'))
    <p role="status" class="mt-3 text-sm text-emerald-700 dark:text-emerald-300">Pembayaran berhasil dicatat. Status dan sisa piutang telah diperbarui.</p>
@endif
