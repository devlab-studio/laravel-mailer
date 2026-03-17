<?php

namespace Devlab\LaravelMailer\CustomMail\Exceptions;

class EmptyDestinationsException extends \Exception
{

    /**
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;

        parent::__construct($message);
    }

    /**
     * @return self
     */
    public static function emptyDestinationsError()
    {
        return new self(
            'No destinations provided for the notification.'
        );
    }
}
