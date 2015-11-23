<?php

namespace Fatmouse;

/**
 * Event, received from Fatmouse. 
 * Event will be marked as "handled" and removed from RabbitMQ only after  
 * acknowledge with $this->ack();
 */
class Event 
{
    const NAME_TASK_FINISH = 'task_finish';
    const NAME_REBOOT_COMPLETE = 'reboot_complete';
    
    /** @var \stdClass Fatmouse Event object */
    private $body = null;

    /** @var \PhpAmqpLib\Message\AMQPMessage Message with serialized Fatmouse event */
    private $message = null;

    /**
     * Constructor.
     * 
     * @param \PhpAmqpLib\Message\AMQPMessage $message Message with serialized event
     * @param \stdClass $body Fatmouse Event object
     */
    public function __construct($message, $body) 
    {
        $this->message = $message;
        $this->body = $body;
    }

    /**
     * A factory to create event from AMQP message.
     * 
     * @param \PhpAmqpLib\Message\AMQPMessage $message Message with serialized event
     * @return object
     */
    public static function newFromMessage($message)
    {
        $body = Util::jsonDecode($message->body);
        if ($body->date) {
            $body->date = new \Datetime($body->date);
        }
        $ref = new \ReflectionClass(self::eventPayloadClassForName($body->name));
        $body->payload = $ref->newInstance($body->payload);
        $ref = new \ReflectionClass(self::eventClassForName($body->name));
        return $ref->newInstance($message, $body);
    }
    
    /**
     * Map event name to PHP class wrapper for event payload
     * 
     * @param string $eventName Event name (Example: "reboot_complete")
     * @return string PHP Class name
     */
    public static function eventPayloadClassForName($eventName)
    {
        $name = ucfirst(Util::camelize($eventName)) . 'Payload';
        if (file_exists(__DIR__ . '/Types/Events/' . $name . '.php')) {
            $retval = 'Fatmouse\\Types\\Events\\' . $name;
        } else {
            $retval = 'Fatmouse\\Types\\BaseType';          
        }
        return $retval;
    }
    
    /**
     * Map event name to PHP class wrapper for event
     * @param string $eventName Event name (Example: "reboot_complete")
     * @return string PHP Class name
     */
    public static function eventClassForName($eventName)
    {
        $name = ucfirst(Util::camelize($eventName));
        if (file_exists(__DIR__ . '/Types/Events/' . $name . '.php')) {
            $retval = 'Fatmouse\\Types\\Events\\' . $name;          
        } else {
            $retval = 'Fatmouse\\Event';
        }
        return $retval;
    }

    /**
     * @return string Event ID
     */
    public function getEventId() 
    {
        return $this->body->event_id;
    }

    /**
     * @return \stdClass Event payload object
     */
    public function getPayload() 
    {
        return $this->body->payload;
    }

    /**
     * @return string Event name
     */
    public function getName() 
    {
        return $this->body->name;
    }

    /**
     * @return \DateTime Event occurrence date
     */
    public function getDate() 
    {
        return $this->body->date;
    }

    /**
     * Acknowledge event message. 
     * To make other clients never receive it after this client disconnects
     */
    public function ack() 
    {
        $this->message->delivery_info['channel']->basic_ack(
            $this->message->delivery_info['delivery_tag']
        );
    }
    
    public function __toString() 
    {
        return sprintf("object(Event:%s id=%s)", $this->getName(), $this->getEventId());
    }
}
