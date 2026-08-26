<?php

namespace App\Services;

use App\Models\TeacherEffectivenessScore;
use App\Models\TeacherRatingComponent;
use App\Models\User;

/**
 * Basic TEI (spec section AB): weighted average of per-dimension scores
 * from teacher_feedback.dimension_scores, weights from the admin-editable
 * teacher_rating_components table. Same confidence heuristic as SQI.
 *
 * Deliberately NOT included yet (see ROADMAP.md): value-add / student
 * learning improvement component, classroom observation, professional
 * development — spec section AC/AD call for these but they need academic
 * performance data this build doesn't have. Only the feedback-based
 * component is calculated; component_breakdown documents what's missing.
 */
class TeacherEffectivenessIndexService
{
    public function recalculate(User $teacher): TeacherEffectivenessScore
    {
        $components = TeacherRatingComponent::where('is_active', true)->get();
        $feedback = $teacher->receivedTeacherFeedback()->orderByDesc('submitted_at')->limit(500)->get();

        $totalWeight = $components->sum('weight');
        $breakdown = [];
        $weightedSum = 0.0;

        foreach ($components as $component) {
            $scores = $feedback
                ->pluck('dimension_scores.'.$component->key)
                ->filter(fn ($v) => $v !== null);

            $average = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;
            $breakdown[$component->key] = [
                'label' => $component->label,
                'weight' => (float) $component->weight,
                'average' => $average,
                'response_count' => $scores->count(),
            ];

            if ($average !== null && $totalWeight > 0) {
                $weightedSum += ($average / 5) * 100 * ((float) $component->weight / $totalWeight);
            }
        }

        $responseCount = $feedback->count();

        return TeacherEffectivenessScore::create([
            'teacher_user_id' => $teacher->id,
            'score' => round($weightedSum, 2),
            'confidence' => $this->confidenceFor($responseCount),
            'response_count' => $responseCount,
            'component_breakdown' => $breakdown,
            'calculated_at' => now(),
        ]);
    }

    private function confidenceFor(int $responseCount): string
    {
        return match (true) {
            $responseCount >= 30 => 'high',
            $responseCount >= 10 => 'medium',
            $responseCount >= 1 => 'low',
            default => 'insufficient_data',
        };
    }
}
