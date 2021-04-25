<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2019-04-15
 * Time: 08:53
 */

namespace App\Http\Transformers\Desk;

use Illuminate\Http\Resources\Json\JsonResource;

class DeskItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'meta' => $this->meta,
            'link' => patchImage('user-' . $this->user_id . '/' . $this->folder_id . '-' . $this->name),
            'created_at' => $this->created_at
        ];
    }
}
