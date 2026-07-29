<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use RuntimeException;

/**
 * O modelo recusou-se a processar o conteúdo. Repetir a chamada não resolve.
 */
class OpenAiRefusalException extends RuntimeException
{
}
