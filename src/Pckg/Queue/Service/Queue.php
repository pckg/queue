<?php

namespace Pckg\Queue\Service;

use Carbon\Carbon;
use Pckg\Database\Query\Raw;
use Pckg\Parser\Driver\AbstractDriver;
use Pckg\Parser\Driver\Curl;
use Pckg\Parser\Driver\DriverInterface;
use Pckg\Parser\Driver\Selenium;
use Pckg\Queue\Entity\Queue as QueueEntity;
use Pckg\Queue\Record\Queue as QueueRecord;

class Queue
{

    const PRIORITY_LOW = 3;
    const PRIORITY_MEDIUM = 6;
    const PRIORITY_HIGH = 9;

    /**
     * @var QueueEntity
     */
    protected $queue;

    /**
     * @var string
     */
    protected $driverClass = RabbitMQ::class;

    /**
     * @var DriverInterface
     */
    protected $driver;

    public function __construct(QueueEntity $queue)
    {
        $this->queue = $queue;
        $this->driverClass = config('pckg.queue.driver', RabbitMQ::class);
    }

    public function getTotalByStatusAndTime($status, $time)
    {
        return $this->queue->status($status)
                           ->inLast($time)
                           ->total();
    }

    public function getNext()
    {
        return $this->queue->future()->withLogs()->orderBy('execute_at ASC')->count()->limit(50)->all();
    }

    public function getNextManual()
    {
        return $this->queue->status('manual')->withLogs()->orderBy('execute_at ASC')->count()->limit(50)->all();
    }

    public function getCurrent()
    {
        return $this->queue->current()->withLogs()->orderBy('started_at DESC')->count()->all();
    }

    public function getPrev()
    {
        return $this->queue->status(['finished', 'failed_permanently', 'skipped_unique'])->withLogs()->orderBy(
            'execute_at DESC'
        )->count()->limit(20)->all();
    }

    public function getStarted()
    {
        return $this->queue->status(['started'])->withLogs()->count()->limit(20)->all();
    }

    public function getWaiting()
    {
        return $this->queue->where('execute_at', date('Y-m-d H:i:s'), '<')
                           ->where(
                               Raw::raw(
                                   'waiting_id IS NULL OR waiting_id IN (SELECT id FROM queue WHERE status = \'finished\')'
                               )
                           )
                           ->status(['created', 'failed'])
                           ->all();
    }

    public function getChartData()
    {
        $minDate = date('Y-m-d', strtotime('-3 months'));
        $maxDate = date('Y-m-d', strtotime('+1 day'));

        $data = (new QueueEntity())
            ->select(
                [
                    'status',
                    'week'  => 'WEEKOFYEAR(created_at)',
                    'year'  => 'YEAR(created_at)',
                    'count' => 'COUNT(id)',
                ]
            )
            ->groupBy('WEEKOFYEAR(created_at), YEAR(year), status')
            ->where('created_at', $minDate, '>')
            ->all();

        $date = new Carbon($minDate);
        $times = [];
        /**
         * Prepare times.
         */
        while ($maxDate > $date) {
            $times[$date->year . '-' . $date->weekOfYear] = [];
            $date->addDays(7);
        }

        $statuses = [];
        $data->each(
            function (QueueRecord $queue) use (&$times, &$statuses) {
                $times[$queue->year . '-' . $queue->week][$queue->status] = $queue->count;
                $statuses[$queue->status][$queue->year . '-' . $queue->week] = $queue->count;
            }
        );

        $chart = [
            'labels'   => array_keys($times),
            'datasets' => [],
        ];

        $colors = [
            'finished'           => 'rgba(0, 255, 0, 0.33)',
            'failed_permanently' => 'rgba(255, 0, 0, 0.33)',
            'created'            => 'rgba(0, 0, 255, 0.33)',
            'skipped_unique'     => 'rgba(100, 100, 100, 0.33)',
            'skipped'            => 'rgba(200, 100, 100, 0.33)',
            'total'              => 'rgba(50, 50, 50, 0.33)',
        ];
        foreach ($statuses as $status => $statusTimes) {
            $dataset = [
                'label'           => $status,
                'data'            => [],
                'borderColor'     => $colors[$status],
                'backgroundColor' => 'transparent',
                'borderWidth'     => 1,
            ];
            foreach ($times as $time => $timeStatuses) {
                $dataset['data'][] = $statusTimes[$time] ?? 0;
            }
            $chart['datasets'][] = $dataset;
        }
        $dataset = [
            'label'           => 'total',
            'data'            => [],
            'borderColor'     => $colors['total'],
            'backgroundColor' => 'transparent',
            'borderWidth'     => 1,
        ];
        foreach ($times as $time => $statuses) {
            $total = 0;
            foreach ($statuses as $status) {
                $total += $status;
            }
            $dataset['data'][] = $total;
        }
        $chart['datasets'][] = $dataset;

        return $chart;
    }

    /**
     * @return RabbitMQ
     */
    protected function getRabbitMQ()
    {
        return resolve(RabbitMQ::class);
    }

    protected function getBeanstalkd()
    {
        /**
         * @phpstan-ignore-next-line
         */
        return resolve(Beanstalkd::class);
    }

    /**
     * @return mixed|object|DriverInterface|AbstractDriver|Curl|Selenium
     */
    public function getDriver()
    {
        if (!$this->driver) {
            $this->driver = resolve($this->driverClass);
        }

        return $this->driver;
    }

    public function setDriver(\Pckg\Queue\Service\DriverInterface $driver)
    {
        $this->driverClass = get_class($driver);
        $this->driver = $driver;

        return $this;
    }

    /**
     * Push job to general queue for jobs.
     *
     * @param string $command
     * @param array  $params
     *
     * @return QueueRecord
     */
    public function job(string $command, $params = [], $channel = Queue::class)
    {
        return $this->queue($channel, $command, $params);
    }

    /**
     * We will wrap actual command into queue task wrapper to have option of tracking process.
     * @param       $command
     * @param array $params
     */
    public function queue($channel, $command, $params = [], $options = [])
    {
        $message = !$params && is_array($command)
            ? json_encode($command)
            : (function () use ($command, $params) {
                $wrappedCommand = $this->getCommand($command, $params);

                /**
                 * Build JSON message.
                 */
                return json_encode(['command' => $wrappedCommand]);
            })();

        try {
            /**
             * Connect to channel.
             */
            $driver = $this->getDriver();
            $driver->makeQueue($channel);

            /**
             * Send to queue.
             */
            $driver->queueMessage($message, $channel, $options);

            return true;
        } catch (\Throwable $e) {
            /**
             * When RabbitMQ queueing fails, try with database?
             */
            error_log(exception($e));
            if (config('pckg.queue.fallback')) {
                return $this->create($command, $params, 'created', 'queue');
            }

            throw $e;
        }
    }

    /**
     * @param       $command
     * @param array $data
     *
     * @return QueueRecord
     */
    public function create($command, $data = [], $status = 'created', $type = 'command')
    {
        $command = $this->getCommand($command, $data);
        $queue = QueueRecord::create(
            [
                'execute_at' => date('Y-m-d H:i:s'),
                'status'     => config('pckg.queue.enabled') ? $status : 'disabled',
                'command'    => $command,
                'type'       => $type,
            ]
        );

        return $queue;
    }

    protected function getCommand($command, $data, $entrypoint = 'console')
    {
        if (strpos($command, 'php ') === 0 || strpos($command, ':') === false) {
            return $command;
        }

        $appName = config('pckg.queue.app', lcfirst(get_class(app())));
        $path = path('root') . $entrypoint;
        $parameters = $this->getParametersFromData($data);

        return 'php ' . $path . ($appName ? ' ' . $appName : '') . ' ' . $command .
            ($parameters ? ' ' . implode(' ', $parameters) : '');
    }

    protected function getParametersFromData($data)
    {
        $parameters = [];
        foreach ($data as $key => $val) {
            if (is_int($key)) {
                /**
                 * We're passing attribute, option without value or already encoded part of command.
                 */
                $parameters[] = $val;
            } elseif (is_array($val)) {
                /**
                 * Array of values should be handled differently.
                 */
                if (isArrayList($val)) {
                    foreach ($val as $subval) {
                        $parameters[] = '--' . $key . '=' . escapeshellarg($subval);
                    }
                } else {
                    $parameters[] = '--' . $key . '=' . escapeshellarg(json_encode($val));
                }
            } elseif (is_object($val)) {
                /**
                 * Serialize object.
                 */
                $parameters[] = '--' . $key . '=' . escapeshellarg(base64_encode(serialize($val)));
            } else {
                /**
                 * We simply escape all other values.
                 */
                $parameters[] = '--' . $key . '=' . escapeshellarg($val);
            }
        }

        return $parameters;
    }
}
