<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Programmer;

class TeamMatchingService
{
    public function calculateMatchScore(Programmer $programmer, Team $team): float
    {
        $score = 0;
        $maxScore = 100;

        if ($team->required_skills) {
            $matchPercentage = $this->calculateSkillsMatch($programmer, $team);
            $score += $matchPercentage * 40;
        }

        $experienceScore = $this->calculateExperienceMatch($programmer, $team);
        $score += $experienceScore;

        $teamCompatibility = $this->calculateTeamCompatibility($programmer, $team);
        $score += $teamCompatibility;

        $availabilityScore = $this->calculateAvailabilityScore($programmer);
        $score += $availabilityScore;

        return min($maxScore, round($score, 2));
    }

    private function calculateSkillsMatch(Programmer $programmer, Team $team): float
    {
        $programmerSkills = $programmer->programmerSkills()->pluck('name')->toArray();
        $requiredSkills = json_decode($team->required_skills, true) ?? [];

        if (empty($requiredSkills)) {
            return 1.0;
        }

        $matchingSkills = array_intersect($programmerSkills, $requiredSkills);
        return count($matchingSkills) / count($requiredSkills);
    }

    private function calculateExperienceMatch(Programmer $programmer, Team $team): float
    {
        $programmerLevel = $this->getExperienceLevel($programmer);
        $teamLevel = $team->experience_level;

        $levelScores = [
            'beginner' => 1,
            'intermediate' => 2,
            'advanced' => 3,
            'expert' => 4,
        ];

        $programmerScore = $levelScores[$programmerLevel] ?? 0;
        $teamScore = $levelScores[$teamLevel] ?? 0;

        if ($programmerScore >= $teamScore) {
            return 30;
        } elseif ($programmerScore >= $teamScore - 1) {
            return 20;
        }

        return 10;
    }

    private function getExperienceLevel(Programmer $programmer): string
    {
        $score = $programmer->total_score;

        return match(true) {
            $score >= 2000 => 'expert',
            $score >= 1000 => 'advanced',
            $score >= 500 => 'intermediate',
            default => 'beginner',
        };
    }

    private function calculateTeamCompatibility(Programmer $programmer, Team $team): float
    {
        $compatibility = 0;
        $teamMembers = $team->activeMembers;

        foreach ($teamMembers as $member) {
            $commonSkills = $this->countCommonSkills($programmer, $member->programmer);
            $compatibility += min(5, $commonSkills);
        }

        return min(20, $compatibility);
    }

    private function countCommonSkills(Programmer $programmer1, Programmer $programmer2): int
    {
        $skills1 = $programmer1->programmerSkills()->pluck('name')->toArray();
        $skills2 = $programmer2->programmerSkills()->pluck('name')->toArray();

        return count(array_intersect($skills1, $skills2));
    }

    private function calculateAvailabilityScore(Programmer $programmer): float
    {
        $currentTasks = $programmer->tasks()
            ->whereIn('status', ['in_progress', 'review'])
            ->count();

        return match(true) {
            $currentTasks < 3 => 10,
            $currentTasks < 5 => 7,
            $currentTasks < 7 => 4,
            default => 1,
        };
    }

    public function getMatchAnalysis(Programmer $programmer, Team $team): array
    {
        $score = $this->calculateMatchScore($programmer, $team);

        return [
            'score' => $score,
            'level' => $this->getMatchLevel($score),
            'recommendation' => $this->getRecommendation($score),
            'breakdown' => [
                'skills_match' => $this->calculateSkillsMatch($programmer, $team) * 40,
                'experience_match' => $this->calculateExperienceMatch($programmer, $team),
                'team_compatibility' => $this->calculateTeamCompatibility($programmer, $team),
                'availability' => $this->calculateAvailabilityScore($programmer),
            ],
        ];
    }

    private function getMatchLevel(float $score): string
    {
        return match(true) {
            $score >= 90 => 'excellent',
            $score >= 75 => 'very_good',
            $score >= 60 => 'good',
            $score >= 40 => 'fair',
            default => 'poor',
        };
    }

    private function getRecommendation(float $score): string
    {
        return match(true) {
            $score >= 80 => 'highly_recommended',
            $score >= 60 => 'recommended',
            $score >= 40 => 'consider',
            default => 'not_recommended',
        };
    }
}
