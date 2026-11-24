<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;

class IncomeCategory extends Model
{
    use HasFactory, SoftDeletes, DateFormatTrait;

    protected $fillable = ['name', 'description', 'status', 'school_id', 'created_by', 'updated_by'];

    public function scopeOwner()
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $this->where('school_id', Auth::user()->school_id);
        }
        return $this;
    }

    /**
     * Get all of the incomes for the IncomeCategory
     *
     * @return HasMany
     */
    public function incomes()
    {
        return $this->hasMany(Income::class, 'income_category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getCreatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('created_at'));
    }

    public function getUpdatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('updated_at'));
    }
}
