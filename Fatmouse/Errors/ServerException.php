<?php

namespace Fatmouse\Errors;

/**
 * Fatmouse server error
 */
class ServerException extends Exception 
{
    /** @var \stdClass Celery error object */
    private $pythonException;

    /**
     * {@inheritdoc}
     */
    public function __construct($pythonException, $code = 0, $previous = null)
    {
        $this->pythonException = $pythonException;
        Exception::__construct($this->pythonException->exc_message, $code, $previous);
    }

    /**
     * @return string A human-readable error type (Example: "DuplicateError")
     */
    public function getType() 
    {
        return $this->pythonException->exc_type;
    }

    /**
     * @return \stdClass Data object, associated with error. Depends from error type.
     */
    public function getData()
    {
        return $this->pythonException->exc_data ?: [];
    }
}