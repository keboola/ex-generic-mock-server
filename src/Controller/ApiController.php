<?php

namespace App\Controller;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    private function getBaseDirectory()
    {
        if (getenv('KBC_EXAMPLES_DIR')) {
            $directory = getenv('KBC_EXAMPLES_DIR');
        } else {
            $directory = __DIR__ . DIRECTORY_SEPARATOR . '..' .
                DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data';
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
            $requestHeaderFile = $requestFile . 'Headers';
            $responseHeaderFile = $responseFile . 'Headers';
            $responseCodeFile = $responseFile . 'Code';
            $requestId = strtr($file->getRelativePath(), '/\\', '--') . '-'. $baseName;
            $requestHeaders = '';
            if (file_exists($requestHeaderFile)) {
                $requestHeaders = file_get_contents($requestHeaderFile);
            }
            $responseHeaders = '';
            if (file_exists($responseHeaderFile)) {
                $responseHeaders = file_get_contents($responseHeaderFile);
            }
            $responseCode = '';
            if (file_exists($responseCodeFile)) {
                $responseCode = file_get_contents($responseCodeFile);
            }
            $requestData = file_get_contents($requestFile);
            $newRequestId = $requestData . $requestHeaders;
            if (isset($requestIndex[$newRequestId])) {
                throw new InvalidArgumentException(
                    "Multiple instances of request $newRequestId, conflicting instances: " .
                    $requestIndex[$newRequestId] . " (id: " . $newRequestId . " and " . $requestId .
                    "(" . $requestData . ' Headers:' . $requestHeaders . ")"
                );
            }
            $requests[$requestId]['request'] = $requestData;
            $requests[$requestId]['response'] = $responseFile;
            $requests[$requestId]['requestHeaders'] = $requestHeaders ? explode("\n", $requestHeaders) : [];
            $requests[$requestId]['responseHeaders'] = $responseHeaders ? explode("\n", $responseHeaders) : [];
            $requests[$requestId]['responseCode'] = $responseCode ?: null;
            $requests[$requestId]['example'] = $baseName;
            $requestIndex[$newRequestId] = $requestId;
        }
        return $requests;
    }

    public function index(Request $request, LoggerInterface $logger)
    {
        try {
            $uri = substr($request->getRequestUri(), strlen($request->getBaseUrl()));
            $requestId = trim($request->getMethod() . ' ' . $uri . "\r\n\r\n" . $request->getContent());
            $headers = $request->headers->all();
            $samples = $this->loadData();
            $logger->info("Loaded " . count($samples) . "samples.");
            foreach ($samples as $sampleId => $sample) {
                if ($sample['request'] == $requestId) {
                    $valid = true;
                    foreach ($sample['requestHeaders'] as $header) {
                        $name = strtolower(trim(substr($header, 0, strpos($header, ':'))));
                        if ($name == '') { // skip empty header
                            continue;
                        }
                        $value = trim(substr($header, strpos($header, ':') + 1));
                        if (!isset($headers[$name]) || $headers[$name][0] != $value) {
                            $logger->info("Request headers " . var_export($headers, true) .
                                " do not match sample $sampleId headers " .
                                var_export($sample['requestHeaders'], true));
                            $valid = false;
                            break;
                        }
                    }
                    if ($valid) {
                        $response = new Response();
                        $response->setContent(file_get_contents($sample['response']));
                        if ($sample['responseCode']) {
                            $response->setStatusCode($sample['responseCode']);
                        } else {
                            $response->setStatusCode(200);
                        }
                        if ($sample['responseHeaders']) {
                            foreach ($sample['responseHeaders'] as $header) {
                                $name = strtolower(trim(substr($header, 0, strpos($header, ':'))));
                                $value = trim(substr($header, strpos($header, ':') + 1));
                                $response->headers->add([$name => $value]);
                            }
                        } else {
                            $response->headers->add(['Content-type' => 'application/json']);
                        }
                        $logger->info("mem " . memory_get_usage(true) .  " peak: " . memory_get_peak_usage(true));
                        return $response;
                    }
                } else {
                    $logger->debug("Request " . var_export($requestId, true) .
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
            $response->setStatusCode(500);
            return $response;
        }
    }
}
