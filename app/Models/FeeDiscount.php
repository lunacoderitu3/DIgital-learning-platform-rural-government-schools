<?php

namespace App\Models;

use App\Services\CachingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class FeeDiscount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'school_id',
        'student_id',
        'fees_id',
        'discount_scope',
        'discount_name',
        'discount_type',
        'discount_value',
        'discount_date',
        'description',
        'created_by'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function fee()
    {
        return $this->belongsTo(Fee::class, 'fees_id')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(FeeDiscountItem::class);
    }

    public function logs()
    {
        return $this->hasMany(FeeDiscountLog::class);
    }

    public function getDiscountDateAttribute($value)
    {
        $cache = app(CachingService::class);
        $schoolSettings = $cache->getSchoolSettings();
        $dateFormat = $schoolSettings['date_format'] ?? 'Y-m-d';

        if ($value) {
            $value = date($dateFormat, strtotime($value));
        }

        return $value;
    }
}
