<?php

namespace App\Services\Sieg\Exceptions;

/** Esperar (limite de taxa/backoff) ultrapassaria o prazo da execução. */
class SiegDeadlineException extends SiegException {}
