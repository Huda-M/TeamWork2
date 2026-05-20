<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammerDetailsResource extends JsonResource
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
            'track' => $this->track,
            'experience_level' => $this->experience_level,
            'bio' => $this->bio,
            'cover_image' => $this->cover_image,
            'avatar_url' => $this->avatar_url,
            'stars' => $this->stars,
            'skills' => $this->skills,
            'projects' => $this->teams
                ->map->project
                ->filter()
                ->unique('id')
                ->values()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'title' => $project->title,
                        'description' => $project->description,
                        'project_status' => $project->status,
                    ];
                }),
        ];
    }
}
