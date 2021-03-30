<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CMBanner extends Model
{
    protected $table = 'cm_banners';

    protected $fillable = [
        'type',
        'title',
        'link',
        'image',
        'visit_count',
        'click_count',
        'online'
    ];

    protected $casts = [
        'online' => 'boolean'
    ];
}
