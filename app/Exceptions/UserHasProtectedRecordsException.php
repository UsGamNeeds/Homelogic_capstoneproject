<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when something tries to hard-delete a user who has clinical, payroll or
 * audit records attached to them.
 *
 * The users table is referenced by 41 cascading foreign keys. A hard delete
 * therefore does not just remove the person: it removes the medication doses
 * they signed, the incidents they reported, the clock-ins they are paid for and
 * the t-logs they wrote. Those are legal records that must outlive employment,
 * so deletion is refused and the caller is pointed at deactivation instead.
 */
class UserHasProtectedRecordsException extends RuntimeException
{
    /**
     * @param  array<string, int>  $recordCounts  table name => number of rows found
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $recordCounts,
    ) {
        parent::__construct(sprintf(
            'User %d cannot be deleted because they have records that must be kept: %s. '
            .'Deactivate the user instead.',
            $userId,
            $this->describeRecords(),
        ));
    }

    public function describeRecords(): string
    {
        $parts = [];

        foreach ($this->recordCounts as $table => $count) {
            $parts[] = sprintf('%s (%d)', str_replace('_', ' ', $table), $count);
        }

        return implode(', ', $parts);
    }
}
