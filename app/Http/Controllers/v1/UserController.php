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

        $user->update($form);

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
