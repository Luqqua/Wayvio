<?php

namespace Modules\AdminExtensions\Models;

use Illuminate\Database\Eloquent\Model;

class SystemEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['event_type', 'severity', 'context', 'created_at'];
    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];
}
