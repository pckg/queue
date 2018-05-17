<?php namespace Pckg\Queue\Service\Cron;

class Fork
{

    public static function fork(callable $fork, callable $name = null, callable $error = null)
    {
        $pid = pcntl_fork();
        if ($pid == -1 || $pid) {
            return $pid;
        }

        /**
         * First, change process name.
         */
        // child
        try {
            if ($name) {
                $title = $name();
                if (!cli_set_process_title($title)) {
                    /**
                     * This is crucial for counting number of running instances.
                     */
                    exit(2);
                }
            }

            /**
             * __wakeup :)
             */
            trigger('forked');

            $fork();

            exit(0);
        } catch (Throwable $e) {
            if ($error) {
                $error($e);
                return false;
            }

            /**
             * @T00D00 - log error?
             */
            echo exception($e);
            exit(1);
        }
    }

}