<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WhatsAppSetting extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'school_id',
        'enabled',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'verify_token',
        'default_meta_template',
        'default_meta_language',
        'default_meta_media_template',
        'default_meta_image_template',
        'webhook_secret',
        'monthly_limit',
        'messages_sent',
        'usage_month',
        'status',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }
}
