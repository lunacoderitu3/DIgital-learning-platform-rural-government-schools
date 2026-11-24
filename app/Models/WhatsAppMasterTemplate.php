<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMasterTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_master_templates';
    protected $connection = 'mysql';

    protected $fillable = [
        'event',
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
}
