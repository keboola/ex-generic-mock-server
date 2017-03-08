<?php

namespace AppBundle\Controller;

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
        $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($stream));
    }

    private function getBaseDirectory()
    {
        if (getenv('KBC_SAMPLES_DIR')) {
            $directory = getenv('KBC_SAMPLES_DIR');
        } else {
            $directory = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' .
                DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'samples';
        }
        return $directory;
    }

    private function loadData()
    {
        $requests = [];
        $finder = new Finder();
        $finder->depth(2);
        $finder->files()->name('/^(request|response)$/');
        /** @var SplFileInfo $file */
        $requests[] = [];
        foreach ($finder->in($this->getBaseDirectory()) as $file) {
            $request = null;
            $response = null;
            $fragments = explode(DIRECTORY_SEPARATOR, $file->getRelativePath());
            $requestId = $fragments[count($fragments) - 2] . '-' . $fragments[count($fragments) - 1];
            if ($file->getFilename() == 'request') {
                $request = file_get_contents($file->getPathname());
                if (isset($requests[$request])) {
                    throw new InvalidArgumentException(
                        "Multiple instances of request $request, conflicting instances: " .
                        $requests[$request] . " and " . $requestId
                    );
                }
                $requests[$requestId]['request'] = $request;
                $requests[$request] = $requestId;
            } elseif ($file->getFilename() == 'response') {
                $requests[$requestId]['response'] = file_get_contents($file->getPathname());
            }
            $requests[$requestId]['test'] = $fragments[count($fragments) - 2];
        }
        return $requests;
    }

    public function indexAction(Request $request)
    {
        try {
            $this->init();
            $this->logger->info("Triggered index action");
            $uri = substr($request->getRequestUri(), strlen($request->getBaseUrl()));
            $requestId = $request->getMethod() . ' ' . $uri;
            $samples = $this->loadData();
            $this->logger->info("Loaded " . count($samples) . "samples.");
            foreach ($samples as $sampleId => $sample) {
                if ($sample['request'] == $requestId) {
                    $response = new Response();
                    $response->headers->add(['Content-type' => 'application/json']);
                    $response->setContent($sample['response']);
                    return $response;
                } else {
                    $this->logger->info("Request $requestId does not match sample $sampleId " . $sample['request']);
                }
            }
            $response = new Response();
            $response->setContent("Unknown request $requestId");
            return $response;
        } catch (\Exception $e) {
            $response = new Response();
            $response->setContent("Error " . $e->getMessage());
            $response->setStatusCode(503);
            return $response;
        }
    }
}
