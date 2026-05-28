<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuggestedTeamsRequest;
use App\Models\AiTeam;
use App\Models\Programmer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class TeamMatchingController extends Controller
{
     public function joinTeam(){
        $user = auth()->user();
        $programmer = Programmer::query()->where('user_id', $user->id)->firstOrFail();
        $teams = Team::query()->where('status','forming')->get();
        $payload = [
            'user_profile'=>[
                'user_id'=>$user->id,
                'full_name'=>$user->full_name,
                'skills'=>$programmer->skills,
                'experience'=>$programmer->experience_level,
            ],
            'teams'=>$teams->map(function($team){
                return [
                    'id'=>$team->id,
                    'name'=>$team->name,
                    'status'=>$team->status,
                    'req_skills'=>$team->required_skills,
                    'req_exp_level'=>$team->experience_level,
                    'composition'=>$team->teamMembers->map(function($member){
                        return [
                            'user_id'=>$member->programmer->user_id,
                            'full_name'=>$member->programmer->user->full_name,
                            'experience'=>$member->programmer->experience_level,
                        ];
                    })
                ];
            })
        ];

        Http::post('https://arabicsoft-ai-team-matcher.hf.space/api/match-teams', $payload);

        return response()->json([
            'success' => true,
            'data' => $payload
        ]);
    }

    public function suggestTeam(SuggestedTeamsRequest $request){
        $data = $request->validated();
        $user = auth()->user();
        $programmer = $user->programmer;

        if(!$programmer || $programmer->user_id != $data['user_id']){
            return response()->json([
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        }

        foreach($data['team_ids'] as $teamId){
            AiTeam::firstOrCreate([
                'user_id' => $programmer->user_id,
                'team_id' => $teamId
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Suggested teams saved successfully.'
        ]);
    }

    public function getSuggestedTeams()
    {
        $programmer = auth()->user()->programmer;
        if (!$programmer) {
            return response()->json([
                'message' => 'Programmer profile not found.',
            ], 404);
        }

        $aiTeams = AiTeam::where('user_id', $programmer->user_id)
            ->whereHas('team', function($query) {
                $query->where('status', 'forming');
            })
            ->with(['team.teamMembers.programmer.user'])
            ->get();

        $teams = $aiTeams->map(function($aiTeam) {
            $team = $aiTeam->team;
            if (!$team) {
                return null;
            }
            return [
                'id' => $team->id,
                'name' => $team->name,
                'status' => $team->status,
                'req_skills' => $team->required_skills,
                'req_exp_level' => $team->experience_level,
                'composition' => $team->teamMembers->map(function($member) {
                    return [
                        'user_id' => $member->programmer->user_id,
                        'full_name' => $member->programmer->user->full_name,
                        'experience' => $member->programmer->experience_level,
                    ];
                })
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'teams' => $teams
        ]);
    }
}
