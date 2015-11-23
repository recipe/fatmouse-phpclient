<?php

namespace Fatmouse\Types;

/**
 * Incapsulates Fatmouse workflow.
 */
class Workflow extends BaseType
{
    /** @var \Fatmouse\Types\Celery Celery section */
    public $celery;

    /**
     * {@inheritdoc}
     */
    public function __construct($object)
    {
        parent::__construct($object);
        if ($this->celery) {
            $this->celery = Celery($this->celery);
        }
    }
}