<?php


namespace App\Http\Controllers\v1;


use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\Folder;
use Illuminate\Http\Request;

class DeskController extends Controller
{
    public function token(Request $request)
    {
        $user = $request->user();
        $id = config('app.aliyun.oss.id');          // 请填写您的AccessKeyId。
        $key = config('app.aliyun.oss.secret');     // 请填写您的AccessKeySecret。
        // $host的格式为 bucketname.endpoint，请替换为您的真实信息。
        $host = 'https://calibur-arthur.oss-cn-shanghai.aliyuncs.com';
        // $callbackUrl为上传回调服务器的URL，请将下面的IP和Port配置为您自己的真实URL信息。
        $callbackUrl = 'https://fc.calibur.tv/callback/oss/upload';
        $dir = 'user-' . $user->id . '/';          // 用户上传文件时指定的前缀。
        $callback_param = array(
            'callbackUrl' => $callbackUrl,
            'callbackBody' => 'filename=${object}&hash=${etag}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}&format=${imageInfo.format}',
            'callbackBodyType' => "application/x-www-form-urlencoded"
        );
        $callback_string = json_encode($callback_param);

        $base64_callback_body = base64_encode($callback_string);
        $now = time();
        $expire = 86400;  //设置该policy超时时间是1天. 即这个policy过了这个有效时间，将不能访问。
        $end = $now + $expire;
        $expiration = $this->gmt_iso8601($end);


        //最大文件大小.用户可以自己设置
        $condition = array(
            0 => 'content-length-range',
            1 => 0,
            2 => 1048576000
        );
        $conditions[] = $condition;

        // 表示用户上传的数据，必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
        $start = array(
            0 => 'starts-with',
            1 => '$key',
            2 => $dir
        );
        $conditions[] = $start;

        $arr = array(
            'expiration' => $expiration,
            'conditions' => $conditions
        );

        $policy = json_encode($arr);
        $base64_policy = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));

        $response = array();
        $response['accessid'] = $id;
        $response['host'] = $host;
        $response['policy'] = $base64_policy;
        $response['signature'] = $signature;
        $response['expire'] = $end;
        $response['callback'] = $base64_callback_body;
        $response['dir'] = $dir;

        return $this->resOK($response);
    }

    public function folders(Request $request)
    {
        $user = $request->user();
        $folders = Folder
            ::where('user_id', $user->id)
            ->get()
            ->toArray();

        return $this->resOK($folders);
    }

    public function files(Request $request)
    {
        $user = $request->user();
        $folder_id = $request->get('folder_id') ?? 0;

        $files = Desk
            ::where('user_id', $user->id)
            ->where('folder_id', $folder_id)
            ->get()
            ->toArray();

        return $this->resOK([
            'total' => count($files),
            'result' => $files,
            'no_more' => true
        ]);
    }

    public function createFolder(Request $request)
    {
        $user = $request->user();
        $name = $request->get('name');

        $folder = Folder::create([
            'name' => $name,
            'user_id' => $user->id
        ]);

        return $this->resOK($folder);
    }

    public function deleteFolder(Request $request)
    {
        $user = $request->user();
        $folderId = $request->get('folder_id');

        $folder = Folder
            ::where('user_id', $user->id)
            ->where('id', $folderId)
            ->first();

        if (!$folder)
        {
            return $this->resOK();
        }

        $folder->delete();

        Desk
            ::where('user_id', $user->id)
            ->where('folder_id', $folder->id)
            ->delete();

        return $this->resOK();
    }

    public function moveFile(Request $request)
    {
        $user = $request->user();
        $fileId = $request->get('file_id');
        $folderId = $request->get('folder_id');
        $name = $request->get('name');

        $file = Desk
            ::where('id', $fileId)
            ->first();

        if (!$file || $file->user_id !== $user->id)
        {
            return $this->resErrBad();
        }

        $file->update([
            'name' => $name ?? $file->name,
            'folder_id' => $folderId ?? $file->folder_id
        ]);

        return $this->resOK();
    }

    public function deleteFile(Request $request)
    {
        $user = $request->user();
        $fileId = $request->get('file_id');

        $file = Desk
            ::where('id', $fileId)
            ->first();

        if (!$file || $file->user_id !== $user->id)
        {
            return $this->resErrBad();
        }

        $file->delete();

        return $this->resOK();
    }

    private function gmt_iso8601($time)
    {
        $dtStr = date("c", $time);
        $mydatetime = new \DateTime($dtStr);
        $expiration = $mydatetime->format(\DateTime::ISO8601);
        $pos = strpos($expiration, '+');
        $expiration = substr($expiration, 0, $pos);

        return $expiration . "Z";
    }
}
