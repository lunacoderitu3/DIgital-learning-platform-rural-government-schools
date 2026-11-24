<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WhatsAppLog extends Model
{
    use HasFactory, DateFormatTrait;

    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'school_id',
        'user_id',
        'mobile',
        'event',
        'template_name',
        'message',
        'meta_message_id',
        'batch_id',
        'status',
        'response',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function getCreatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('created_at'));
    }
}
