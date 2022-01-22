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
            $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], null, 500, 3);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Just one of $delay or $expiration can be set', $th->getMessage());
        }

        try {
            $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], 300);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Priority accept between 0 and', $th->getMessage());
        }

        try {
            $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], -1);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Priority accept between 0 and', $th->getMessage());
        }

        try {
            $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], null, null, -1);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(\LogicException::class, $th);
            $this->assertStringContainsString('Delay can not less than 0', $th->getMessage());
        }
    }

    public function testQueueSendAndReceiveWorker()
    {
        $data = ['id' => 123];

        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', $data);
        $this->queue->getConsumer()->consume(1);

        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, $data);
    }

    public function testQueueSendAndReceiveWorkerPriority()
    {
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], 4);
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 1234], 4);
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 1235], 1);
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123522], 0);
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 1236], 2);

        $this->queue->getConsumer()->consume(100);

        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, ['id' => 123522]);
    }

    public function testQueueSendAndReceiveWorkerExpire()
    {
        UserProfileAnalysisWorker::$receivedData = null;
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], null, 1000);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, ['id' => 123456]);

        UserProfileAnalysisWorker::$receivedData = null;
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], null, 1);
        usleep(2000);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, null);
    }

    public function testQueueSendAndReceiveWorkerDelay()
    {
        UserProfileAnalysisWorker::$receivedData = null;
        $this->queue->getSender()->worker('service_worker', 'user_profile_analysis', ['id' => 123456], null, null, 100);
        $this->queue->getConsumer()->consume(1);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, null);
        $this->queue->getConsumer()->consume(120);
        $this->assertEquals(UserProfileAnalysisWorker::$receivedData, ['id' => 123456]);
    }
}
