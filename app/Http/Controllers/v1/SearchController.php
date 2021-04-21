<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\Repository;
use App\Models\Bangumi;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("搜索相关接口")
 */
class SearchController extends Controller
{
    /**
     * 搜索接口
     *
     * > 目前支持的参数格式：
     * type：all, user，bangumi，character
     *
     * @Get("/v1/search/mixin")
     *
     * @Parameters({
     *      @Parameter("type", description="要检测的类型", type="string", required=true),
     *      @Parameter("q", description="搜索的关键词", type="string", required=true),
     *      @Parameter("page", description="搜索的页码", type="integer", required=true)
     * })
     *
     * @Transaction({
     *      @Response(200, body="数据列表")
     * })
     */
    public function mixin(Request $request)
    {
        $q = $request->get('q');
        if (!$q || !($q = trim($q)) || !($q = Purifier::clean($q)))
        {
            return $this->resOK([
                'total' => 0,
                'result' => [],
                'no_more' => true
            ]);
        }

        $type = $request->get('type') ?: 'all';
        $page = intval($request->get('page')) ?: 1;

        $search = new Search();
        $result = $search->get(strtolower($q), $type, $page - 1);

        return $this->resOK($result);
    }

    public function bangumi()
    {
        $repository = new Repository();

        $result = $repository->RedisItem('bangumi-all-search-v2', function ()
        {
            return Bangumi
                ::select('slug', 'alias', 'title AS text')
                ->get();
        });

        return $this->resOK([
            'result' => $result,
            'total' => count($result),
            'no_more' => true
        ]);
    }
}
