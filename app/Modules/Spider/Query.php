<?php


namespace App\Modules\Spider;


use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use QL\QueryList;

class Query
{
    private static $opts = [
        'timeout' => 30,
        'headers' => [
            'Referer' => 'http://bgm.tv/login',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36',
            'Cookie' => 'chii_theme=light; __utma=1.997399125.1583364894.1583801487.1590063301.3; __cfduid=d979a367b45925bba6379c6783da48c821617191255; chii_sec_id=dQ9vSzXPwPA7feuKfLnU1UZwgwtDgcHMZOw0pyo; chii_sid=i6ifP8; chii_cookietime=0; chii_auth=J1pvGTjAwvcufbqAf77DwiUI329Y%2B6%2BwHZEqlQb%2BlUgr5Gm8xim3fNr0H3Y1aV0nwfmTjW8YKJ%2BJxiFpTZp9%2BsgFfX2TTtAbJxHy',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Encoding' => 'gzip, deflate',
            'Accept-Language' => 'zh-CN,zh;q=0.9',
            'Host' => 'bgm.tv',
            'Upgrade-Insecure-Requests' => '1'
        ]
    ];

    public function fetchMeta($url)
    {
        $ql = QueryList::get($url, [], $this::$opts);
        $title = $ql->find('title')->text();
        $description = $ql->find('meta[name=description]')->content;
        $image = $ql->find('img')->src;

        return [
            'title' => $title,
            'description' => $description,
            'image' => [
                'url' => $image
            ]
        ];
    }

    public function searchBangumi($name)
    {
        try
        {
            $query = urlencode($name);
            $url = "http://bgm.tv/subject_search/{$query}?cat=2";
            $ql = QueryList::get($url, [], $this::$opts);
            $result = $ql
                ->find('#browserItemList')
                ->eq(0)
                ->find('.item')
                ->map(function ($item)
                {
                    $id = last(explode('/', $item->find('a.subjectCover')->eq(0)->href));
                    $name = $item->find('a.l')->text();
                    $meta = explode(' / ', $item->find('p.tip')->text());
                    $year = '';
                    $rank = $item->find('.rank')->eq(0)->text() ?: '';
                    if ($rank)
                    {
                        $rank = explode(' ', $rank)[1];
                    }
                    foreach ($meta as $one)
                    {
                        if (preg_match('/(年|\.|-|\/)/', $one))
                        {
                            $year = $one;
                            break;
                        }
                    }

                    if ($year)
                    {
                        $year = explode('---', preg_replace('/(年|\.|-|\/)/', '---', $year))[0];
                        if (strlen($year) === 2)
                        {
                            if ($year[0] === '1')
                            {
                                $year = '19' . $year;
                            }
                            else if ($year[0] === '0')
                            {
                                $year = '20' . $year;
                            }
                        }
                    }

                    return [
                        'id' => $id,
                        'name' => $name,
                        'year' => $year,
                        'rank' => $rank,
                        'meta' => $meta
                    ];
                })
                ->all();

            return $result;
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：search bangumi - name {$name} failed");
            return [];
        }
    }

    public function getBangumiIdols($id)
    {
        try
        {
            $url = "http://bgm.tv/subject/{$id}/characters";
            $ql = QueryList::get($url, [], $this::$opts);
            $result = $ql
                ->find('.light_odd')
                ->filter(':has(h2 .tip)')
                ->map(function ($item)
                {
                    $id = last(explode('/', $item->find('a.avatar')->eq(0)->href));
                    $name = str_replace('/ ', '', $item->find('h2 span')->text());
                    $meta = explode(' / ', $item->find('.crt_info .tip')->text());
                    $meta = array_map(function ($val)
                    {
                        $arr = explode(' ', $val);
                        return count($arr) === 2 ? $arr[1] : '';
                    }, $meta);

                    return [
                        'id' => $id,
                        'name' => $name,
                        'sex' => $meta[0] ?? '',
                        'birthday' => $meta[1] ?? ''
                    ];
                })
                ->all();

            $result = array_filter($result, function ($item)
            {
                return (isset($item['sex']) && $item['sex']) && (isset($item['birthday']) && $item['birthday']);
            });

            $result = array_map(function ($item)
            {
                return $item['id'];
            }, $result);

            return $result;
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：get bangumi - idol {$id} failed");
            return false;
        }
    }

    public function getBangumiTags($id)
    {
        try
        {
            $url = "http://bgm.tv/subject/{$id}";
            $ql = QueryList::get($url, [], $this::$opts);

            return $ql
                ->find('.subject_tag_section')
                ->eq(0)
                ->find('a')
                ->map(function ($link)
                {
                    return trim(strtolower($link->find('span')->eq(0)->text()));
                });
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：get bangumi {$id} tags failed");
            return [];
        }
    }

    public function getBangumiList($page)
    {
        try
        {
            $url = "http://bgm.tv/anime/browser?sort=rank&page={$page}";
            $ql = QueryList::get($url, [], $this::$opts);

            $ids = $ql
                ->find('#browserItemList')
                ->children()
                ->map(function ($item)
                {
                    return last(explode('/', $item->find('.subjectCover')->eq(0)->href));
                })
                ->all();

            $result = [];

            foreach ($ids as $id)
            {
                $result[] = $this->getBangumiDetail($id);
            }

            $result = array_filter($result, function ($item)
            {
                return !!$item;
            });

            return $result;
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：get bangumi page {$page} failed");
            return [];
        }
    }

    public function getIdolDetail($id)
    {
        try
        {
            $url = "http://bgm.tv/character/{$id}";
            $ql = QueryList::get($url, [], $this::$opts);

            $avatar = $ql->find('.infobox')->eq(0)->find('img')->eq(0)->src;
            $meta = explode(PHP_EOL, $ql->find('#infobox')->text());
            $extra = [];
            foreach ($meta as $item)
            {
                $arr = explode(': ', $item);
                $extra[$arr[0]] = $arr[1];
            }

            $detail = trim($ql->find('.detail')->text());
            $extra['alias'] = [];
            $validate = false;
            if (isset($extra['简体中文名']))
            {
                $validate = true;
                $extra['alias'][] = $extra['简体中文名'];
            }
            if (isset($extra['别名']))
            {
                $validate = true;
                $extra['alias'][] = $extra['别名'];
            }

            if (!$validate)
            {
                return null;
            }

            $extra['alias'] = array_unique($extra['alias']);

            return [
                'id' => $id,
                'avatar' => "http:{$avatar}",
                'name' => $extra['alias'][0],
                'intro' => $detail,
                'alias' => array_values($extra['alias'])
            ];
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：get idol {$id} failed");
            return null;
        }
    }

    public function getBangumiDetail($id)
    {
        try
        {
            $url = "http://bgm.tv/subject/{$id}";
            $ql = QueryList::get($url, [], $this::$opts);

            $avatar = $ql
                ->find('.infobox')
                ->eq(0)
                ->find('img')
                ->eq(0)
                ->src;

            $meta = explode(PHP_EOL, $ql->find('#infobox')->text());
            $name = $ql->find('.nameSingle')->eq(0)->find('a')->eq(0)->text();
            $count = 0;
            $publish = '';
            $alias = [];
            foreach ($meta as $item)
            {
                $arr = explode(': ', $item);
                if ($arr[0] === '中文名')
                {
                    $name = trim($arr[1]);
                    $alias[] = $name;
                }
                else if ($arr[0] === '话数')
                {
                    $count = $arr[1];
                }
                else if (
                    $arr[0] === '放送开始' ||
                    $arr[0] === '发售日' ||
                    $arr[0] === '上映年度' ||
                    $arr[0] === '发行日期' ||
                    $arr[0] === '开始'
                )
                {
                    $publish = $arr[1];
                }
                else if ($arr[0] === '别名')
                {
                    $alias[] = $arr[1];
                }
                else
                {
                    $alias[] = $name;
                }
            }

            $intro = trim($ql->find('#subject_summary')->text());
            $alias = array_unique($alias);

            $tags = $ql->find('.subject_tag_section')->eq(0)->find('span')->map(function ($item){
                return $item->text();
            })->all();

            return [
                'id' => $id,
                'name' => $name,
                'avatar' => "http:{$avatar}",
                'ep_total' => $count,
                'published_at' => $this->formatPublish($publish),
                'alias' => array_values($alias),
                'intro' => $intro,
                'tags' => $tags
            ];
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：get bangumi {$id} failed");
            return null;
        }
    }

    public function getNewsBangumi()
    {
        try
        {
            $url = 'http://bgm.tv/calendar';
            $ql = QueryList::get($url, [], $this::$opts);
            $data = $ql
                ->find('.coverList')
                ->map(function ($item)
                {
                    $list = $item
                        ->find('li')
                        ->map(function ($li)
                        {
                            $info = $li->find('.nav')->eq(0);
                            return last(explode('/', $info->href));
                        })
                        ->all();

                    return [$list];
                })
                ->all();

            $result = [];

            foreach ($data as $row)
            {
                $result = array_merge($result, $row);
            }

            return $result;
        }
        catch (\Exception $e)
        {
            Log::info("[--spider--]：get news bangumi failed");
            return [];
        }
    }

    private function formatPublish($publish)
    {
        if (!$publish)
        {
            return null;
        }
        try
        {
            if ($publish[0] === '0')
            {
                $publish = '20' . $publish;
            }
            if ($publish[0] === '1' && ($publish[3] === '-' || $publish[3] === '年'))
            {
                $publish = '19' . $publish;
            }
            $publish = str_replace('年', '-', $publish);
            $publish = str_replace('月', '-', $publish);
            $publish = str_replace('日', '', $publish);

            $ts = strtotime($publish);

            return Carbon::createFromTimestamp($ts);
        }
        catch (\Exception $e)
        {
            return null;
        }
    }
}
