<?php

namespace Fatmouse\Types\Results;

use Fatmouse\Types\BaseType;

/**
 * Incapsulate 'register_server' task result
 */
class RegisterServer extends BaseType
{
    /** @var string RabbitMQ username */
    public $username;

    /** @var string RabbitMQ password */
    public $password;

    /** @var \stdClass Agent configuration (to inject as /etc/fatmouse/fatmouse.yaml) */
    public $agentConfig;

    /** @var \stdClass Celery configuration (to inject as /etc/fatmouse/celery.yaml) */
    public $celeryConfig;
}