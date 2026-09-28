<?php

namespace Weglot\Client\Api\Exception;

use Weglot\Parser\Definitions\Exception\AbstractException;
use Weglot\Parser\Definitions\Exception\WeglotCode;

class InvalidLanguageException extends AbstractException
{
    public function __construct()
    {
        parent::__construct(
            'The given language is invalid.',
            WeglotCode::PARAMETERS
        );
    }
}
