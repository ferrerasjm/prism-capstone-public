<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentStatusBatch extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['rows' => 'array', 'effective_on' => 'date', 'applied_at' => 'datetime'];

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }
}
