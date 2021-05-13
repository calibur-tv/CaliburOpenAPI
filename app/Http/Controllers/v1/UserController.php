<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Transformers\User\UserAuthResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        $form = $request->only(['nickname', 'avatar', 'meta']);

        $user->update($form);

        return $this->resOK();
    }
}
