<?php

namespace grnrbt\yii2\beanstalkTaskManager;

use sergebezborodov\beanstalk\Controller;

class WorkerController extends Controller
{
    public function actionRunTask($rawTask)
    {
        $task = Task::fromString($rawTask);
        return \Yii::$app->get('taskManager')->runTask($task);
    }

    public function actionKickBuryTasks($limit = null)
    {
        list($total, $kicked) = \Yii::$app->get('taskManager')->kickBuriedTasks($limit);
        echo "Kicked {$kicked} jobs of {$total}.\n";
    }

    public function actionDeleteBuryTasks($limit = null)
    {
        list($total, $deleted) = \Yii::$app->get('taskManager')->deleteBuriedTasks($limit);
        echo "Deleted {$deleted} jobs of {$total}.\n";
    }
}