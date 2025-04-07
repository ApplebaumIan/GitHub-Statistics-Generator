<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartRequest extends Model
{
    //
    protected $table = 'chart_requests';
    protected $fillable = [
      'cache_key',
        'hit_count',
        'last_accessed_at',
    ];
}
