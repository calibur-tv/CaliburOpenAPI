<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Relation extends Model
{
    protected $fillable = [
        'attach_id',    // 操作者
        'detach_id',    // 被操作者
        'type',         // 类型是什么
        'value'         // 具体的值是什么
    ];
}
