<?php namespace Pckg\Queue\Service;

use Carbon\Carbon;
use Pckg\Collection;
use Pckg\Database\Query\Raw;
use Pckg\Queue\Entity\Queue as QueueEntity;
use Pckg\Queue\Record\Queue as QueueRecord;

class Queue
{

    /**
     * @var QueueEntity
     */
    protected $queue;

    public function __construct(QueueEntity $queue)
    {
        $this->queue = $queue;
    }

    public function getTotalByStatusAndTime($status, $time)
    {
        return $this->queue->status($status)
                           ->inLast($time)
                           ->total();
    }

    public function getNext()
    {
        return $this->queue->future()->withLogs()->orderBy('execute_at ASC')->count()->all();
    }

    public function getCurrent()
    {
        return $this->queue->current()->withLogs()->orderBy('started_at DESC')->count()->all();;
    }

    public function getPrev()
    {
        return $this->queue->status(['finished', 'failed_permanently', 'skipped_unique'])->withLogs()->orderBy(
            'execute_at DESC'
        )->count()->limit(10)->all();
    }

    public function getStarted()
    {
        return $this->queue->status(['started'])->withLogs()->count()->all();
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
            function(QueueRecord $queue) use (&$times, &$statuses) {
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
     * @param       $command
     * @param array $data
     *
     * @return QueueRecord
     */
    public function create($command, $data = [])
    {
        $appName = config('pckg.queue.app', lcfirst(get_class(app())));
        $platformName = context()->getOrDefault('platformName');
        $path = path('root') . 'console';
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
                if (isset($val[0]) && isset($val[count($val) - 1])) {
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

        $queue = new QueueRecord(
            [
                'execute_at' => date('Y-m-d H:i:s'),
                'status'     => 'created',
                'command'    => 'php ' . $path .
                                ($appName ? ' ' . $appName : '') .
                                ($platformName ? ' ' . $platformName : '') .
                                ' ' . $command .
                                ($parameters ? ' ' . implode(' ', $parameters) : ''),
            ]
        );
        $queue->save();

        return $queue;
    }

}