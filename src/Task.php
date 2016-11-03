<?php

namespace grnrbt\yii2\beanstalkTaskManager;

class Task
{
    private $handler;
    private $data;

    public static function fromString($sourceStr)
    {
        $raw = json_decode($sourceStr, true);
        return new static($raw['handler'], $raw['data']);
    }

    public function __construct($handler, $data = null)
    {
        if (is_array($handler) && count($handler) != 2) {
            throw new \InvalidArgumentException("Invalid handler");
        }
        if (!is_callable($handler) && !method_exists($handler[0], $handler[1])) {
            throw new \InvalidArgumentException("Undefined handler");
        }
        $this->handler = $handler;
        $this->data = $data;
    }

    public function __toString()
    {
        return json_encode([
            'handler' => $this->handler,
            'data' => $this->data,
        ]);
    }

    /**
     * @return mixed
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }
}