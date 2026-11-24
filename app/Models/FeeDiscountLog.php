<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeDiscountLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_discount_id',
        'action',
        'old_data',
        'new_data',
        'user_id'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function fee_discount()
    {
        return $this->belongsTo(FeeDiscount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
