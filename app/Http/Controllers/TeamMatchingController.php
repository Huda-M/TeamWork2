<?php

namespace App\Http\Controllers;

use App\Models\AiTeam;
use App\Models\Programmer;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

class TeamMatchingController extends Controller
{
    public function matchTeams()
    {
        $user = auth()->user();

        $programmer = Programmer::where('user_id', $user->id)->firstOrFail();

        $teams = Team::where('status', 'forming')
            ->with(['teamMembers.programmer.user'])
            ->get();

        $payload = [
            'user_profile' => [
                'user_id' => $user->id,
                'full_name' => $user->full_name,
                'skills' => is_string($programmer->skills)
                    ? json_decode($programmer->skills, true)
                    : $programmer->skills ?? [],
                'experience' => (float) $programmer->current_level,
            ],

            'teams' => $teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'status' => $team->status,
                'req_skills' => $team->required_skills,
                'req_exp_level' => $team->experience_level,
                'composition' => $team->teamMembers->map(fn ($member) => [
                    'user_id' => $member->programmer?->user_id,
                    'full_name' => $member->programmer?->user?->full_name,
                    'experience' => $member->programmer?->experience_level,
                ])->values(),
            ])->values(),
        ];

        $response = Http::timeout(60)
            ->post('https://arabicsoft-ai-team-matcher.hf.space/api/match-teams', $payload);

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'AI service failed.',
                'error' => $response->json(),
            ], 500);
        }

        $aiData = $response->json('data');

        if (! isset($aiData['team_ids'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid AI response.',
                'ai_response' => $response->json(),
            ], 422);
        }

        AiTeam::where('user_id', $programmer->id)->delete();

        foreach ($aiData['team_ids'] as $teamId) {
            AiTeam::create([
                'user_id' => $programmer->id,
                'team_id' => $teamId,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Teams matched and saved successfully.',
            'data' => $response->json('data'),
        ]);
    }
}
