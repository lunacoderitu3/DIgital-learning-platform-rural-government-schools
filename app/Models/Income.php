<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use App\Traits\DateFormatTrait;

class Income extends Model
{
    use HasFactory, DateFormatTrait;

    protected $fillable = [
        'income_category_id',
        'title',
        'reference_no',
        'amount',
        'date',
        'description',
        'school_id',
        'session_year_id',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['original_date'];

    /**
     * Income can belong to any session year (it is created session-year-wise),
     * so the owner scope only restricts by school. Session-year filtering is
     * applied explicitly by the datatable / dashboard queries.
     */
    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    /**
     * Get the category that owns the Income
     *
     * @return BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id', 'id')->withTrashed();
    }

    public function sessionYear()
    {
        return $this->belongsTo(SessionYear::class, 'session_year_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Alias used by report views / CreatedByNameFormatter (mirrors Expense model).
    public function created_by()
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

    public function getDateAttribute($value)
    {
        return $this->formatDateOnly($value);
    }

    public function getOriginalDateAttribute()
    {
        return $this->getRawOriginal('date');
    }
}
