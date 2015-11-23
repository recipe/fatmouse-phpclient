<?php

namespace Fatmouse;

/**
 * Asynchronous result of a Fatmouse task. 
 * Result will be marked as handled and removed from RabbitMQ broker only 
 * after acknowledge with $this->ack()
 */
class AsyncResult 
{
    /** Celery task statuses */
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_FAILURE = 'FAILURE';

    /** @var Mapping between task name and PHP result wrapper class */
    public static $TASK_NAME_TO_RESULT_CLASS = [
        'register_server' => Types\Results\RegisterServer::class,
        'run' => Types\Results\Run::class  
    ];
    
    /** @var \PhpAmqpLib\Message\AMQPMessage Result message */
    private $message = null;

    /** @var \PhpAmqpLib\Channel\AMQPChannel AMQP Channel */
    private $channel = null;

    /** @var string Class to wrap result into */
    private $resultClassName = null;

    /** @var mixed Result value */
    private $result = null;

    /** @var \Fatmouse\Errors\ServerException Value when result is error */
    private $exception = null;

    /** @var boolean whether result was received? */
    private $hasResult = false;

    /**
     * Constructor.
     * 
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $connection AMQP connection
     * @param string $taskId Celery task-id
     * @param string $resultClassName optional Task result wrapper class
     */
    function __construct($connection, $taskId, $resultClassName = null) 
    {
        assert(isset($taskId));
        $this->connection = $connection;
        $this->taskId = $taskId;
        $this->resultClassName = $resultClassName;
    }

    /**
     * @return string Celery task ID
     */
    public function getTaskId() 
    {
        return $this->taskId;
    }

    /**
     * Declare and bind result queue
     * 
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel AMQP channel
     */
    private function declareResultQueue($channel) 
    {
        Util::declareQueue($this->connection, $this->taskId, [
            'exclusive' => false,
            'auto_delete' => true,
            'arguments' => ["x-expires" => 3600000]
        ]);
        Util::declareExchange($this->connection, 'celeryresults', 'direct', [
            'exclusive' => false
        ]);
        $channel->queue_bind($this->taskId, 'celeryresults');
    }

    /**
     * Wait result message and assign into $this->message.
     * 
     * @param integer $timeout optional timeout in seconds
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    private function waitResult($timeout = 0)
    {
        if (!$this->channel) {
            $ch = $this->connection->channel();
            $this->declareResultQueue($ch);
            $ch->basic_consume(
                $this->taskId,    // queue 
                '',               // consumer tag 
                false,            // no_local 
                false,            // no_ack
                false,            // exclusive
                false,            // nowait
                function ($message) {
                    $this->message = $message;
                }
            );
            $this->channel = $ch;
        }
        // Get message from result queue with timeout
        $this->channel->wait(
            null,               // allowed methods
            false,              // non_blocking
            $timeout
        );
    }

    /**
     * Translate result value into PHP object.
     */
    private function translateResult()
    {
        if (!isset($this->message)) {
            throw Errors\ClientException(
                "Result has not yet been received, so could not get result"
            );
        }
        $celeryResult = Util::jsonDecode($this->message->body);
        if (self::STATUS_FAILURE == $celeryResult->status) {
            $this->exception = new Errors\ServerException($celeryResult->result);
        } else {
            if ($this->resultClassName) {
                $ref = new \ReflectionClass($this->resultClassName);
                $this->result = $ref->newInstance($celeryResult->result);
            } else {
                $this->result = $celeryResult->result;
            }
        }
    }

    /**
     * Wait for task result and return it.
     * 
     * @return \stdClass|mixed Task result
     * @throws \Fatmouse\Errors\ServerException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function get($timeout = 0) 
    {
        if (!$this->hasResult) {
            $this->waitResult($timeout);
            $this->translateResult();
            $this->hasResult = true;
        }
        if ($this->hasResult) {
            if ($this->exception) {
                throw $this->exception;
            } else {
                return $this->result;
            }
        }
    }

    /**
     * Acknowledge result message.
     * This is required to make other clients never receive message after this client will disconnects
     */
    public function ack() 
    {
        if (! $this->message) {
            throw new Errors\ClientException(
                "Result has not yet been received, so could not been acknowledged"
            );
        }
        $this->channel->basic_ack($this->message->delivery_info['delivery_tag']);
        $this->channel->queue_delete($this->taskId);
        $this->channel->close();
        $this->channel = null;
    }
}
