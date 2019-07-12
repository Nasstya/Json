<?php

/**
 * Unit tests for MAChitgarha\Component\JSON class.
 *
 * Go to the project's root and run the tests in this way:
 * phpunit --bootstrap vendor/autoload.php tests/unit
 *
 * @see MAChitgarha\Component\JSON
 */
namespace MAChitgarha\UnitTest\JSON;

use PHPUnit\Framework\TestCase;
use MAChitgarha\Component\JSON;
use MAChitgarha\Exception\JSON\ScalarDataException;

class ScalarDataExceptionTest extends TestCase
{
    protected function setUp()
    {
        $this->expectException(ScalarDataException::class);
    }

    /** @dataProvider callMethodsWithIndexesProvider */
    public function testCallMethodsWithIndexes(JSON $json, string $funcName, array $args)
    {
        $json->$funcName(...$args);
    }

    /** @dataProvider methodsForCountableDataProvider */
    public function testMethodsDependOnCountableData(JSON $json, string $funcName, array $args = [])
    {
        $json->$funcName(...$args);
    }

    protected function prependJsonToEveryElement(JSON $json, array $array): array
    {
        foreach ($array as &$item) {
            array_unshift($item, $json);
        }
        return $array;
    }

    public function callMethodsWithIndexesProvider()
    {
        $json = clone new JSON("Ubuntu");
        $sampleIndex = "appName";
        $sampleValue = "Terminal";

        return $this->prependJsonToEveryElement($json, [
            ["get", [$sampleIndex]],
            ["set", [$sampleValue, $sampleIndex]],
            ["unset", [$sampleIndex]],
            ["isSet", [$sampleIndex]],
            ["isCountable", [$sampleIndex]]
        ]);
    }

    public function methodsForCountableDataProvider()
    {
        $json = clone new JSON(1400);
        $sampleValue = 1398;

        return $this->prependJsonToEveryElement($json, [
            ["count"],
            ["iterate"],
            ["forEach", [function () {}]],
            ["forEachRecursive", [function () {}]],
            ["push", [$sampleValue]],
            ["pop"],
            ["shift"],
            ["unshift", [$sampleValue]],
            ["getValues"],
            ["getKeys"],
            ["getRandomValue"],
            ["getRandomKey"],
            ["getRandomElement"],
            ["getRandomValues", [2]],
            ["getRandomKeys", [2]],
            ["getRandomSubset", [2]],
            ["mergeWith", [[]]],
            ["mergeRecursivelyWith", [[]]],
            ["difference", [[]]],
            ["filter"],
            ["flipValuesAndKeys"],
            ["reduce", [function () {}]],
            ["shuffle"],
            ["reverse"],
            ["fill", [0, 1, $sampleValue]]
        ]);
    }
}
