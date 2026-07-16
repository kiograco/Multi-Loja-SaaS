<?php

declare(strict_types=1);

namespace OrderHub\Infrastructure\Persistence;

use OrderHub\Application\Queue\ProcessedJobLedger;
use PDOException;

final class PostgresProcessedJobLedger implements ProcessedJobLedger
{
    public function __construct(private readonly Database $database)
    {
    }

    public function isProcessed(string $jobId): bool
    {
        $stmt = $this->database->pdo()->prepare('SELECT 1 FROM processed_jobs WHERE job_id = :id');
        $stmt->execute(['id' => $jobId]);

        return $stmt->fetchColumn() !== false;
    }

    public function markProcessed(string $jobId, string $jobType): bool
    {
        try {
            $stmt = $this->database->pdo()->prepare(
                'INSERT INTO processed_jobs (job_id, job_type) VALUES (:id, :type)
                 ON CONFLICT (job_id) DO NOTHING'
            );
            $stmt->execute(['id' => $jobId, 'type' => $jobType]);

            return $stmt->rowCount() > 0;
        } catch (PDOException) {
            return false;
        }
    }
}
