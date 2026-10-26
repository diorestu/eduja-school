<?php
namespace App\Http\Controllers;

use App\Models\{ApprovalRequest, Invoice, PaymentSubmission, Student};
use App\Services\{BillingPaymentService, SchoolContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PortalPaymentController extends Controller
{
    public function store(Request $request, SchoolContext $context, BillingPaymentService $payments)
    {
        $schoolId = $context->activeSchoolIdFor();
        $data = $request->validate(['invoice_id'=>['required','integer'],'account_id'=>['required','integer'],'payment_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'amount'=>['required','numeric','min:0.01','decimal:0,2'],'proof'=>['required','file','mimes:pdf,jpg,jpeg,png','max:5120']]);
        $invoice = Invoice::ownedBySchool($schoolId)->whereKey($data['invoice_id'])->whereHas('student',fn ($q)=>$q->active()->where('school_id',$schoolId)
            ->where(fn ($own)=>$own->where('user_id',$request->user()->id)->orWhere('guardian_user_id',$request->user()->id)))->firstOrFail();
        $path = null;
        try {
            DB::transaction(function () use ($invoice,$request,$data,$schoolId,$payments,&$path) {
                $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                if (PaymentSubmission::where('school_id',$schoolId)->where('invoice_id',$invoice->id)->where('status','pending')->exists()) {
                    throw ValidationException::withMessages(['invoice_id'=>'Bukti pembayaran tagihan ini masih menunggu verifikasi.']);
                }
                $payments->refreshCharges($invoice,now());
                $payments->validatePayment($invoice,$data['amount']);
                $account = $payments->destinationAccount($schoolId,'Transfer Bank',$data['account_id'],true,(bool) $invoice->billing_period_id);
                if (strtolower($account->type) !== 'bank') throw ValidationException::withMessages(['account_id'=>'Pembayaran portal harus ke rekening bank sekolah.']);
                $path = $request->file('proof')->store('payment-proofs/'.$schoolId,'local');
                $payment = PaymentSubmission::create(['school_id'=>$schoolId,'invoice_id'=>$invoice->id,'student_id'=>$invoice->student_id,'submitted_by'=>$request->user()->id,
                    'account_id'=>$account->id,'amount'=>$data['amount'],'payment_date'=>$data['payment_date'],'payment_method'=>'Transfer Bank','proof_path'=>$path,'status'=>'pending']);
                ApprovalRequest::create(['school_id'=>$schoolId,'requested_by'=>$request->user()->id,'type'=>'payment','approvable_type'=>PaymentSubmission::class,'approvable_id'=>$payment->id,'status'=>'pending']);
            });
        } catch (\Throwable $e) { if ($path) Storage::disk('local')->delete($path); throw $e; }
        return back()->with('success','Bukti transfer dikirim. Pembayaran menunggu verifikasi bendahara atau teller.');
    }
}
