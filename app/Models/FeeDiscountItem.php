<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeDiscountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'fee_discount_id',
        'fee_type',
        'fee_type_id',
        'original_amount',
        'discount_amount',
        'final_amount'
    ];

    public function fee_discount()
    {
        return $this->belongsTo(FeeDiscount::class);
    }

    public function optional_fee_type()
    {
        return $this->belongsTo(FeesClassType::class, 'fee_type_id');
    }

    public function installment()
    {
        return $this->belongsTo(FeesInstallment::class, 'fee_type_id');
    }
}
