<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request)
    {
        $slug = $request->get('slug');

        $userRepository = new UserRepository();
        $user = $userRepository->item($slug);

        return $this->resOK($user);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $form = $request->only(['nickname', 'avatar', 'meta', 'aboutus']);

        $user->updateUser($form);

        return $this->resOK();
    }

    public function signTogether(Request $request)
    {
        $user = $request->user();
        $targetSlug = $request->get('target_slug');
        $time = $request->get('time');
        $token = $request->get('token');

        if ($user->love_type != 0)
        {
            return $this->resErrBad('你目前不是单身');
        }

        if ($user->slug === $targetSlug)
        {
            return $this->resErrBad('不能和自己牵手');
        }

        if (time() * 1000 - $time > 86400000)
        {
            return $this->resErrBad('该邀请已超时');
        }

        $target = User
            ::where('id', slug2id($targetSlug))
            ->first();

        if (!$target)
        {
            return $this->resErrBad('找不到对方');
        }

        if ($target->love_type != 0)
        {
            return $this->resErrBad('TA现在不是单身');
        }

        if (md5($target->id . $user->slug . $target->slug . $time) !== $token)
        {
            return $this->resErrBad('非法的请求');
        }

        $user->updateUser([
            'love_type' => 1,
            'love_user' => $target->id
        ]);

        $target->updateUser([
            'love_type' => 1,
            'love_user' => $user->id
        ]);

        return $this->resOK();
    }

    public function signSingle(Request $request)
    {
        $user = $request->user();
        $targetSlug = $request->get('target_slug');
        $time = $request->get('time');
        $token = $request->get('token');

        if ($user->love_type == 0)
        {
            return $this->resErrBad('你目前是单身');
        }

        if ($user->slug === $targetSlug)
        {
            return $this->resErrBad('不能和自己牵手');
        }

        if (time() * 1000 - $time > 86400000)
        {
            return $this->resErrBad('该邀请已超时');
        }

        $target = User
            ::where('id', slug2id($targetSlug))
            ->first();

        if (!$target)
        {
            return $this->resErrBad('找不到对方');
        }

        if ($target->love_type == 0)
        {
            return $this->resErrBad('TA现在是单身');
        }

        if (md5($target->id . $user->slug . $target->slug . $time) !== $token)
        {
            return $this->resErrBad('非法的请求');
        }

        $user->updateUser([
            'love_type' => 0,
            'love_user' => ''
        ]);

        $target->updateUser([
            'love_type' => 0,
            'love_user' => ''
        ]);

        return $this->resOK();
    }

    public function aboutFlow(Request $request)
    {
        $seenIds = $request->get('seen_ids') ? explode(',', $request->get('seen_ids')) : [];

        $users = User
            ::whereNotIn('id', $seenIds)
            ->whereNotNull('aboutus')
            ->whereNotNull('meta')
            ->where('love_type', 0)
            ->orderBy('updated_at', 'DESC')
            ->select('id', 'slug', 'avatar', 'nickname', 'aboutus', 'meta', 'realname')
            ->take(20)
            ->get();

        return $this->resOK([
            'result' => $users
        ]);
    }
}
