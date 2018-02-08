<?php


namespace Lomocoin\Mongodb\Tests\FunctionalTests;

use Lomocoin\Mongodb\Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function testConnectMongo()
    {
        $config = null;

        try {
            $config = $this->getBasicConfig();
        } catch (\Exception $exception) {
            $this->fail($exception->getMessage());
        }

        $this->assertNotNull($config);
    }
}