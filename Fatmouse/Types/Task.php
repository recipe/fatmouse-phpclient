<?php

namespace Fatmouse\Types;

use Fatmouse\Errors\ServerException;

/**
 * Incapsulates Fatmouse task
 */
class Task extends BaseType
{
    const STATE_COMPLETED = 'completed';
    const STATE_FAILED = 'failed';

    /** @var \Fatmouse\Types\Celery Celery section */
    public $celery;

    /** @var \Fatmouse\Types\Fsm FSM section */
    public $fsm;

    /** @var string Task state name */
    public $state;

    /** @var string Scalr server-id */
    public $serverId;

    /** @var \Datetime Execution start date */
    public $startDate;

    /** @var \Datetime Execution finish date */
    public $endDate;   

    /** @var \Fatmouse\Errors\ServerException Task error */
    private $exception;
    
    /** @var mixed Task result, when task succeed */
    private $result;

    /**
     * {@inheritdoc}
     */
    public function __construct($object)
    {
        $result = $object->result;
        unset($object->result);
        
        parent::__construct($object);
        
        if ($this->fsm) {
            $this->fsm = new Fsm($this->fsm);
        }
        if ($this->celery) {
            $this->celery = new Celery($this->celery);
        }
        if (self::STATE_FAILED == $this->state) {
            $this->exception = new ServerException($result);
        } else {
            $this->result = $result;
        }
    }
    
    /**
     * Return task result or raise task error.
     * 
     * @return mixed task result value
     * @throws \Fatmouse\Errors\ServerException on task error
     */
    public function getResult()
    {
        if ($this->exception) {
            throw $this->exception;
        }
        return $this->result;
    }
    
    /**
     * Return task exception object.
     * 
     * @return \Fatmouse\Errors\ServerException
     */
    public function getException()
    {
        return $this->exception;
    }
    
    /**
     * Return true when task was failed.
     * 
     * @return boolean
     */
    public function failed()
    {
        return $this->state == self::STATE_FAILED;
    }
}
