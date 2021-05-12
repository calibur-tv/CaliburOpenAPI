<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Relation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'attach_id',    // 操作者
        'detach_id',    // 被操作者
        'type',         // 类型：1. Group
        'value'         // 具体的值是什么
    ];
}
