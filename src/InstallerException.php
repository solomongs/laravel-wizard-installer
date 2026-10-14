<?php

namespace SolomonGbeta\LaravelWizard;

class InstallerException extends \RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'setup_error', public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
