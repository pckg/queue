<?php namespace Pckg\Queue\Service\Cron;

class Fork
{

    /**
     * @var array
     */
    protected static $pids = [];

    /**
     * @param $pid
     */
    public static function waitFor($pid)
    {
        static::$pids[$pid] = $pid;
    }

    /**
     *
     */
    public static function waitWaiting()
    {
        while (count(static::$pids) > 0) {
            /**
             * Wait for 5 seconds before next check.
             */
            sleep(5);

            foreach (static::$pids as $pid) {
                /**
                 * Get process status code.
                 */
                $res = pcntl_waitpid($pid, $status, WNOHANG);

                if (!($res > 0)) {
                    /**
                     * Process is still running, check another process.
                     */
                    continue;
                }

                /**
                 * Process has ended, remove it from watch list.
                 */
                unset(static::$pids[$pid]);
            }

            /**
             * We have processes to check, retry.
             */
            if (static::$pids) {
                continue;
            }

            /**
             * All waiting processes has finished, quit.
             */
            break;
        }
    }

    /**
     * @param callable      $fork
     * @param callable|null $name
     * @param callable|null $error
     *
     * @return bool|int
     */
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