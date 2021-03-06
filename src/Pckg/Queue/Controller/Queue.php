<?php

namespace Pckg\Queue\Controller;

use Exception;
use Pckg\Framework\Controller;
use Pckg\Queue\Service\Queue as QueueService;

class Queue extends Controller
{

    /**
     * @var QueueService
     */
    protected $queueService;

    public function __construct(QueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    public function getIndexAction()
    {
        return view(
            'queue/index',
            [
                'manualQueue'  => $this->queueService->getNextManual(),
                'nextQueue'    => $this->queueService->getNext(),
                'currentQueue' => $this->queueService->getCurrent(),
                'prevQueue'    => $this->queueService->getPrev(),
                'startedQueue' => $this->queueService->getStarted(),
                'stat'         => [
                    'successful24h'        => $this->queueService->getTotalByStatusAndTime('finished', '1 day'),
                    'failedPermanently24h' => $this->queueService->getTotalByStatusAndTime(
                        ['failed_permanently', 'failed'],
                        '1 day'
                    ),
                    'currentlyRunning'     => $this->queueService->getTotalByStatusAndTime('running', '1 day'),
                ],
                'chart'        => $this->queueService->getChartData(),
            ]
        );
    }

    public function getAjaxAction($type)
    {
        if ($type == 'next') {
            return $this->queueService->getNext();
        } else if ($type == 'current') {
            return $this->queueService->getCurrent();
        } else if ($type == 'prev') {
            return $this->queueService->getPrev();
        } else if ($type == 'started') {
            return $this->queueService->getStarted();
        } else if ($type == 'manual') {
            return $this->queueService->getNextManual();
        }

        throw new Exception('Type ' . $type . 'not implemented');
    }
}
