<?php


namespace App\Http\Transformers\LiveRoom;


use App\Http\Repositories\UserRepository;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveRoomItemResource extends JsonResource
{
    public function toArray($request)
    {
        $userRepository = new UserRepository();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'desc' => $this->desc,
            'visit_state' => $this->visit_state,
            'author' => $userRepository->item($this->author_id),
            'readers' => $this->readers,
            'content' => $this->content,
            'created_at' => $this->created_at
        ];
    }
}
