<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Desk extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'file_id',
        'user_id',
        'folder_id'
    ];

    public function file()
    {
        return $this->hasOne('App\Models\File', 'id', 'file_id');
    }
}
