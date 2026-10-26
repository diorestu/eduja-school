<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningMaterial extends Model
{
    protected $fillable = ['school_id','user_id','school_class_id','title','description','file_path','file_name'];
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
    public function schoolClass() { return $this->belongsTo(SchoolClass::class); }
}
