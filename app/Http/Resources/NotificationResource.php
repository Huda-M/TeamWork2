<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'team_id' => $this->data['team_id'] ?? null,
            'team_name' => $this->data['team_name'] ?? null,
            'message' => $this->data['message'] ?? $this->data['message'] ?? 'New notification',
            'action_url' => $this->data['action_url'] ?? null,
            'action_text' => $this->data['action_text'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'is_read' => !is_null($this->read_at),
        ];
    }
}
