<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatabaseBackup extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'school_id', 'status', 'file_path', 'error_message'];

    protected $connection = 'mysql';

    public function scopeOwner()
    {
        if (Auth::user() && Auth::user()->school_id) {
            return $this->where('school_id', Auth::user()->school_id);
        }
        return $this;
    }
}
