<?php

namespace Araz\MicroService\Tests\Functional;

use Araz\MicroService\Queue;
use Araz\MicroService\Tests\Functional\Processor\Worker\UserProfileAnalysisWorker;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{
    private ?Queue $queue = null;

    protected function setUp(): void
    {
        if (!$this->queue) {
            $this->queue = new Queue(
                'test-app',
                [
                    'dsn' => $_ENV['AMQP_DSN'],
                    'persisted' => false,
                ],
                null,
                null,
                true,
                true,
                [
                    \Araz\MicroService\Tests\Functional\Consumer\ConsumerWorker::class,
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
                ->send();
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
                ->send();
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
                ->send();
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
                ->send();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Delay can not less than 0', $th->getMessage());
        }
    }

    public function testQueueSendAndReceiveWorker()
    {
        $data = ['id' => 123];

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData($data)
            ->send();

        $this->queue->getConsumer()->consume(1);

        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, $data);
    }

    public function testQueueSendAndReceiveWorkerPriority()
    {
        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123456])
            ->setPriority(4)
            ->send();

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 1234])
            ->setPriority(4)
            ->send();

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 1235])
            ->setPriority(1)
            ->send();

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123522])
            ->setPriority(0)
            ->send();

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 1236])
            ->setPriority(2)
            ->send();

        $this->queue->getConsumer()->consume(100);

        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, ['id' => 123522]);
    }

    public function testQueueSendAndReceiveWorkerExpire()
    {
        UserProfileAnalysisWorker::$receivedData = null;
        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123456])
            ->setExpiration(1000)
            ->send();

        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, ['id' => 123456]);

        UserProfileAnalysisWorker::$receivedData = null;
        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123456])
            ->setExpiration(1)
            ->send();

        usleep(2000);

        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, null);
    }

    public function testQueueSendAndReceiveWorkerDelay()
    {
        UserProfileAnalysisWorker::$receivedData = null;

        $this->queue->getClient()->worker()
            ->setQueueName('service_worker')
            ->setJobName('user_profile_analysis')
            ->setData(['id' => 123456])
            ->setDelay(100)
            ->send();

        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, null);
        $this->queue->getConsumer()->consume(120);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, ['id' => 123456]);
    }
}
