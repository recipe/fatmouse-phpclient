<?php

$loader = require __DIR__ . '/../../vendor/autoload.php';
$loader->add('', __DIR__ . '/../..');


use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;


class FeatureContext implements Context, SnippetAcceptingContext
{
    private function createFatmouse()
    {
        return new Fatmouse\Fatmouse("amqp://localhost");
    }

    /* @AfterScenario */
    public function after(AfterScenarioScope $scope)
    {
        $this->fatmouse->close();
    }

    /**
     * @Given I connect to fatmouse
     */
    public function iConnectToFatmouse()
    {
        $this->fatmouse = $this->createFatmouse();
    }

    /**
     * @When I call task
     */
    public function iCallTask()
    {
        $this->serverId = uniqid('behat_');
        $this->asyncResult = $this->fatmouse->registerServer($this->serverId, 123);
    }

    /**
     * @Then I can get result in same process
     */
    public function iCanGetResultInSameProcess()
    {
        $this->result = $this->asyncResult->get();
        assert($this->result->username == $this->serverId);
    }

    /**
     * @Then I can get result in other process
     */
    public function iCanGetResultInOtherProcess()
    {
        $this->fatmouse = $this->createFatmouse();
        $this->asyncResult = $this->fatmouse->createAsyncResult(
            $this->asyncResult->getTaskId()
        );
        $this->result = $this->asyncResult->get();
        assert($this->result->username == $this->serverId);
    }

    /**
     * @Then I can acknowledge result
     */
    public function iCanAcknowledgeResult()
    {
        $this->asyncResult->ack();
        $_asyncResult = $this->fatmouse->createAsyncResult(
            $this->asyncResult->getTaskId()
        );
        try {
            $_asyncResult->get($timeout=1);
            assert(0);
        } catch (AMQPTimeoutException $e) {
            // pass
        }
    }


    /**
     * @When I send task to fatmouse
     */
    public function iSendTaskToFatmouse()
    {
        # TODO: check name, args, kwargs
        # Unique name is for testing purposes only, task name should be
        # registered fatmouse task.
        $this->task_name = uniqid();
        $this->task_result = $this->fatmouse->apply_async($this->task_name);
        $this->task_id = $this->task_result->task_id;
    }

    /**
    * @Then I see this task in celery queue
    */
    public function check_task_has_beem_delivered()
    {
        $ctx = $this;

        $callback = function ($msg) use ($ctx) {
            $ctx->sent_task = $msg;
        };
        $ch = $this->connection->channel();
        # TODO: use celery_results_queue
        $message = $ch->basic_get('celery');
        $this->task_msg = $message;

        $task_name = json_decode($message->body)->task;
        $ch->basic_ack($message->delivery_info['delivery_tag']);
        assert(($task_name == $this->task_name));
    }

    /**
     * @Then I send result to result queue for the task
     */
    public function send_task_result()
    {   
        $ch = $this->connection->channel();
        $task_id = json_decode($this->task_msg->body)->id;
        $ch->queue_declare(                            
            $task_id,              /* name */               
            false,                 /* passive */         
            true,                  /* durable */         
            false,                 /* exclusive */          
            false                  /* auto_delete */         
        );

        $this->random_result = uniqid();
        $body = json_encode(array(
            'task_id' => $task_id,
            'result' =>  $this->random_result
        ));
        $result = new AMQPMessage($body);

        $ch->exchange_declare('celeryresults', 'direct');
        $ch->queue_bind($task_id, 'celeryresults', $task_id);
        $ch->basic_publish($result, 'celeryresults', $task_id);
    }

    /**
     * @When I reconnect to fatmouse
     */
    public function iReconnectToFatmouse()
    {
        $this->connection->close();
        $this->connection = $this->get_connection($this->url);
    }

    /**
     * @Then I can receive task result
     */
    public function iStillCanReceiveTaskResult()
    {
        $this->result = new TaskResult($this->connection, $this->task_id);
        $body = $this->result->get(5);
        assert($body !== false);
        assert($body->result == $this->random_result);
    }

    /**
     * @When I acknowledge task result
     */
    public function iAcknowledgeTaskResult()
    {
        $this->result->ack();
    }

    /**
     * @Then I can't receive no task results
     */
    public function iCanTReceiveNoTaskResults()
    {
        $this->result = new TaskResult($this->connection, $this->task_id);
        $body = $this->result->get(5);
        assert($body === false);

    }

    /**
    *  @When I start listening for events from fam server
    */

    public function start_listen_events() 
    {
        $ctx = $this;
        if (!isset($ctx->events)) {
            $ctx->events = array();
        };

        $cb = function ($event) use ($ctx) {
            array_push($ctx->events, $event);
        };
        $this->fatmouse->setup_consumer($cb);
    }


    /**
    *  @When I send random event to event queue
    */

    public function i_send_random_event() {
        $this->event_body = uniqid();
        $msg = new AMQPMessage($this->event_body);
        $ch = $this->connection->channel();
        $ch->basic_publish($msg, '', 'fatmouse.event');
    }

    /**
    * @Then I see that this very event was handled
    */

    public function event_was_handled() {
        $this->fatmouse->drain_events(5);
        assert($this->events[0]->message->body == $this->event_body);
    }

    /**
    * @When I restart event listener 
    */
    public function restart_listener() {
        $this->fatmouse->disconnect();
        $this->events = array();
        $this->start_listen_events();
    }
    
    /**
    * @When I acknowledge event
    */
    public function ack_event() {
        $this->events[0]->ack();
    }

    /**
    * @Then I see no new events were sent
    */
    public function no_events_were_received() {
        $this->fatmouse->drain_events(5);
        assert($this->events == array());
    }

}
