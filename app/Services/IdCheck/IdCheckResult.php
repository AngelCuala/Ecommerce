<?php

namespace App\Services\IdCheck;

/** Outcome of checking one uploaded ID image. */
final class IdCheckResult
{
    public const PASSED    = 'passed';     // looks like a government-issued ID
    public const REJECTED  = 'rejected';   // clearly not an ID (or unreadable)
    public const UNCHECKED = 'unchecked';  // the check couldn't run — accept, but flag for admin review

    public function __construct(
        public readonly string $status,
        public readonly ?string $documentType = null,
        public readonly ?string $reason = null,
    ) {}

    public static function unchecked(string $reason): self
    {
        return new self(self::UNCHECKED, null, $reason);
    }

    public function rejected(): bool
    {
        return $this->status === self::REJECTED;
    }
}
