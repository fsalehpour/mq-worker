<?php

namespace MQWorker;

use Exception;
use PhpAmqpLib\Exception\AMQPIOException;

class MQWorker
{
    protected $ch;
    protected $queue;
    protected $exchange;
    protected $routingPattern;
    protected $routing;
    protected $strict;

    /**
     * MQWorker constructor.
     */
    public function __construct($ch, $queue, $exchange, $pattern, $strict = true)
    {
        $this->ch = $ch;
        $this->queue = $queue;
        $this->exchange = $exchange;
        $this->routingPattern = $pattern;
        $this->strict = $strict;
    }

    public function run()
    {
        $this->setupMQ();

        $this->ch->basic_consume($this->queue, '', false, false, false, false, [$this, 'listenForRouting']);

        while (count($this->ch->callbacks)) {
                $this->ch->wait(null, false, null);
        }

        $this->ch->close();
    }

    public function setupMQ()
    {
        $this->ch->queue_declare($this->queue, false, true, false, false);
        $this->ch->queue_bind($this->queue, $this->exchange, $this->routingPattern);
    }

    public function listenForRouting($message) {
        try {
            $routing_key = $message->delivery_info['routing_key'];

            if (!isset($this->routing[$routing_key])) {
                throw new Exception("Routing key ${routing_key} is not defined");
            }

            call_user_func([$this, $this->routing[$routing_key]], $message);
        } catch (Exception $e) {
            if ($this->strict) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
        finally {
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
        }
    }
}
