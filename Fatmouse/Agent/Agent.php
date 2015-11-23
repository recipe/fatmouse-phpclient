<?php

namespace Fatmouse\Agent;

use Fatmouse\Agent\Tasks\Sys;

/**
 * A proxy class to Fatmouse Agent API
 */
class Agent
{
    /** @var \Fatmouse\Agent\Sys OS level tasks */
    public $sys;
    
    /**
     * Constructor
     * 
     * @param \Fatmouse\Fatmouse $fatmouse
     * @param string $serverId
     */
    public function __construct($fatmouse, $serverId)
    {
        $this->fatmouse = $fatmouse;
        $this->serverId = $serverId;
        $this->sys = Sys($this);
    }

    /**
     * A generic method to call agent tasks
     * 
     * @param string $taskName Agent task name
     * @param array $kwargs task parameters
     * @param integer $timeout Result timeout in seconds
     * @return \stdClass|mixed Task result
     */
    public function call($taskName, $kwargs, $timeout = null)
    {
        return $this->fatmouse->callAgentSync($this->serverId, $taskName, $kwargs, $timeout);
    }
}
