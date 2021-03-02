<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Desk extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'mine',
        'size',
        'link',
        'hash',
        'meta',
        'user_id',
        'folder_id'
    ];
}
