<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PageReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_PROCESSED = 'processed';

    protected $fillable = [
        'reported_user_id',
        'reported_page_name_snapshot',
        'reported_page_url_snapshot',
        'report_count',
        'first_reported_at',
        'last_reported_at',
        'last_report_type',
        'last_report_message',
        'last_reporter_user_id',
        'last_reporter_ip_hash',
        'status',
        'processed_at',
        'processed_by_user_id',
        'moderator_comment',
    ];

    protected $casts = [
        'first_reported_at' => 'datetime',
        'last_reported_at' => 'datetime',
        'processed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function lastReporter()
    {
        return $this->belongsTo(User::class, 'last_reporter_user_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function events()
    {
        return $this->hasMany(PageReportEvent::class, 'page_report_id');
    }
}
