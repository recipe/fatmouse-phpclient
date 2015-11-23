<?php

namespace Fatmouse;

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * PHP client to Fatmouse server and agents.
 * @see https://github.com/Scalr/fatmouse
 */
class Fatmouse 
{   
    /** @var string Events AMQP exchange */
    private $eventExchange = 'fatmouse.event';
    
    /** @var string Events routing key */
    private $eventRoutingKey = 'fatmouse.event';
    
    /** @var string Events queue to bind to $eventExchange with $eventRoutingKey */
    private $eventQueue = 'fatmouse.event';
    
    /** @var string API tasks AMQP exchange */
    private $apiExchange = 'api';
    
    /** @var string API tasks AMQP routing key */
    private $apiRoutingKey = 'api';
    
    /** @var array API tasks message publish parameters */
    private $publishParams = [
        'content_type' => 'application/json',
        'content_encoding' => 'UTF-8',
        'mandatory' => false
    ];
        
    /** @var array AMQP connection parameters */
    private $connectionParams = null;
    
    /** @var array AMQP connection SSL options */
    private $sslOptions = [
        'verify_peer' => false,
        'verify_peer_name' => false
    ];
    
    /** @var string AMQP connection class name (SSL/non-SSL) */
    private $connectionClass = null;
    
    /** @var PhpAmqpLib\Connection\AMQPStreamConnection AMQP connection object */
    private $connection = null;
    
    /** @var PhpAmqpLib\Channel\AMQPChannel channel to receive events from */
    private $eventChannel = null;
    
    /** @var Default timeout for Agent calls */
    public $defaultCallAgentTimeout = 5;

    /**
     * Create Fatmouse client.
     * 
     * Example:
     * <code>
     * <?php
     * $brokerUrl = "amqp://user:password@host/";
     * $fatmouse = new \Fatmouse\Fatmouse($brokerUrl);
     * ?>
     * </code>
     * 
     * @param string $brokerUrl RabbitMQ broker URL (Example: "amqp://user:password@example.com/")
     * @param array $sslOptions optional SSL connection options
     * @param array $publishParams optional Message publish parameters
     * @throws \InvalidArgumentException
     */
    public function __construct($brokerUrl, $sslOptions = true, $publishParams = null) 
    {
        // merge $publishParams with default ones
        if ($publishParams) {
            $this->publishParams = array_replace($this->publishParams, $publishParams);
        }

        $parsedUrl = parse_url($brokerUrl);
        if ($parsedUrl === false) {
            throw new \InvalidArgumentException('Malformed URL: ' . $brokerUrl);
        }
        $this->connectionParams = [
            'host' => $parsedUrl['host'],
            'port' => isset($parsedUrl['port']) ? $parsedUrl['port'] : 5672,
            'user' => isset($parsedUrl['user']) ? $parsedUrl['user'] : "guest",
            'password' => isset($parsedUrl['pass']) ? $parsedUrl['pass'] : "guest",
            'vhost' => (isset($parsedUrl['path']) && strlen($parsedUrl['path'])) > 1 ? 
                        substr($parsedUrl['path'], 1) : "/"
        ];
        if ($sslOptions) {
            $this->connectionParams['ssl_options'] = is_array($sslOptions) ? 
                array_replace($this->$sslOptions, $sslOptions) : $this->sslOptions;
        }

        $connectionClass = $sslOptions ? 'AMQPSSLConnection' : 'AMQPConnection';
        $this->connectionClass = 'PhpAmqpLib\\Connection\\' . $connectionClass;
    }

    /**
     * Close all open channels and finally close AMQP connection.
     */
    public function close() 
    {
        if ($this->eventChannel) {
            $this->eventChannel->close();
            $this->eventChannel = null;
        };
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * @return \PhpAmqpLib\Connection\AMQPStreamConnection AMQP connection object
     */
    public function getConnection() 
    {
        // TODO: Keepalive connection, auto reconnect
        if (!isset($this->connection)) {
            $reflect  = new \ReflectionClass($this->connectionClass);
            $this->connection = $reflect->newInstanceArgs($this->connectionParams);
        }
        return $this->connection;
    }

    /**
     * Generates Celery task-id and serializes task call into a message.
     * 
     * @param string $taskName Celery task name
     * @param array $kwargs optional Celery task parameters
     * @return [taskId, messageBody] task-id and call message body
     */
    private function prepareCeleryTask($taskName, $kwargs = null)
    {
        $taskId = uniqid('php_', true);
        $task = [
            'id' => $taskId,
            'task' => $taskName,
            'args' => [],
            'kwargs' => $kwargs ?: new \stdClass()
        ];
        return [$taskId, Util::jsonEncode($task)];
    }

    /**
     * Generic method to call Fatmouse API tasks.
     * 
     * Example: Call Fatmouse Server task and get result in a same process:
     * <code>
     * <?php
     * $taskName = "register_server";
     * $params = [
     *     "server_id" => "cf3a320b-7ac6-4b88-810a-c760a94e1875",
     *     "env_id" => "7743d825-9792-46bc-827f-285882f58fb2"
     * ];
     * $asyncResult = $fatmouse->callAsync($taskName, $params);
     * try {
     *     $result = $asyncResult->get();   
     * 
     * } catch (\Fatmouse\Errors\ServerException $e) {
     *     printf(
     *          "API error: %s errorType: %s  errorData: %s",
     *          $e->getMessage(), $e->getType(), var_export($e->getData(), true));
     * } catch (\Exception $e) {
     *     print("General error: " . $e->getMessage());
     * } finally {
     *     // After result was handled, it should be acknowledged to remove it from queue
     *     $asyncResult->ack();  
     * }
     * ?>
     * </code>
     * 
     * Example: Call Fatmouse Server task and get result in other process
     * <code>
     * <?php
     * // Process 1
     * $asyncResult = $fatmouse->callAsync($taskName, $params);
     * $taskId = $asyncResult->id;
     *
     * // Process 2
     * $asyncResult = $fatmouse->createAsyncResult($taskId);
     * try {
     *     $result = $asyncResult->get();
     * finally {
     *     $asyncResult->ack();  
     * }
     * </code>
     * 
     * @param string $taskName Fatmouse API task name
     * @param array $kwargs optional Task parameters
     * @param string $resultClassName optional Task result wrapper class
     * @return \Fatmouse\AsyncResult Future result
     */
    public function callAsync($taskName, $kwargs = null, $resultClassName = null) 
    {
        list($taskId, $serializedTask) = $this->prepareCeleryTask($taskName, $kwargs);
        $ch = $this->getConnection()->channel();
        try {
            $msg = new AMQPMessage($serializedTask, $this->publishParams);
            $ch->basic_publish($msg, $this->apiExchange, $this->apiRoutingKey);
        } finally {
            $ch->close();
        }
        return $this->createAsyncResult($taskId, $resultClassName);
    }

    /**
     * Factory function to create AsyncResult object.
     * 
     * @param string $taskId Celery task-id
     * @param string $resultClassName optional Task result wrapper class
     * @return \Fatmouse\AsyncResult Future result
     */
    public function createAsyncResult($taskId, $resultClassName = null) 
    {
        return new AsyncResult($this->getConnection(), $taskId, $resultClassName);
    }

    /**
     * Initialize event consumer. 
     * You should call consumeEvents() next, to receive events.
     * 
     * @param callable $callback Function to call with each consumed message
     */
    public function initEventConsumer($callback) 
    {
        if (! isset($this->eventChannel)) {
            $connection = $this->getConnection();
            $ch = $connection->channel();

            Util::declareQueue($connection, $this->eventQueue, [
                'durable' => true,
                'exclusive' => false
            ]);
            Util::declareExchange($connection, $this->eventExchange, 'direct', [
                'auto_delete' => false
            ]);
            $ch->queue_bind($this->eventQueue, $this->eventExchange, $this->eventRoutingKey);
            
            $wrapper = function ($msg) use ($callback) {
                $event = Event::newFromMessage($msg);
                return $callback($event);
            };
            $ch->basic_consume(
                $this->eventQueue,   
                '',         // consumer tag
                false,      // no_local
                false,      // no_ack
                false,      // exclusive
                false,      // nowait
                $wrapper    // callback function
            );
            $this->eventChannel = $ch;
        } else {
            throw new Errors\ClientException("Event consumer has already been started");
        }
    }

    /** 
     * Wait for events at least for $timeout seconds. 
     * All received events are passed to callback, registered in initEventConsumer().
     * 
     * Example: Consume Fatmouse Server events
     * <code>
     * <?php
     * $callback = function ($event) {
     *     printf(
     *         "Received event %s (ID: %s) with payload: %s",
     *         $event->getName(), 
     *         $event->getEventId(), 
     *         var_export($event->getPayload(), true)
     *     );
     *     // After result was handled, it should be acknowledged to prevent repeated handling
     *     $event->ack();
     * }
     * 
     * $fatmouse->initEventConsumer($callback);
     * $timeout = 1;
     * while (true) {
     *     try {
     *         // poll for events until timeout   
     *         $fatmouse->consumeEvents($timeout);
     *     } catch (\Exception $e) {
     *         print("Event loop error: %s" . $e->getMessage());
     *     }  
     * }
     * ?>
     * </code>
     * 
     * @param integer $timeout optional timeout in seconds
     */
    public function consumeEvents($timeout = 0) 
    {
        if (!isset($this->eventChannel)) {
            throw new Errors\ClientException("Event consumer has not been started yet");
        };
        try {
            $this->eventChannel->wait(null, false, $timeout);
        } catch (AMQPTimeoutException $e) {
            return false;
        }
    }

    /**
    * Generic method to synchronously call Fatmouse Agent tasks.
    * 
    * Create result queue for the task and bind it to results exchange.
    * Scalr uses agent tasks directly in synchronous manner (mostly for
    * retrieving server stats), and due to very high request rate we need
    * to clean up per-task result queues ASAP.
    *
    * `auto-delete` argument makes sure that queue will be deleted after last queue
    * consumer disconnected. (Won't be deleted if there weren't any consumers)
    *
    * `x-expires` queue option means that queue will be deleted if it's unused for the
    * specified time in milliseconds. Unused means that queue has no consumers,
    * the queue has not been redeclared, and basic.get has not been invoked.
    * 
    * Example: Call Fatmouse Agent task
    * <code>
    * <?php
    * $serverId = "cf3a320b-7ac6-4b88-810a-c760a94e1875";
    * $taskName = "sys.set_hostname";
    * $params = ["hostname" => "myexample.com"];
    * $timeout = 5; 
    * $result = $fatmouse->callAgentSync($serverId, $taskName, $params, $timeout);
    * ?>
    * </code>
    * 
    * @param string $serverId Scalr server-id to call task at
    * @param string $taskName Agent task name
    * @param array $kwargs optional Task parameters
    * @param integer $timeout optional Result timeout in seconds
    * @return \stdClass|mixed Task result 
    */
    public function callAgentSync($serverId, $taskName, $kwargs = null, $timeout = null) 
    {
        list($taskId, $serializedTask) = $this->prepareCeleryTask($taskName, $kwargs);
        $serverExchange = 'server.' . $serverId . '.celery';
        $connection = $this->getConnection();
        $channel = $connection->channel();
        $timeout = $timeout === null ? $this->defaultCallAgentTimeout : $timeout;

        try {
            Util::declareQueue($connection, $taskId, [
                'durable' => false,
                'auto_delete' => true,
                'arguments' => $timeout ? ['x-expires' => $timeout * 1000] : null
            ]);
            Util::declareExchange($connection, 'celeryresults', 'direct');
            $channel->queue_bind($taskId, 'celeryresults', $taskId);

            // Sending task to server's queue
            $msg = new AMQPMessage($serializedTask, $this->publishParams);
            $channel->basic_publish($msg, $serverExchange);

            $result = null;
            $setResult = function($msg) use (&$result) {
                $result = Util::jsonDecode($msg->body);
            };
            // Setting callback for message
            $channel->basic_consume(
                $taskId,    // queue
                '',         // consumer tag
                false,      // no_local
                true,       // no_ack
                false,      // exclusive
                false,      // nowait
                $setResult  // callback function
            );
            try {
                $channel->wait(null, false, $timeout);
            } catch (AMQPTimeoutException $e) {
                throw new Errors\ClientException(
                    sprintf("Timeout %d seconds exceeded while waiting for task '%s' to complete on server '%s'",
                            $timeout, $taskName, $server_id)
                );
            }
            return $result;
        } finally {
            $channel->close();           
        }
    }

    /**
     * Create proxy to Fatmouse Agent API.
     * 
     * @param string $serverId Scalr server-id
     * @return \Fatmouse\Agent\Agent Agent tasks proxy
     */
    public function agent($serverId)
    {
        return Agent\Agent($this, $serverId);
    }
    
    /**
     * Register $serverId, that Scalr is going to launch. 
     * It creates RabbitMQ user for server, declares input queue and out exchange, 
     * sets all necessary permissions
     * 
     * @param string $serverId Scalr server-id to register
     * @param string $envId Scalr env-id for this server
     * @return \Fatmouse\Types\Results\AsyncRegisterServer Future result
     */
    public function registerServer($serverId, $envId)
    {
        $taskName = 'register_server';
        return $this->callAsync($taskName, [
                'server_id' => $serverId,
                'env_id' => $envId
            ],
            AsyncResult::$TASK_NAME_TO_RESULT_CLASS[$taskName]
        );
    }

    /**
     * Deregister $serverId, after Scalr terminated this server. 
     * It removes all internal objects created for this server, and forgets about server.
     * 
     * @param string $serverId Scalr server-id to deregister
     * @return \Fatmouse\AsyncResult Future result
     */
    public function deregisterServer($serverId)
    {
        return $this->callAsync('deregister_server', 
            ['server_id' => $serverId]
        );
    }

    /**
     * Initalize server.
     * Fatmouse will initialize server like Scalarizr's HIR -> BeforeHostUp phase.
     * 
     * @param string $serverId Scalr server-id
     * @return \Fatmouse\Types\Results\AsyncRun Future result
     */
    public function runInitWorkflow($serverId)
    {
        $taskName = 'run';
        return $this->callAsync($taskName, [
                'name' => 'init',
                'parameters' => [
                    'server_id' => $serverId
                ],
                AsyncResult::$TASK_NAME_TO_RESULT_CLASS[$taskName]
            ]
        );
    }

    /**
     * Orchestrate fired event.
     * FatMouse will execute scripts associated with a given event on their targets.
     * 
     * @param string $eventId Scalr triggered event-id
     * @return \Fatmouse\Types\Results\AsyncRun Future result
     */
    public function runOrchestrationWorkflow($eventId)
    {
        $taskName = 'run';
        return $this->callAsync($taskName, [
                'name' => 'orchestration',
                'parameters' => [
                    'event_id' => $eventId
                ],
                AsyncResult::$TASK_NAME_TO_RESULT_CLASS[$taskName]
            ]
        );
    }
}
