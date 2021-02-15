<?php
/**
 * Author: Drunk
 * Date: 2017-3-13 11:01
 */

namespace dce\project\view\engine;

use dce\project\view\ViewHttpApi;
use SimpleXMLElement;

abstract class ViewHttpXml extends ViewHttpApi {
    /** @inheritDoc */
    protected function setContentType(): void {
        @$this->httpRequest->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(): string {
        return self::arrayToXml($this->getAllAssignedStatus());
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
            if (is_array($value)) {
                if (is_numeric($key)){
                    $key = "item-{$key}";
                }
                $childXmlElement = $xmlElement->addChild($key);
                self::arrayToXml($value, $childXmlElement);
            } else {
                $xmlElement->addChild($key, htmlspecialchars($value));
            }
        }
        return $isTopLevel ? $xmlElement->asXML(): null;
    }
}
