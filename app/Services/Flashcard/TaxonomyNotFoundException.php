<?php

declare(strict_types=1);

namespace App\Services\Flashcard;

use RuntimeException;

/**
 * Disciplina, categoria ou assunto não encontrado e sem autorização para criar.
 * Resulta em HTTP 404 na API externa.
 */
class TaxonomyNotFoundException extends RuntimeException
{
}
