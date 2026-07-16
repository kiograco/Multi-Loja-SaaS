<?php

declare(strict_types=1);

namespace OrderHub\Application\Bus;

/**
 * Handles exactly one command type. Handlers return either nothing or a small
 * result value (e.g. a generated id) — never a read model.
 */
interface CommandHandler
{
}
