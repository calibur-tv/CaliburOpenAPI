<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\Repository;
use App\Models\CMBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class CMController extends Controller
{
    protected $bannerCacheKey = 'homepage_banners';

    public function imageLooper()
    {
        $result = [
            'https://web.calibur.tv/banner/1.jpg',
            'https://web.calibur.tv/banner/2.png',
            'https://web.calibur.tv/banner/3.jpg',
            'https://web.calibur.tv/banner/4.jpg',
            'https://web.calibur.tv/banner/5.jpg',
            'https://web.calibur.tv/banner/6.jpg',
            'https://web.calibur.tv/banner/7.jpg',
            'https://web.calibur.tv/banner/8.jpg',
            'https://web.calibur.tv/banner/9.jpg',
        ];

        shuffle($result);

        return $this->resOK([
            'result' => $result,
            'total' => count($result),
            'no_more' => true
        ]);
    }

    public function showBanners(Request $request)
    {
        $repository = new Repository();
        $result = $repository->RedisArray($this->bannerCacheKey, function ()
        {
            return CMBanner
                ::where('online', 1)
                ->orderBy('id', 'DESC')
                ->select('id', 'type', 'image', 'title', 'link')
                ->get()
                ->toArray();
        });

        return $this->resOK($result);
    }

    public function reportBannerStat(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        if (!in_array($type, ['visit', 'click']))
        {
            return $this->resOK();
        }

        if ($type === 'visit')
        {
            CMBanner::where('id', $id)->increment('visit_count');
        }
        else
        {
            $ids = $id ? explode(',', $id) : [];
            CMBanner::whereIn('id', $ids)->increment('click_count');
        }

        return $this->resOK();
    }

    public function toggleBanner(Request $request)
    {
        $user = $request->user();
        if ($user->cant('change_homepage_banner'))
        {
            return $this->resErrRole();
        }

        $id = $request->get('id');
        $status = $request->get('status');

        CMBanner
            ::where('id', $id)
            ->update([
                'online' => $status
            ]);

        Redis::DEL($this->bannerCacheKey);

        return $this->resOK();
    }

    public function createBanner(Request $request)
    {
        $user = $request->user();
        if ($user->cant('change_homepage_banner'))
        {
            return $this->resErrRole();
        }

        $type = $request->get('type');
        $image = $request->get('image');
        $title = $request->get('title');
        $link = $request->get('link');

        $data = CMBanner::create([
            'type' => $type,
            'image' => $image,
            'title' => trim($title),
            'link' => trim($link)
        ]);

        return $this->resOK($data);
    }

    public function updateBanner(Request $request)
    {
        $user = $request->user();
        if ($user->cant('change_homepage_banner'))
        {
            return $this->resErrRole();
        }

        $id = $request->get('id');
        $type = $request->get('type');
        $image = $request->get('image');
        $title = $request->get('title');
        $link = $request->get('link');

        CMBanner
            ::where('id', $id)
            ->update([
                'type' => $type,
                'image' => $image,
                'title' => trim($title),
                'link' => trim($link)
            ]);

        Redis::DEL($this->bannerCacheKey);

        return $this->resOK();
    }

    public function allBanners(Request $request)
    {
        $list = CMBanner
            ::orderBy('id', 'DESC')
            ->get()
            ->toArray();

        return $this->resOK($list);
    }
}
