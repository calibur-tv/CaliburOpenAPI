<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\Repository;
use App\Models\Bangumi;
use Illuminate\Http\Request;

class BangumiController extends Controller
{
    public function all()
    {
        $repository = new Repository();

        $result = $repository->RedisItem('bangumi-all-search', function ()
        {
            return Bangumi
                ::select('slug', 'alias')
                ->get();
        });

        return $this->resOK([
            'result' => $result,
            'total' => count($result),
            'no_more' => true
        ]);
    }
}
