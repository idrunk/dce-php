<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/3/23 23:23
 */

namespace dce\project\render;

use dce\project\Controller;
use dce\project\request\RawRequest;
use SimpleXMLElement;

class XmlRenderer extends Renderer {
    /** @inheritDoc */
    protected function setContentType(RawRequest $rawRequest): void {
        @$rawRequest->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(Controller $controller, mixed $data): string {
        return self::arrayToXml(false === $data ? $controller->getAllAssignedStatus() : $data);
    }

    /**
     * 简单数组转XML
     * @param array $data
     * @param SimpleXMLElement|null $xmlElement
     * @return string|null
     */
    private static function arrayToXml(array $data, SimpleXMLElement|null $xmlElement = null): string|null {
        $isTopLevel = false;
        if (null === $xmlElement) {
            $isTopLevel = true;
            $xmlElement = new SimpleXMLElement('<root></root>');
        }
        foreach ($data as $key => $value) {
            if (is_int($key)){
                $key = "item";
            }
            if (is_array($value)) {
                $childXmlElement = $xmlElement->addChild($key);
                self::arrayToXml($value, $childXmlElement);
            } else {
                $xmlElement->addChild($key, htmlspecialchars($value));
            }
        }
        return $isTopLevel ? $xmlElement->asXML(): null;
    }
}