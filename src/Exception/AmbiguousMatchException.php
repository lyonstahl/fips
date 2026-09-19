<?php

declare(strict_types=1);

namespace LyonStahl\Fips\Exception;

final class AmbiguousMatchException extends FipsException
{
    /** @var array<int,object> */
    private $candidates;

    /**
     * @param array<int,object> $candidates
     */
    public function __construct(string $message, array $candidates)
    {
        parent::__construct($message);
        $this->candidates = array_values($candidates);
    }

    /**
     * @return array<int,object>
     */
    public function candidates(): array
    {
        return $this->candidates;
    }
}
