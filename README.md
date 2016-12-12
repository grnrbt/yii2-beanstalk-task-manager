# Yii2 task manager component uses beanstalk

## Installation

Require composer package: `composer require grnrbt/yii2-beanstalk-task-manager "*"`

Add components to your `/config/config.php` file:
```php
<?php
return [
    'components' => [
        'taskManager' => [
            'class' => \grnrbt\yii2\beanstalkTaskManager\TaskManager::class,
            'beanstalk' => 'beanstalk',
        ],
        'beanstalk' => [ // This id you should set in TaskManager::beanstalk field
            'class' => \sergebezborodov\beanstalk\Beanstalk::class,
        ],
    ],
    // ...
];
```

Add worker config file (`/config/worker.php`):
```php
<?php
return \yii\helpers\ArrayHelper::merge(require (__DIR__.'/config.php'),[
    'on beforeAction' => function () {
        Yii::$app->db->open();
    },
    'on afterAction' => function () {
        Yii::$app->db->close();
    },
    'exitOnDbException' => true,
    'controllerMap' => [
        'worker'=> \grnrbt\yii2\beanstalkTaskManager\WorkerController:: class,
    ],
    'components' => [
        'router' => [
            'class' => \sergebezborodov\beanstalk\Router::class,
            'routes' => [
                'default'=>'worker/run-task'
            ],
        ],
    ],
]);
```

Add worker executed file (`/worker.php`):
```php
<?php
defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

require(__DIR__ . '/vendor/autoload.php');
require(__DIR__ . '/vendor/yiisoft/yii2/Yii.php');

$config = require(__DIR__ . '/config/worker.php');
$application = new \sergebezborodov\beanstalk\Application($config);
return $application->run();
```

Run worker application:
```
$ cd app-directory
$ php ./worker.php
```

Enjoy.