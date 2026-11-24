<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'school_id',
        'event',
        'type',
        'category',
        'name',
        'language',
        'meta_template_name',
        'meta_body_param',
        'header',
        'body',
        'footer',
        'status',
    ];

    protected $casts = [
        'status'          => 'boolean',
        'meta_body_param' => 'boolean',
    ];

    protected $connection = 'school';

    public function scopeAutomatic($query)
    {
        return $query->where('type', 'automatic');
    }

    public function scopeManual($query)
    {
        return $query->where('type', 'manual');
    }

    public function scopeOwner($query)
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $query->where('school_id', Auth::user()->school_id);
        }
        return $query;
    }
}
