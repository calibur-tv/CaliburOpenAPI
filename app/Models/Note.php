<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'type',         // 类型：文章、评论、图集、问题、答案、投票、视频
        'parent_id',    // 归属：如果是文章，那就是 zone_id，如果是评论，那就是文章 id
        'plugins'       // 插件：是一个 json，可以插入其它的模型
    ];
}
