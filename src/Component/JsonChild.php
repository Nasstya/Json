<?php
/**
 * JsonChild class file.
 *
 * @author Mohammad Amin Chitgarha <machitgarha@outlook.com>
 * @see https://github.com/MAChitgarha/Json
 * @see https://packagist.org/packages/machitgarha/json
 */

namespace MAChitgarha\Component;

/**
 * Holds an element of the parent data by-reference and make operations on it.
 *
 * @see https://github.com/MAChitgarha/Json/wiki
 */
class JsonChild extends Json
{
    /**
     * @param mixed $dataPointer The element to be held, by-reference.
     * @param array $properties Properties from the caller class to be set inside this class.
     */
    public function __construct(&$dataPointer, array $properties)
    {
        foreach ($properties as $propName => $value) {
            $this->$propName = $value;
        }

        $this->data = &$dataPointer;
    }
}
