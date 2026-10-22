<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RosterReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['comparison' => 'array', 'roster_date' => 'date', 'completed_at' => 'datetime'];

    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
