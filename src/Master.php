<?php

namespace Archman\Whisper;

abstract class Master
{
    /**
     * @var array 数据结构
     *  [
     *      $workerID => [
     *          'pid' => xxx,
     *          'communicator' => \Archman\Whisper\Communicator,
     *          'info' => [],       // 自定义信息
     *      ],
     *      ...
     *  ]
     */
    protected $workers = [];

    abstract public function run();

    protected function fork(WorkerFactoryInterface $factory): string
    {
        $socketPair = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );
        if ($socketPair === false) {
            // TODO throw exception
        }

        $pid = pcntl_fork();

        if ($pid > 0) {
            fclose($socketPair[1]);
            unset($socketPair[1]);

            $communicator = new Communicator($socketPair[0]);
            $workerID = spl_object_hash($communicator);
            $this->workers[$workerID] = [
                'pid' => $pid,
                'communicator' => $communicator,
                'info' => [],
            ];
        } else if ($pid === 0) {
            fclose($socketPair[0]);
            unset($socketPair[0]);

            try {
                $communicator = new Communicator($socketPair[1]);
                $result = $factory->makeWorker($communicator)->run();
            } catch (\Throwable $e) {
                $result = -1;
            }
            exit($result);
        } else {
            // TODO throw exception
        }

        return $workerID;
    }
}