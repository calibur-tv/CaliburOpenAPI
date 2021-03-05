<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    protected $fillable = [
        'name',
        'user_id'
    ];
}
