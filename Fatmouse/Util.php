<?php

namespace Fatmouse;

use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Utilities
 */
class Util
{
    /**
     * @var array Default parameters for queue_declare
     */
    private static $defaultDeclareQueueParams = [
        'durable' => true,
        'exclusive' => true,
        'auto_delete' => false,
        'nowait' => false,
        'arguments' => null
    ];

    /**
     * First tries to create queue using passive flag.
     * If queue does not exist, creates new with provided settings
     * 
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $connection AMQP connection
     * @param string $name queue name
     * @param array $params optional queue_declare parameters
     * @see self::$defaultDeclareQueueParams
     */
    public static function declareQueue($connection, $name, $params = [])
    {
        $params = array_replace(self::$defaultDeclareQueueParams, $params);
        $ch = $connection->channel();
        try {
            $ch->queue_declare($name, true);
        } catch (AMQPProtocolChannelException $e) {
            $ch = $connection->channel();
            if ($params['arguments'] and is_array($params['arguments'])) {
                $params['arguments'] = new AMQPTable($params['arguments']);
            }
            $ch->queue_declare(
                $name, false, 
                $params['durable'], 
                $params['exclusive'],
                $params['auto_delete'], 
                $params['nowait'],
                $params['arguments']
            );
        };
    }

    /**
     * @var array Default paramenters for exchange_declare
     */
    private static $defaultDeclareExchangeParams = [
        'durable' => true,
        'auto_delete' => false,
        'internal' => false,
        'nowait' => false,
        'arguments' => null
    ];

    /**
     * First tries to create exchange using passive flag.
     * If exchange does not exist, creates new with provided settings.
     * 
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $connection AMQP connection
     * @param string $name Exchange name
     * @param string $type Exchange type
     * @param array $params optional declare exchange parameters
     * @see self::$defaultDeclareExchangeParams
     */
    public static function declareExchange($connection, $name, $type, $params = []) 
    {
        $params = array_replace(self::$defaultDeclareExchangeParams, $params);
        $ch = $connection->channel();
        try {
            $ch->exchange_declare($name, $type, true);
        } catch (AMQPProtocolChannelException $e) {
            $ch = $connection->channel();
            if ($params['arguments'] and is_array($params['arguments'])){
                $params['arguments'] = new AMQPTable($params['arguments']);
            }
            $ch->exchange_declare(
                $name, $type, false, 
                $params['durable'], 
                $params['auto_delete'],
                $params['internal'], 
                $params['nowait'], 
                $params['arguments']
            );
        };
    }

    /**
     * JSON decoder
     * 
     * @param string JSON string
     * @return mixed Decoded value
     * @throws \Fatmouse\Errors\ClientException on decode error
     */
    public static function jsonDecode($json) 
    {
        if (! is_string($json)) {
            throw new Errors\ClientException('JSON decoder: $json is expected be a string but got an object');
        }
        $result = json_decode($json);
        if ($result === null) {
            throw new Errors\ClientException('JSON decoder: ' . json_last_error_msg());
        }
        return $result;
    }

    /**
     * JSON encoder
     * 
     * @param mixed Value to encode
     * @return string JSON string
     * @throws \Fatmouse\Errors\ClientException on encode error
     */
    public static function jsonEncode($value)
    {
        $result = json_encode($value);
        if ($result === null) {
            throw new Errors\ClientException('JSON encoder: ' . json_last_error_msg());
        }
        return $result;  
    }

    /**
     * Convert under_score to camelCase
     * 
     * @param string $value Value to convert
     * @return string Converted value
     */
    public static function camelize($value)
    {
        return lcfirst(preg_replace_callback('/(_|^)([^_]+)/', 
            function($c){
                return ucfirst(strtolower($c[2]));
            }, 
            $value
        ));
    }
}
