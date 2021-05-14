<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();
        $form = $request->only(['nickname', 'avatar', 'meta', 'aboutus']);

        $user->update($form);

        return $this->resOK();
    }
}
