<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageReportEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_report_id',
        'reporter_user_id',
        'reporter_ip_hash',
        'report_type',
        'message',
    ];

    public function report()
    {
        return $this->belongsTo(PageReport::class, 'page_report_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
}
