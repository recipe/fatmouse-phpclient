<?php

namespace Fatmouse\Types\Events;

use Fatmouse\Types\BaseType;

/**
 * Fired after server OS was rebooted.
 */
class RebootCompletePayload extends BaseType
{
    /** @var string Scalr server-id of rebooted server */
    public $serverId;

    /** @var string Operating system boot-id */
    public $bootId;
}
