<?php

namespace Modules\Tiers\Models;

use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'max_pages', 'max_links_per_page',
        'analytics_enabled', 'custom_domain_enabled', 'design_customization_enabled',
        'price_1m', 'price_3m', 'price_6m',
    ];
}
