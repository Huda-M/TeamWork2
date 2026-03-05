<?php

namespace App\Services;

use App\Models\Programmer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AITeamRecommendationService
{
    protected $aiApiUrl = 'http://localhost:5000/api/recommend'; // عنوان الـ API


    public function getRecommendations(Programmer $programmer, $limit = 10)
    {
        try {
            $programmerData = [
                'programmer_id' => $programmer->id,
                'skills' => $programmer->programmerSkills()->pluck('skills.name')->toArray(),
                'experience_level' => $programmer->experience_level,
                'total_score' => $programmer->total_score,
                'specialty' => $programmer->specialty,
                'limit' => $limit
            ];

            $response = Http::timeout(30)->post($this->aiApiUrl, $programmerData);

            if ($response->successful()) {
                $recommendations = $response->json();

                return $this->formatRecommendations($recommendations);
            }

            Log::warning('AI API failed, using fallback', [
                'status' => $response->status()
            ]);

            return $this->getFallbackRecommendations($programmer, $limit);

        } catch (\Exception $e) {
            Log::error('Error calling AI API: ' . $e->getMessage());
            return $this->getFallbackRecommendations($programmer, $limit);
        }
    }

    private function formatRecommendations($apiResponse)
    {
        $formatted = [];

        foreach ($apiResponse['recommendations'] ?? [] as $rec) {
            $team = \App\Models\Team::with(['project', 'activeMembers'])
                ->find($rec['team_id']);

            if ($team) {
                $formatted[] = [
                    'team' => $team,
                    'match_score' => $rec['match_score'] ?? 0.5,
                    'match_percentage' => ($rec['match_score'] ?? 0.5) * 100,
                    'matching_skills' => $rec['matching_skills'] ?? [],
                    'reason' => $rec['reason'] ?? 'Recommended by AI',
                ];
            }
        }

        return $formatted;
    }

    private function getFallbackRecommendations($programmer, $limit)
    {
        $teams = \App\Models\Team::active()
            ->hasVacancies()
            ->with(['project'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return $teams->map(function($team) {
            return [
                'team' => $team,
                'match_score' => 0.5,
                'match_percentage' => 50,
                'matching_skills' => [],
                'reason' => 'Random recommendation (AI unavailable)',
            ];
        })->toArray();
    }
}
