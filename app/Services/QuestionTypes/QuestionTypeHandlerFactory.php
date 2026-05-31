<?php

namespace App\Services\QuestionTypes;

use App\Models\QuizQuestion;
use Exception;

/**
 * Factory for creating question type handlers
 */
class QuestionTypeHandlerFactory
{
    private static array $handlers = [
        'multiple_choice'         => MultipleChoiceHandler::class,
        'true_false'              => TrueFalseHandler::class,
        'short_answer'            => ShortAnswerHandler::class,
        'numerical'               => NumericalHandler::class,
        'matching'                => MatchingHandler::class,
        'essay'                   => EssayHandler::class,
        'drag_drop'               => DragDropHandler::class,
        'drag_drop_text'          => DragDropHandler::class,
        'drag_drop_markers'       => DragDropHandler::class,
        // 'calculated'           => CalculatedHandler::class,
        // 'calculated_simple'    => CalculatedSimpleHandler::class,
        // 'calculated_multichoice' => CalculatedMultiChoiceHandler::class,
    ];

    /**
     * Create handler for given question type
     *
     * @param QuizQuestion $question
     * @return QuestionTypeHandler
     * @throws Exception
     */
    public static function create(QuizQuestion $question): QuestionTypeHandler
    {
        $type = $question->type;

        if (!isset(self::$handlers[$type])) {
            throw new Exception("Question type '$type' is not supported yet.");
        }

        $handlerClass = self::$handlers[$type];
        return new $handlerClass($question);
    }

    /**
     * Get all supported question types
     */
    public static function getSupportedTypes(): array
    {
        return array_keys(self::$handlers);
    }

    /**
     * Check if a type requires manual grading
     */
    public static function requiresManualGrading(string $type): bool
    {
        // Essay always requires manual grading
        return $type === 'essay';
    }

    /**
     * Register a custom handler
     */
    public static function register(string $type, string $handlerClass): void
    {
        if (!is_subclass_of($handlerClass, QuestionTypeHandler::class)) {
            throw new Exception("Handler must extend QuestionTypeHandler");
        }

        self::$handlers[$type] = $handlerClass;
    }
}
