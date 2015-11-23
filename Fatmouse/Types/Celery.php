<?php

namespace Fatmouse\Types;

/**
 * Incapsulate Celery tasks
 */
class Celery extends BaseType
{
    /** @var string Celery task-id (Example: "7e644d6d-28f4-42b7-863a-96f82e756183") */
    public $taskId;

    /** @var string Celery task name (Example: "chef.install") */
    public $name;
}