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
    public static function waitWaiting($seconds = 5)
    {
        while (count(static::$pids) > 0) {
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
                /**
                 * Wait for 5 seconds before next check.
                 */
                sleep($seconds);
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
    public static function fork(callable $fork, callable $name = null, callable $error = null, &$sockets = [])
    {
        /**
         * Create a communication socket chanel.
         */
        if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets) === false) {
            throw new \Exception('socket_create_pair() failed. Reason: ' . socket_strerror(socket_last_error()));
        }

        /**
         * Test msg.
         */
        $strone = 'String one';
        $strtwo = 'String two';

        /**
         * Try to fork process.
         */
        $pid = pcntl_fork();
        if ($pid == -1 || $pid) {
            /**
             * Failed or success, return to parent.
             */

            socket_close($sockets[0]);
            if (socket_write($sockets[1], $strone, strlen($strone)) === false) {
                echo "parent socket_write() failed. Reason: ".socket_strerror(socket_last_error($ary[1]));
            }
            if (socket_read($sockets[1], strlen($strtwo), PHP_BINARY_READ) == $strtwo) {
                echo "parent Recieved $strtwo\n";
            }
            socket_close($sockets[1]);

            return $pid;
        }

        /**
         * Allow child to communicate to parent.
         */
        $message = function($string) use (&$sockets, $strtwo, $strone){
            socket_close($sockets[1]);
            if (socket_write($sockets[0], $string, strlen($string)) === false) {
                echo "child socket_write() failed. Reason: " . socket_strerror(socket_last_error($ary[0]));
            }
            if (socket_read($sockets[0], strlen($strone), PHP_BINARY_READ) == $strone) {
                echo "child Recieved $strone\n";
            }
            socket_close($ary[0]);
        };

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

            /**
             * Execute forked code.
             */
            $fork($message);

            /**
             * How to communicate status to parent?
             */

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