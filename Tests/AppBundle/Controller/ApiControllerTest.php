<?php

namespace Tests\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiControllerTest extends WebTestCase
{
    public function testNormal()
    {
        $client = static::createClient();
        $examplesDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' .
            DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
        putenv('KBC_EXAMPLES_DIR=' . $examplesDir);
        $client->request('GET', '/test-url');
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals(['message' => 'ok'], json_decode($client->getResponse()->getContent(), true));
        $client->request('GET', '/ignored-test-url');
        self::assertEquals(404, $client->getResponse()->getStatusCode());
        self::assertEquals(
            ['message' => 'Unknown request GET /ignored-test-url'],
            json_decode($client->getResponse()->getContent(), true)
        );
    }

    public function testInvalidDirectory()
    {
        $client = static::createClient();
        $examplesDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' .
            DIRECTORY_SEPARATOR . 'data-invalid' . DIRECTORY_SEPARATOR;
        putenv('KBC_EXAMPLES_DIR=' . $examplesDir);
        $client->request('GET', '/test-url');
        self::assertEquals(503, $client->getResponse()->getStatusCode());
        self::assertEquals(
            [
                'message' =>
                    'Error Multiple instances of request GET /test-url, ' .
                    'conflicting instances: data-01-example-call1 and data-02-example-call1'
            ],
            json_decode($client->getResponse()->getContent(), true)
        );
    }

    public function testPostWithBody()
    {
        $client = static::createClient();
        $examplesDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' .
            DIRECTORY_SEPARATOR . 'data-post' . DIRECTORY_SEPARATOR;
        putenv('KBC_EXAMPLES_DIR=' . $examplesDir);
        $client->request('POST', '/test-post', [], [], [], json_encode(["thisIs" => "correct"]));
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals(['message' => 'ok'], json_decode($client->getResponse()->getContent(), true));
        $client->request('POST', '/test-post', [], [], [], json_encode(["thisIs" => "incorrect"]));
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals(['message' => 'error'], json_decode($client->getResponse()->getContent(), true));
        $client->request('POST', '/test-post', [], [], [], json_encode(["something" => "else"]));
        self::assertEquals(404, $client->getResponse()->getStatusCode());
        self::assertEquals(
            ['message' => "Unknown request POST /test-post\r\n\r\n{\"something\":\"else\"}"],
            json_decode($client->getResponse()->getContent(), true)
        );
    }
}
