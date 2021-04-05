<?php


namespace App\Modules\Spider;


use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use QL\QueryList;
use GuzzleHttp\Client;

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

    public function __construct()
    {
        $ql = QueryList::getInstance();
        $ql->bind('guzzle', function ($url)
        {
            $client = new Client();
            $resp = $client->get($url);
            $body = $resp->getBody();
            $this->setHtml($body);
            return $this;
        });

        $this->query = $ql;
    }

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

    public function getBangumiCharacters($id)
    {
        try
        {
            $ids = $this
                ->query
                ->guzzle("http://bgm.tv/subject/{$id}/characters")
                ->find('.light_odd')
                ->filter(':has(h2 .tip)')
                ->map(function ($item)
                {
                    $tips = $item->find('.tip_j')->count();
                    $talk = $item->find('.rr')->find('.na')->text();
                    $cv = $item->find('.actorBadge')->count();
                    if (!$cv)
                    {
                        return 0;
                    }
                    $talk = str_replace('+', '', $talk);
                    $talk = str_replace('(', '', $talk);
                    $talk = str_replace(')', '', $talk);
                    $talk = $talk ? intval($talk) : 0;
                    if ($tips < 2 && $talk < 10)
                    {
                        return 0;
                    }
                    return last(explode('/', $item->find('a.avatar')->eq(0)->href));
                })
                ->all();

            $ids = array_filter($ids, function ($item)
            {
                return !!$item;
            });

//            $ids = array_slice($ids, 0, 50);

            $result = [];
            foreach ($ids as $id)
            {
                $character = $this->getCharacter($id);
                if ($character)
                {
                    $result[] = $character;
                }
            }

            return $result;
        }
        catch (\Exception $e)
        {
            Log::info('get bangumi characters', [
                'message' => $e
            ]);
            return [];
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

    public function getRankBangumiIds($page)
    {
        try
        {
            $ids = $this
                ->query
                ->guzzle("http://bgm.tv/anime/browser?sort=rank&page={$page}")
                ->find('#browserItemList')
                ->children()
                ->map(function ($item)
                {
                    return last(explode('/', $item->find('.subjectCover')->eq(0)->href));
                })
                ->all();

            return $ids;
        }
        catch (\Exception $e)
        {
            Log::info('get rank bangumi ids', [
                'message' => $e
            ]);
            return [];
        }
    }

    public function getCharacter($id)
    {
        try
        {
            $ql = $this->query->guzzle("http://bgm.tv/character/{$id}");
            $avatar = $ql->find('.infobox')->eq(0)->find('img')->eq(0)->src;
            $meta = explode(PHP_EOL, $ql->find('#infobox')->text());
            $extra = [];
            foreach ($meta as $item)
            {
                $arr = explode(': ', $item);
                if (isset($extra[$arr[0]]))
                {
                    if (gettype($extra[$arr[0]]) === 'string')
                    {
                        $extra[$arr[0]] = [$extra[$arr[0]]];
                    }
                    $extra[$arr[0]][] = $arr[1];
                }
                else
                {
                    $extra[$arr[0]] = $arr[1];
                }
            }

            $detail = trim($ql->find('.detail')->text());
            $extra['alias'] = [];
            $validate = false;
            if (isset($extra['简体中文名']) && $extra['简体中文名'] === '广播')
            {
                return null;
            }

            if (isset($extra['简体中文名']))
            {
                $validate = true;
                if (gettype($extra['简体中文名']) === 'string')
                {
                    $extra['alias'][] = $extra['简体中文名'];
                }
                else
                {
                    $extra['alias'] = array_merge($extra['alias'], $extra['简体中文名']);
                }
            }

            if (isset($extra['别名']))
            {
                $validate = true;
                if (gettype($extra['别名']) === 'string')
                {
                    $extra['alias'][] = $extra['别名'];
                }
                else
                {
                    $extra['alias'] = array_merge($extra['alias'], $extra['别名']);
                }
            }

            if (!$validate)
            {
                return null;
            }

            $extra['alias'] = array_unique($extra['alias']);
            $alias = array_values($extra['alias']);
            $alias = count($alias) > 1 ? implode('|', $alias) : $alias[0];

            if (!$detail)
            {
                return null;
            }

            return [
                'bgm_id' => $id,
                'avatar' => "http:{$avatar}",
                'name' => $extra['alias'][0],
                'intro' => $detail,
                'alias' => $alias
            ];
        }
        catch (\Exception $e)
        {
            Log::info('get character', [
                'message' => $e
            ]);
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
