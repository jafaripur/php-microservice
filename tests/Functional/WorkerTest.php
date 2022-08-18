<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\AmqpConnection;
use Araz\MicroService\Processor;
use Araz\MicroService\Queue;
use Araz\MicroService\Tests\Functional\Processor\Worker\UserProfileAnalysisTestWorker;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{
    private ?Queue $queue = null;

    protected function setUp(): void
    {
        if (!$this->queue) {
            $this->queue = new Queue(
                'test-app',
                new AmqpConnection([
                    'dsn' => $_ENV['AMQP_DSN'],
                    'persisted' => false,
                ]),
                null,
                null,
                true,
                true,
                [
                    \Araz\MicroService\Tests\Functional\Consumer\Worker\ConsumerWorkerResult::class,
                ]
            );

            $this->queue->getConsumer()->consume(1);
        }
    }

    public function testQueueSendAndReceiveWorkerException()
    {
        try {
            $this->queue->getClient()->worker()
                ->setQueueName('service_worker')
                ->setJobName('user_profile_analysis')
                ->setData(['id' => 123456])
                ->setExpiration(500)
                ->setDelay(3)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Just one of $delay or $expiration can be set', $th->getMessage());
        }

        try {
            $this->queue->getClient()->worker()
                ->setQueueName('service_worker')
                ->setJobName('user_profile_analysis')
                ->setData(['id' => 123456])
                ->setPriority(300)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Priority accept between 0 and', $th->getMessage());
        }

        try {
            $this->queue->getClient()->worker()
                ->setQueueName('service_worker')
                ->setJobName('user_profile_analysis')
                ->setData(['id' => 123456])
                ->setPriority(-1)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Priority accept between 0 and', $th->getMessage());
        }

        try {
            $this->queue->getClient()->worker()
                ->setQueueName('service_worker')
                ->setJobName('user_profile_analysis')
                ->setData(['id' => 123456])
                ->setDelay(-1)
                ->send()
            ;
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Delay can not less than 0', $th->getMessage());
        }
    }

    public function testQueueSendAndReceiveWorker()
    {
        $data = ['id' => 123];

        $id = $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData($data)
            ->send()
        ;

        $this->queue->getConsumer()->consume(50);

        $this->assertEquals(UserProfileAnalysisTestWorker::$receivedData, [
            'id' => $id,
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);
    }

    public function testQueueSendAndReceiveWorkerPriority()
    {
        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123456, 'priority' => 4])
            ->setPriority(4)
            ->send()
        ;

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 1234, 'priority' => 5])
            ->setPriority(5)
            ->send()
        ;

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 1235, 'priority' => 1])
            ->setPriority(1)
            ->send()
        ;

        $id = $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123522, 'priority' => 0])
            ->setPriority(0)
            ->send()
        ;

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 1236, 'priority' => 2])
            ->setPriority(2)
            ->send()
        ;

        UserProfileAnalysisTestWorker::$receivedData = null;

        $this->queue->getConsumer()->consume(1000);

        $this->assertEquals(UserProfileAnalysisTestWorker::$receivedData, [
            'id' => $id,
            'data' => ['id' => 123522, 'priority' => 0],
            'process' => Processor::ACK,
            'beforeExecute' => ['id' => 123522, 'priority' => 0],
            'afterExecute' => ['id' => 123522, 'priority' => 0],
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);
    }

    public function testQueueSendAndReceiveWorkerExpire()
    {
        UserProfileAnalysisTestWorker::$receivedData = null;

        $data = ['id' => 123456, 'expire' => 2000];

        $id = $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData($data)
            ->setExpiration(2000)
            ->send()
        ;

        $this->queue->getConsumer()->consume(20);

        $this->assertEquals(UserProfileAnalysisTestWorker::$receivedData, [
            'id' => $id,
            'data' => $data,
            'process' => Processor::ACK,
            'beforeExecute' => $data,
            'afterExecute' => $data,
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);

        UserProfileAnalysisTestWorker::$receivedData = null;

        $data['id'] = 654321;
        $data['expire'] = 1;

        $id = $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData($data)
            ->setExpiration(2000)
            ->send()
        ;

        usleep(50 * 1000);

        $this->queue->getConsumer()->consume(20);

        $this->assertEquals(UserProfileAnalysisTestWorker::$receivedData, null);
    }

    public function testQueueSendAndReceiveWorkerDelay()
    {
        UserProfileAnalysisTestWorker::$receivedData = null;

        $id = $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123456])
            ->setDelay(120)
            ->send()
        ;

        $this->queue->getConsumer()->consume(50);
        $this->assertEquals(UserProfileAnalysisTestWorker::$receivedData, null);

        $this->queue->getConsumer()->consume(80);
        $this->assertEquals(UserProfileAnalysisTestWorker::$receivedData, [
            'id' => $id,
            'data' => ['id' => 123456],
            'process' => Processor::ACK,
            'beforeExecute' => ['id' => 123456],
            'afterExecute' => ['id' => 123456],
            'afterMessageAcknowledge' => Processor::ACK,
            'processorFinished' => Processor::ACK,
        ]);
    }
}
