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
        $data = is_array($this->data) ? $this->data : (json_decode($this->data ?: '', true) ?: []);
        
        $details = $data;
        unset($details['message']);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'message' => $data['message'] ?? 'New notification',
            'data' => $data['data'] ?? $details,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'is_read' => !is_null($this->read_at),
        ];
    }
}
