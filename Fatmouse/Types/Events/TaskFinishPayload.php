<?php

namespace Fatmouse\Types\Events;

use Fatmouse\Types\BaseType;
use Fatmouse\Types\Task;
use Fatmouse\Types\Workflow;

/**
 * Fired after Fatmouse task finishes it's execution
 */
class TaskFinishPayload extends BaseType
{
    /** @var \Fatmouse\Types\Task Task that finishes it's execution */
    public $task;

    /** @var \Fatmouse\Types\Workflow workflow associated with task */
    public $workflow;

    /**
     * {@inheritdoc}
     */
    function __construct($object)
    {
        parent::__construct($object);
        $this->task = new Task($this->task);
        if ($this->workflow) {
            $this->workflow = new Workflow($this->workflow);
        }
    }
}
