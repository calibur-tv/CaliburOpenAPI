<?php


namespace App\Modules\Spider\Base;


use App\Http\Repositories\Repository;
use App\Services\Qiniu\Qshell;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use QL\QueryList;
use App\Services\Qiniu\Http\Client;

class GetResourceService
{
    protected $userTable = 'spider_users';
    protected $dataTable = 'spider_resource';
    protected $repeatIds = [];
    protected $siteMap = [
        'bilibili' => 1
    ];
    /**
     * 资源网站的类型
     * 1. bilibili
     */
    /**
     * source_type
     * 数据列的类型，根据 user 的 rule 来填制，默认为 0
     * 1. 动画区
     * 2. 游戏区
     * 3. 学习区
     */
    protected $siteType;

    // TODO：计算数据热度
    public function __construct($siteType = 0)
    {
        $this->siteType = $siteType;
    }

    public function getUser($userId, $channel)
    {
        return DB
            ::table($this->userTable)
            ->where('site_type', $this->siteMap[$channel])
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 自动抓取和更新全部数据
     */
    public function autoload()
    {
        $this->updateOldResources();
        $this->getNewestResources();
        $this->clearRepeatData();
    }

    public function setUser($id, $rule = '', $selfId = '')
    {
        if (!$id)
        {
            return null;
        }

        $userId = DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->where('user_id', $id)
            ->pluck('id')
            ->first();

        $now = Carbon::now();
        if ($userId)
        {
            DB
                ::table($this->userTable)
                ->where('site_type', $this->siteType)
                ->where('user_id', $id)
                ->update([
                    'rule' => json_encode($rule),
                    'updated_at' => $now
                ]);
        }
        else
        {
            $userId = DB
                ::table($this->userTable)
                ->insertGetId([
                    'site_type' => $this->siteType,
                    'user_id' => $id,
                    'self_id' => $selfId,
                    'rule' => json_encode($rule),
                    'updated_at' => $now,
                    'created_at' => $now
                ]);
        }

        event(new \App\Events\Spider\AddUser($this->siteType, $id));

        return DB
            ::table($this->userTable)
            ->where('id', $userId)
            ->get()
            ->first();
    }

    public function delUser($id, $withData = false)
    {
        if (!$id)
        {
            return false;
        }

        $user = DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->where('user_id', $id)
            ->first();

        if (!$user)
        {
            return false;
        }

        if ($user->deleted_at)
        {
            DB
                ::table($this->userTable)
                ->where('site_type', $this->siteType)
                ->where('user_id', $id)
                ->update([
                    'deleted_at' => null
                ]);

            DB
                ::table($this->dataTable)
                ->where('site_type', $this->siteType)
                ->where('author_id', $id)
                ->update([
                    'deleted_at' => null
                ]);

            return true;
        }

        $now = Carbon::now();

        DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->where('user_id', $id)
            ->update([
                'deleted_at' => $now
            ]);

        if ($withData)
        {
            DB
                ::table($this->dataTable)
                ->where('site_type', $this->siteType)
                ->where('author_id', $id)
                ->update([
                    'deleted_at' => $now
                ]);
        }

        return true;
    }

    /**
     * TODO：当数据量过大的时候，这个 for 循环很多很多
     * 根据稿件id更新老的存量数据
     */
    public function updateOldResources($forceRefresh = false, $authorId = '')
    {
        $list = DB
            ::table($this->dataTable)
            ->where('site_type', $this->siteType)
            ->when($authorId, function ($query) use ($authorId)
            {
                return $query->where('author_id', $authorId);
            })
            ->whereNull('deleted_at')
            ->select(['id', 'model_id', 'model_type', 'data'])
            ->get()
            ->toArray();

        if (empty($list))
        {
            return true;
        }

        $now = Carbon::now();
        foreach ($list as $row)
        {
            try
            {
                $data = $this->getDataItem(
                    $row->model_id,
                    $row->model_type,
                    $forceRefresh ? null : json_decode($row->data, true)
                );

                DB
                    ::table($this->dataTable)
                    ->where('id', $row->id)
                    ->update([
                        'data' => json_encode($data),
                        'updated_at' => $now
                    ]);
            }
            catch (\Exception $e)
            {
                // do nothing
            }
        }

        return true;
    }

    public function reportResource($ids, $type)
    {
        DB
            ::table($this->dataTable)
            ->whereIn('id', $ids)
            ->increment("{$type}_count");
    }

    public function spiderHots($day, $refresh = false)
    {
        $repository = new Repository();
        $cache = $repository->RedisList("spider-hot-flow-{$day}", function () use ($day)
        {
            $list = DB
                ::table($this->dataTable)
                ->whereNull('deleted_at')
                ->orderBy('click_count', 'DESC')
                ->take(8)
                ->get();

            $result = [];
            foreach ($list as $row)
            {
                $result[] = json_encode($row);
            }

            return $result;
        }, $refresh, 'h');

        $result = array_map(function ($item)
        {
            $res = json_decode($item, true);
            $res['data'] = json_decode($res['data'], true);

            return $res;
        }, $cache);

        return $result;
    }

    /**
     * 根据用户id获取最新的数据源
     */
    public function getNewestResources($authorId = '')
    {
        $users = DB
            ::table($this->userTable)
            ->where('site_type', $this->siteType)
            ->when($authorId, function ($query) use ($authorId)
            {
                return $query->where('user_id', $authorId);
            })
            ->whereNull('deleted_at')
            ->pluck('rule', 'user_id')
            ->toArray();

        if (empty($users))
        {
            return true;
        }

        foreach ($users as $userId => $rule)
        {
            try
            {
                $list = $this->getUserList($userId, json_decode($rule, true));
            }
            catch (\Exception $e)
            {
                $list = [];
            }

            foreach ($list as $i => $row)
            {
                $has = DB
                    ::table($this->dataTable)
                    ->where('model_id', $row['model_id'])
                    ->where('site_type', $row['site_type'])
                    ->count();

                if ($has)
                {
                    unset($list[$i]);
                }
            }

            DB::table($this->dataTable)->insert($list);
        }

        return true;
    }

    public function getDataItem($id, $type, $data)
    {
        return '';
    }

    public function getUserList($id, $rule)
    {
        return [];
    }

    public function getSpider($url)
    {
        return QueryList::get($url, [], [
            'timeout' => 10
        ]);
    }

    public function getClient($url)
    {
        $client = new Client();
        $resp = $client->get($url);
        $body = json_decode($resp->body, true);

        return $body;
    }

    public function getImage($url)
    {
        $qshell = new Qshell();

        return patchImage($qshell->fetch($url));
    }

    public function getAllUser()
    {
        return DB::table($this->userTable)->get()->toArray();
    }

    public function getFlowData($sort, $slug, $page, $take, $randId, $refresh = false)
    {
        $repository = new Repository();
        $cache = $repository->RedisList("spider-mixin-flow-{$slug}-{$sort}-{$randId}", function () use ($slug, $sort, $randId)
        {
            $list = DB
                ::table($this->dataTable)
                ->whereNull('deleted_at')
                ->when($randId, function ($query) use ($randId)
                {
                    return $query->where(DB::raw('id % 10'), $randId);
                })
                ->when($slug, function ($query) use ($slug)
                {
                    return $query->where('source_type', $slug);
                })
                ->when($sort, function ($query) use ($sort)
                {
                    if ($sort === 'newest')
                    {
                        return $query->orderBy('created_at', 'DESC');
                    }
                    else if ($sort === 'activity')
                    {
                        return $query->orderBy('updated_at', 'DESC');
                    }
                    return $query->orderBy('score', 'DESC');
                })
                ->get();

            $result = [];
            foreach ($list as $row)
            {
                $result[] = json_encode($row);
            }

            return $result;
        }, $refresh, 'h');

        $obj = $repository->filterIdsByPage($cache, $page, $take);
        $result = array_map(function ($item)
        {
            $res = json_decode($item, true);
            $res['data'] = json_decode($res['data'], true);

            return $res;
        }, $obj['result']);

        $obj['result'] = $result;

        return $obj;
    }

    public function clearRepeatData()
    {
        $ids = $this->getRepeatIds();

        if (empty($ids))
        {
            return true;
        }

        $this->repeatIds = $ids;

        while (!empty($this->repeatIds))
        {
            DB
                ::table($this->dataTable)
                ->whereIn('id', $this->repeatIds)
                ->delete();

            $this->repeatIds = $this->getRepeatIds();
        }

        return true;
    }

    protected function getRepeatIds()
    {
        $ids = DB
            ::table($this->dataTable)
            ->select(DB::raw('MIN(id) AS id'))
            ->groupBy(['site_type', 'model_type', 'model_id'])
            ->havingRaw('COUNT(id) > 1')
            ->pluck('id')
            ->toArray();

        return $ids;
    }
}
