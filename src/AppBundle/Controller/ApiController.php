<?php

namespace AppBundle\Controller;

use Monolog\Handler\StreamHandler;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    private function init()
    {
        $this->logger = $this->container->get('logger');
        $stream = fopen('php://stderr', 'r');
        $this->logger->pushHandler(new StreamHandler($stream));
    }

    private function getBaseDirectory()
    {
        if (getenv('KBC_EXAMPLES_DIR')) {
            $directory = getenv('KBC_EXAMPLES_DIR');
        } else {
            $directory = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' .
                DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'examples';
        }
        return $directory;
    }

    private function loadData()
    {
        $finder = new Finder();
        $finder->depth('> 0');
        $finder->sortByName();
        $finder->files()->name('*.request');
        /** @var SplFileInfo $file */
        $requests = [];
        $requestIndex = [];
        foreach ($finder->in($this->getBaseDirectory()) as $file) {
            $baseName = $file->getBasename('.request');
            $requestFile = $file->getPath() . DIRECTORY_SEPARATOR . $baseName . '.request';
            $responseFile = $file->getPath() . DIRECTORY_SEPARATOR . $baseName . '.response';
            $requestHeaderFile = $requestFile . 'headers';
            $responseHeaderFile = $responseFile . 'headers';
            $requestId = strtr($file->getRelativePath(), '/\\', '--') . '-'. $baseName;
            $requestHeaders = '';
            if (file_exists($requestHeaderFile)) {
                $requestHeaders = file_get_contents($requestHeaderFile);
            }
            $responseHeaders = '';
            if (file_exists($responseHeaderFile)) {
                $responseHeaders = file_get_contents($responseHeaderFile);
            }
            $requestData = file_get_contents($requestFile);
            $newRequestId = $requestData . $requestHeaders;
            if (isset($requestIndex[$newRequestId])) {
                throw new InvalidArgumentException(
                    "Multiple instances of request $newRequestId, conflicting instances: " .
                    $requestIndex[$newRequestId] . " and " . $requestId
                );
            }
            $requests[$requestId]['request'] = $requestData;
            $requests[$requestId]['response'] = file_get_contents($responseFile);
            $requests[$requestId]['requestHeaders'] = $requestHeaders ? explode("\n", $requestHeaders) : [];
            $requests[$requestId]['responseHeaders'] = $responseHeaders ? explode("\n", $responseHeaders) : [];
            $requests[$requestId]['example'] = $baseName;
            $requestIndex[$newRequestId] = $requestId;
        }
        return $requests;
    }

    public function indexAction(Request $request)
    {
        try {
            $this->init();
            $this->logger->info("Triggered index action");
            $uri = substr($request->getRequestUri(), strlen($request->getBaseUrl()));
            $requestId = trim($request->getMethod() . ' ' . $uri . "\r\n\r\n" . $request->getContent());
            $headers = $request->headers->all();
            $samples = $this->loadData();
            $this->logger->info("Loaded " . count($samples) . "samples.");
            foreach ($samples as $sampleId => $sample) {
                if ($sample['request'] == $requestId) {
                    $valid = true;
                    foreach ($sample['requestHeaders'] as $header) {
                        $name = strtolower(trim(substr($header, 0, strpos($header, ':'))));
                        $value = trim(substr($header, strpos($header, ':') + 1));
                        if (!isset($headers[$name]) || $headers[$name][0] != $value) {
                            $this->logger->info("Request headers " . var_export($headers, true) .
                                " do not match sample $sampleId headers " .
                                var_export($sample['requestHeaders'], true));
                            $valid = false;
                            break;
                        }
                    }
                    if ($valid) {
                        $response = new Response();
                        $response->headers->add(['Content-type' => 'application/json']);
                        $response->setContent($sample['response']);
                        return $response;
                    }
                } else {
                    $this->logger->info("Request " . var_export($requestId, true) .
                        " does not match sample $sampleId " . var_export($sample['request'], true));
                }
            }
            $response = new Response();
            $response->setStatusCode(404);
            $response->setContent(json_encode(["message" => "Unknown request $requestId"]));
            return $response;
        } catch (\Exception $e) {
            $response = new Response();
            $response->setContent(json_encode(["message" => "Error " . $e->getMessage()]));
            $response->setStatusCode(503);
            return $response;
        }
    }
}
