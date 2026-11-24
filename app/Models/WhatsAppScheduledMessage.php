<?php

namespace App\Models;

use App\Traits\DateFormatTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WhatsAppScheduledMessage extends Model
{
    use HasFactory, DateFormatTrait;

    protected $table = 'whatsapp_scheduled_messages';

    protected $fillable = [
        'school_id',
        'template_id',
        'user_ids',
        'contacts',
        'variables',
        'media',
        'schedule_type',
        'next_run_at',
        'last_run_at',
        'status',
        'created_by',
    ];

    protected $casts = [
        'variables'   => 'array',
        'contacts'    => 'array',
        'media'       => 'array',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }

    public function userIdsArray(): array
    {
        return array_values(array_filter(explode(',', (string) $this->user_ids)));
    }
}
