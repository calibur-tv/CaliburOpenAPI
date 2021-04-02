<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Search extends Model
{
    protected $table = 'searches';

    protected $fillable = [
        'uuid',
        'text',
        'type',
        'score'
    ];

    public static function createSearch($data)
    {
        $search = self
            ::where('type', $data['type'])
            ->where('uuid', $data['uuid'])
            ->first();

        if ($search)
        {
            return $search;
        }

        $search = self::create($data);

        return $search;
    }
}
