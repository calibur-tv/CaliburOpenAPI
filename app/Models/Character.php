<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Character extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'alias',
        'intro',
        'avatar'
    ];
}
