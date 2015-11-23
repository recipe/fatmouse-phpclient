<?php

namespace Fatmouse\Types;

/**
 * Incapsulates FSM state for a given Task
 */
class Fsm extends BaseType
{
    /** @var string Human friendly workflow FSM state (Example: "sync time from ntp server") */
    public $state;
}