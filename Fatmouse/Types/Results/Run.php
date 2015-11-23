<?php

namespace Fatmouse\Types\Results;

use Fatmouse\Types\BaseType;
use Fatmouse\Types\Workflow;
use Fatmouse\Types\Task;

/**
 * Incapsulate 'run' task result
 */
class Run extends BaseType
{   
    /** @var mixed Workflow result value */
    public $result;

    /** @var \Fatmouse\Types\Workflow Workflow section */ 
    public $workflow;

    /** @var \Fatmouse\Types\Task[] Completed tasks */
    public $completedTasks = [];

    /** @var \Fatmouse\Types\Task[] Failed tasks */
    public $failedTasks = [];

    /**
     * {@inheritdoc}
     */
    public function __construct($object)
    {
        parent::__construct($object);
        if ($this->workflow) {
            $this->workflow = Workflow($this->workflow);
        }
        $taskFactory = function ($task) {
            return new Task($task);
        };
        $this->completedTasks = array_map($taskFactory, $this->completedTasks);
        $this->failedTasks = array_map($taskFactory, $this->failedTasks);
    }
}
