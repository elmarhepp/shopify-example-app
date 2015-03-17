<?php namespace App\MyClasses;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\ConfigureLogging as BaseLoggingConfiguration;
use Illuminate\Log\Writer;

class MyConfigureLogging extends BaseLoggingConfiguration
{

    /**
     * Configure the Monolog handlers for the application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     * @param  \Illuminate\Log\Writer $log
     * @return void
     */
    protected function configureSingleHandler(Application $app, Writer $log)
    {
        //sets the path to custom app/log/single-xxxx-xx-xx.log file.
        $log->useFiles($app->storagePath() . '/logs/laravel.log', env('LOG_LEVEL', 'info'));
    }

    /**
     * Configure the Monolog handlers for the application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application $app
     * @param  \Illuminate\Log\Writer $log
     * @return void
     */
    protected function configureDailyHandler(Application $app, Writer $log)
    {
        //sets the path to custom app/log/daily-xxxx-xx-xx.log file.
        $log->useDailyFiles($app->storagePath() . '/logs/laravel.log', 5, env('LOG_LEVEL', 'info'));
    }

}