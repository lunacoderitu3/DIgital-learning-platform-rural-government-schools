<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Traits\DateFormatTrait;

class School extends Model
{
    use SoftDeletes;
    use HasFactory;
    use DateFormatTrait;
    protected $fillable = [
        'name',
        'address',
        'country_code',
        'support_phone',
        'support_email',
        'tagline',
        'logo',
        'admin_id',
        'status',
        'domain',
        'database_name',
        'code',
        'type',
        'domain_type',
        'installed',
        'progress',
        'provision_step',
        'provision_status',
        'provision_progress',
        'provision_retry_count',
        'provision_last_error',
        'provision_last_retry_at',
        'provision_started_at',
        'provision_completed_at',
    ];

    /** Provisioning steps in order with percentage milestones */
    public const STEPS = [
        'pending'                  => ['label' => 'Queued...',                 'progress' => 0],
        'creating_database'         => ['label' => 'Creating Database...',      'progress' => 10],
        'migrating'                => ['label' => 'Migrating Tables...',       'progress' => 35],
        'seeding'                  => ['label' => 'Configuring Defaults...',   'progress' => 55],
        'creating_admin'           => ['label' => 'Creating Administrator...', 'progress' => 75],
        'configuring_tenant'       => ['label' => 'Verifying Tenant...',       'progress' => 90],
        'completed'                => ['label' => 'Setup Complete',            'progress' => 100],
        'failed'                   => ['label' => 'Setup Failed',              'progress' => 0],

        // Legacy aliases retained for visual compatibility with older UI states.
        'job_started'              => ['label' => 'Initializing...',          'progress' => 0],
        'database_created'         => ['label' => 'Creating Database...',      'progress' => 10],
        'migrations_completed'     => ['label' => 'Migrating Tables...',       'progress' => 35],
        'seeders_executed'         => ['label' => 'Configuring Defaults...',   'progress' => 55],
        'school_activated'         => ['label' => 'Activating School...',      'progress' => 85],
        'welcome_email_sent'       => ['label' => 'Sending Welcome Email...',  'progress' => 93],
        'verification_email_sent'  => ['label' => 'Sending Verification...',   'progress' => 98],
        'provisioning_completed'   => ['label' => 'Setup Complete',            'progress' => 100],
    ];

    protected $casts = [
        'progress' => 'array',
    ];

    protected $hidden = ['database_name'];
    protected $appends = ['original_created_at', 'original_updated_at'];
    //Getter Attributes
    public function getLogoAttribute($value)
    {
        return url(Storage::url($value));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    public function subscription()
    {
        return $this->hasMany(Subscription::class);
    }

    public function addon()
    {
        $today_date = Carbon::now()->format('Y-m-d');
        return $this->hasManyThrough(Feature::class, AddonSubscription::class, 'school_id', 'id', 'id', 'feature_id')
            ->where('start_date', '<=', $today_date)->where('end_date', '>=', $today_date);
    }

    public function features()
    {
        $today_date = Carbon::now()->format('Y-m-d');
        return $this->hasManyThrough(SubscriptionFeature::class, Subscription::class)->where('start_date', '<=', $today_date)->where('end_date', '>=', $today_date);
    }

    public function test()
    {
        return $this->features->merge($this->addon);
    }

    public function extra_school_details()
    {
        return $this->hasMany(ExtraSchoolData::class, 'school_id', 'id');
    }

    public function getCreatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('created_at'));
    }

    public function getUpdatedAtAttribute()
    {
        return $this->formatDateValue($this->getRawOriginal('updated_at'));
    }
    public function getOriginalCreatedAtAttribute()
    {
        return $this->getRawOriginal('created_at');
    }

    public function getOriginalUpdatedAtAttribute()
    {
        return $this->getRawOriginal('updated_at');
    }

    public function school_board()
    {
        return $this->hasOne(SchoolBoard::class, 'school_id', 'id');
    }
}
