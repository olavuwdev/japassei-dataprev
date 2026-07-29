<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use RuntimeException;

/**
 * Lançada quando o serviço FSRS não pode calcular o agendamento. A avaliação
 * não é registrada — o PRD proíbe qualquer cálculo aproximado como alternativa.
 */
class FsrsUnavailableException extends RuntimeException
{
}
