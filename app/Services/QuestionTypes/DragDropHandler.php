<?php

namespace App\Services\QuestionTypes;

/**
 * Drag & Drop Question Handler
 * Supports multiple variants:
 * - General drag_drop: Drag items into zones
 * - drag_drop_text: Drag words into blanks in text
 * - drag_drop_markers: Drag items to image hotspots/markers
 */
class DragDropHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'              => 'required|in:drag_drop,drag_drop_text,drag_drop_markers',
            'question_text'     => 'required|string|min:3',
            'drag_drop_config'  => 'required|array',
            'background_image'  => 'nullable|url',
            'default_mark'      => 'numeric|min:0',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Validate and normalize drag_drop configuration
        if (isset($data['drag_drop_config'])) {
            $data['drag_drop_config'] = $this->validateDragDropConfig($data['drag_drop_config']);
        }

        return $data;
    }

    public function gradeResponse(array $response): array
    {
        $studentDrops = $response['response_data'] ?? $response['response_text'] ?? null;

        if (empty($studentDrops)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'You must place all items in their designated zones.',
                'auto_graded'   => false, // Requires review
            ];
        }

        // Parse student's drop placements
        $drops = is_string($studentDrops) ? json_decode($studentDrops, true) : $studentDrops;
        if (!is_array($drops)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'Invalid response format.',
                'auto_graded'   => false,
            ];
        }

        $config = $this->question->drag_drop_config ?? [];
        $correctPlacements = $config['correct_placements'] ?? [];

        if (empty($correctPlacements)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'Question configuration is incomplete.',
                'auto_graded'   => false,
            ];
        }

        $correctCount = 0;
        $totalPlacements = count($correctPlacements);

        // Check each placement
        foreach ($correctPlacements as $placement) {
            $draggableId = $placement['draggable_id'] ?? null;
            $zoneId = $placement['zone_id'] ?? null;

            if ($drops[$draggableId] === $zoneId) {
                $correctCount++;
            }
        }

        $correctPercentage = $correctCount / $totalPlacements;
        $marks = $this->question->default_mark * $correctPercentage;

        if ($correctCount === $totalPlacements) {
            return [
                'marks_awarded' => $this->question->default_mark,
                'is_correct'    => true,
                'feedback'      => "Perfect! All items are placed in the correct zones.",
                'auto_graded'   => true,
            ];
        }

        return [
            'marks_awarded' => round($marks, 2),
            'is_correct'    => false,
            'feedback'      => "You placed $correctCount out of $totalPlacements items correctly.",
            'auto_graded'   => true,
        ];
    }

    public function isValidResponse(array $response): bool
    {
        $drops = $response['response_data'] ?? $response['response_text'] ?? null;
        return !empty($drops);
    }

    public function getFeedback(array $response): ?string
    {
        $grading = $this->gradeResponse($response);
        return $grading['feedback'] ?? null;
    }

    /**
     * Validate drag_drop configuration structure
     *
     * Expected format:
     * {
     *   "type": "image|text|markers",
     *   "zones": [
     *     {"id": "zone1", "label": "Zone 1", "x": 100, "y": 100, "width": 50, "height": 50}
     *   ],
     *   "draggables": [
     *     {"id": "drag1", "label": "Item 1", "image_url": "..."}
     *   ],
     *   "correct_placements": [
     *     {"draggable_id": "drag1", "zone_id": "zone1"}
     *   ]
     * }
     */
    private function validateDragDropConfig(array $config): array
    {
        // Validate required fields
        if (empty($config['type']) || !in_array($config['type'], ['image', 'text', 'markers'])) {
            throw new \InvalidArgumentException('Invalid drag_drop type. Must be: image, text, or markers.');
        }

        if (empty($config['zones']) || !is_array($config['zones'])) {
            throw new \InvalidArgumentException('Drag & drop must have at least one zone.');
        }

        if (empty($config['draggables']) || !is_array($config['draggables'])) {
            throw new \InvalidArgumentException('Drag & drop must have at least one draggable item.');
        }

        if (empty($config['correct_placements']) || !is_array($config['correct_placements'])) {
            throw new \InvalidArgumentException('Drag & drop must have placement definitions.');
        }

        // Validate zones
        foreach ($config['zones'] as $zone) {
            if (empty($zone['id'])) {
                throw new \InvalidArgumentException('Each zone must have an id.');
            }
            if ($config['type'] === 'markers' && (!isset($zone['x']) || !isset($zone['y']))) {
                throw new \InvalidArgumentException('Marker zones must have x and y coordinates.');
            }
        }

        // Validate draggables
        foreach ($config['draggables'] as $draggable) {
            if (empty($draggable['id'])) {
                throw new \InvalidArgumentException('Each draggable item must have an id.');
            }
        }

        // Validate placements reference valid items
        $zoneIds = array_column($config['zones'], 'id');
        $draggableIds = array_column($config['draggables'], 'id');

        foreach ($config['correct_placements'] as $placement) {
            if (!in_array($placement['draggable_id'] ?? null, $draggableIds)) {
                throw new \InvalidArgumentException('Placement references invalid draggable item.');
            }
            if (!in_array($placement['zone_id'] ?? null, $zoneIds)) {
                throw new \InvalidArgumentException('Placement references invalid zone.');
            }
        }

        return $config;
    }
}
