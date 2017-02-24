<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{


    const NUMBER_OF_ITEMS = 4;

    private function getObject($type, $id, $parentType, $parentId)
    {
        $ret = [
            'id' => $id,
            'type' => $type,
            'name' => 'some ' . $type . ' name',
            $type . 'description' => 'some Description'
        ];
        if ($parentType) {
            $ret['parentType'] = $parentType;
        }
        if ($parentId) {
            $ret['parentId'] = $parentId;
        }
        $ret['random' . rand(10, 20)] = 'some random key';
        return $ret;
    }


    private function generateResponse(array $parts)
    {
        $last = $parts[count($parts) - 1];
        $list = filter_var($last, FILTER_VALIDATE_INT) === false;
        if ($list) {
            if (count($parts) > 2) {
                $parentId = $parts[count($parts) - 2];
                $parentType = $parts[count($parts) - 3];
            } else {
                $parentId = null;
                $parentType = null;
            }
            $items = [];
            for ($i = 0; $i < self::NUMBER_OF_ITEMS; $i++) {
                $oid = str_pad($i, ((count($parts) - 1) / 2) + 1, '0', STR_PAD_LEFT);
                $items[] = $this->getObject($last, $oid, $parentType, $parentId);
            }
            return [
                $last => $items,
            ];
        } else {
            if (count($parts) > 3) {
                $parentId = $parts[count($parts) - 3];
                $parentType = $parts[count($parts) - 4];
            } else {
                $parentId = null;
                $parentType = null;
            }
            $object = $this->getObject($parts[count($parts) - 2], $last, $parentType, $parentId);
            return $object;
        }
    }


    public function indexAction(Request $request)
    {
        $this->loadData();
        $path = trim($request->getPathInfo(), '/');
        $parts = explode('/', $path);
        $response = new Response();
        $response->headers->add(['Content-type' => 'application/json']);
        $response->setContent(json_encode($this->generateResponse($parts)));
        return $response;
    }

}