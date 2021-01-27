<?php


namespace App\Http\Transformers\Message;


use App\Http\Modules\RichContentService;
use App\Http\Transformers\User\UserItemResource;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageItemResource extends JsonResource
{
    public function toArray($request)
    {
        $richContentService = new RichContentService();

        return [
            'id' => $this->id,
            'sender_slug' => $this->sender_slug,
            'getter_slug' => $this->getter_slug,
            'content' => $richContentService->parseRichContent($this->content->text),
            'channel' => $this->when(isset($this->channel), $this->channel),
            'sender' => $this->when(isset($this->sender), new UserItemResource($this->sender)),
            'created_at' => $this->created_at
        ];
    }
}
