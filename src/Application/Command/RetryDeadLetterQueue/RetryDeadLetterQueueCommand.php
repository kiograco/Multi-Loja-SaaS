<?php

declare(strict_types=1);

namespace OrderHub\Application\Command\RetryDeadLetterQueue;

use OrderHub\Application\Bus\Command;

/**
 * No parameters: the dead-letter queue is global to the instance, not
 * tenant-scoped (see Seção 19 — exposing this on the Web is a deliberate,
 * explicitly-requested exception to the "ops tools stay on the CLI" rule).
 */
final readonly class RetryDeadLetterQueueCommand implements Command
{
}
