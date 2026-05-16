<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammerResource extends JsonResource
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
            'name' => $this->user->full_name,
            'skills' => $this->skills->pluck('name'),
            'tracks' => $this->tracks->pluck('name'),
            'image' => $this->cover_image,
            'bio' => $this->bio,
            'total_score' => $this->total_score,
        ];
    }
}
