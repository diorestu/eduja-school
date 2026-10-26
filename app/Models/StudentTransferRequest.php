<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentTransferRequest extends Model {
    protected $guarded=[];
    protected $casts=['transfer_date'=>'date','reviewed_at'=>'datetime'];
    public function student() { return $this->belongsTo(Student::class); }
}
