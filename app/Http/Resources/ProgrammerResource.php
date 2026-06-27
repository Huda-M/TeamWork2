<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'skills' => $this->skills,
            'programmer_skills' => collect($this->getRelation('skills'))->pluck('name'),
            'experience_level' => $this->experience_level,
            'tracks' => $this->track,
            'image' => $this->cover_image,
            'bio' => $this->bio,
            'stars' => $this->stars,
            'avatar_url' => $this->avatar_url
                ? Storage::disk('public')->url($this->avatar_url)
                : null,
        ];
    }
}
