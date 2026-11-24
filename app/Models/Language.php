<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\DateFormatTrait;
use Illuminate\Support\Facades\Storage;

class Language extends Model {
    use HasFactory;
    use DateFormatTrait;

    protected $fillable = [
        'id',
        'name',
        'code',
        'file',
        'student_app_file',
        'staff_app_file',
        'web_file',
        'image',
        'status',
        'is_rtl'
    ];
    protected $connection = 'mysql';

    public function getImageAttribute($value)
    {
        return $value ? url(Storage::url($value)) : null;
    }

    public function getRawImageAttribute()
    {
        return $this->getRawOriginal('image');
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
