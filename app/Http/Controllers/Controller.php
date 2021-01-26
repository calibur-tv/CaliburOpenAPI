<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected function resOK($data = '')
    {
        return response([
            'code' => 0,
            'data' => $data
        ]);
    }

    protected function resErrBad($message = null)
    {
        return response([
            'code' => 400,
            'message' => $message ?: '请求参数错误'
        ]);
    }

    protected function resNoContent()
    {
        return response('', 204);
    }

    protected function resErrNotFound($message = null)
    {
        return response([
            'code' => 404,
            'message' => $message ?: '不存在的资源'
        ]);
    }

    protected function resErrLocked($message = null)
    {
        return response([
            'code' => 423,
            'message' => $message ?: '内容正在审核中'
        ]);
    }

    protected function resErrLogin($message = null)
    {
        return response([
            'code' => 401,
            'message' => $message ?: '继续操作前请先登录'
        ]);
    }

    protected function resErrThrottle($message)
    {
        return response([
            'code' => 429,
            'message' => $message ?: '请勿灌水'
        ]);
    }

    protected function resErrParams($validator)
    {
        return response([
            'code' => 400,
            'message' => $validator->errors()->all()[0]
        ]);
    }

    protected function resErrRole($message = null)
    {
        return response([
            'code' => 403,
            'message' => $message ?: '没有权限进行该操作'
        ]);
    }

    protected function resErrServiceUnavailable($message = null)
    {
        return response([
            'code' => 503,
            'message' => $message ?: '服务升级暂不可用'
        ]);
    }
}
