<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'hash',
        'meta'
    ];

    public function setMetaAttribute($meta)
    {
        $this->attributes['meta'] = json_encode($meta);
    }

    public function getMetaAttribute($meta)
    {
        $result = json_decode($meta);
        $result->url = 'https://web.calibur.tv/' . $result->filename;
        return $result;
    }
}
