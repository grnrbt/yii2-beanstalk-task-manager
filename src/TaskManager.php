<?php

namespace grnrbt\yii2\beanstalkTaskManager;

use sergebezborodov\beanstalk\Beanstalk;
use yii\base\Component;

class TaskManager extends Component
{
    /**
     * @var bool Run tasks immediately in current thread.
     */
    public $workSynchronously = false;
    /**
     * @var bool Put tasks into the order and send them to beanstalk on \Yii::$app::EVENT_AFTER_REQUEST if true.
     * Otherwise sent tasks to beanstalk immediately.
     */
    public $useOrder = true;
    /**
     * @var string
     */
    public $tubeName = 'default';
    /**
     * @var string Id of beanstalk component
     */
    public $beanstalk;
    /**
     * @var int Default task priority
     */
    public $defaultPriority = 100;
    /**
     * @var int Default task delay in seconds
     */
    public $defaultDelay = 0;
    /**
     * @var Beanstalk
     */
    private $beanstalkComponent;
    /**
     * @var array
     */
    private $order = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $app = \Yii::$app;
        $this->beanstalkComponent = $app->get($this->beanstalk);
        $app->on($app::EVENT_AFTER_REQUEST, [$this, 'sendOrderToBeanstalk']);
    }

    /**
     * Add new task to the order.
     *
     * @param callable $handler
     * @param array $data = [] Arguments who will be passed to $handler.
     * @param int $delay = 0
     * @param int $priority = 0
     */
    public function createTask($handler, array $data = [], $delay = 0, $priority = 0)
    {
        if ($delay === null) {
            $delay = $this->defaultDelay;
        }
        if ($priority === null) {
            $priority = $this->defaultPriority;
        }
        $task = new Task($handler, $data);
        if ($this->workSynchronously) {
            $this->runTask($task);
        } elseif (!$this->useOrder) {
            $this->sendTaskToBeanstalk($task, $delay, $priority);
        } else {
            $this->addTaskToOrder($task, $delay, $priority);
        }
    }

    /**
     * Run task.
     *
     * @param Task $task
     * @param bool $suppressExceptions = true Suppress all exceptions if true.
     * @return bool Return `true` if task was running successfully. Return `false` on error.
     * @throws \Exception
     */
    public function runTask(Task $task, $suppressExceptions = true)
    {
        try {
            call_user_func_array($task->getHandler(), $task->getData());
        } catch (\Exception $e) {
            if (!$suppressExceptions) {
                throw $e;
            }
            \Yii::error("TaskManager exception. Message: {$e->getMessage()}. Trace: {$e->getTraceAsString()}");
            return false;
        }
        return true;
    }

    /**
     * Kick buried jobs.
     *
     * @param int $limit = null Max kicked jobs. All jobs will be kicked if `null`.
     * @return array [$buriedCount, $kickedCount]
     */
    public function kickBuriedTasks($limit = null)
    {
        $buriedCount = $this->countBuriedJobs();
        if ($limit === null) {
            $limit = $buriedCount;
        }
        $this->beanstalkComponent->useTube($this->tubeName);
        $kickedCount = $this->beanstalkComponent->kick($limit);
        return [$buriedCount, $kickedCount];
    }

    /**
     * Delete buried jobs.
     *
     * @param int $limit = null Max deleted jobs. All jobs will be deleted if `null`.
     * @return array [$buriedCount, $deletedCount]
     */
    public function deleteBuriedTasks($limit = null)
    {
        $buriedCount = $this->countBuriedJobs();
        if ($limit === null) {
            $limit = $buriedCount;
        }
        $this->beanstalkComponent->useTube($this->tubeName);
        $deletedCount = 0;
        while ($job = $this->beanstalkComponent->peekBuried()) {
            if ($this->beanstalkComponent->delete($job['id'])) {
                $deletedCount++;
            }
            if ($deletedCount >= $limit) {
                break;
            }
        }
        return [$buriedCount, $deletedCount];
    }

    /**
     * @return int
     */
    private function countBuriedJobs()
    {
        return $this->beanstalkComponent->stats()['current-jobs-buried'];
    }

    /**
     * @param Task $task
     * @param int $delay
     * @param int $priority
     */
    private function addTaskToOrder(Task $task, $delay, $priority)
    {
        $this->order[] = [$task, $delay, $priority];
    }

    /**
     * @param Task $task
     * @param int $delay
     * @param int $priority
     */
    private function sendTaskToBeanstalk(Task $task, $delay, $priority)
    {
        $this->beanstalkComponent->addJob($this->tubeName, $task, null, $delay, $priority);
    }

    private function sendOrderToBeanstalk()
    {
        foreach ($this->order as list($task, $delay, $priority)) {
            $this->sendTaskToBeanstalk($task, $delay, $priority);
        }
        $this->order = [];
    }
}