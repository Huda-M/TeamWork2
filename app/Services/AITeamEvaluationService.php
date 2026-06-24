<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AITeamEvaluationService
{
    protected string $baseUrl;
    
    public function __construct()
    {
        $this->baseUrl = config('services.ai_evaluation.url', 'https://arabicsoft-teamevaluationapi.hf.space');
    }

    /**
     * إرسال بيانات الفريق للـ AI API والحصول على التقييمات
     */
    public function evaluateTeam(array $data): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/predict/team-evaluation", $data);

            if ($response->failed()) {
                Log::error('AI Evaluation API failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('AI Evaluation API failed: ' . $response->body());
            }

            $result = $response->json();
            
            Log::info('AI Evaluation success', [
                'team_id' => $data['team']['id'] ?? null,
                'members_count' => count($result['evaluations'] ?? []),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('AI Evaluation error: ' . $e->getMessage());
            throw $e;
        }
    }
}
