<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'count_article'
    ];
}
