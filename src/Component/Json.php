<?php
/**
 * Json class file.
 *
 * @author Mohammad Amin Chitgarha <machitgarha@outlook.com>
 * @see https://github.com/MAChitgarha/Json
 * @see https://packagist.org/packages/machitgarha/json
 */

namespace MAChitgarha\Component;

use MAChitgarha\Json\Exception\Exception;
use MAChitgarha\Json\Exception\InvalidArgumentException;
use MAChitgarha\Json\Exception\InvalidJsonException;
use MAChitgarha\Json\Exception\UncountableValueException;
use MAChitgarha\Json\Exception\JsonException;
use MAChitgarha\Json\Exception\OverflowException;
use MAChitgarha\Json\Option\JsonOpt;
use MAChitgarha\Json\Option\Indexing;
use MAChitgarha\Json\Option\Merge;
use MAChitgarha\Json\Option\DoOpt;

/**
 * JSON data handler.
 *
 * @see https://github.com/MAChitgarha/Json/wiki
 */
class Json implements \ArrayAccess, \Countable
{
    /**
     * @var mixed Data parsed in the constructor. It should be everything but a resource type, but
     * it may. Setting it to a resource might lead to errors/exceptions.
     */
    protected $data;

    /** @var int Options passed to the constructor. */
    protected $options = 0;

    /** @var bool {@see JsonOpt::DECODE_ALWAYS} */
    protected $jsonDecodeAlways = false;

    /** @var int {@see self::setJsonRecursionDepth()} */
    protected $jsonRecursionDepth = 512;

    /** @var callable {@see self::setRandomizationFunction()}. */
    protected $randomizationFunction = "mt_rand";

    /**
     * @param mixed $data The data. It must be either countable or scalar (i.e. it must not be
     * resource).
     * @param int $options A combination of JsonOpt::* constants.
     * @throws InvalidArgumentException Using JsonOpt::AS_JSON option and passing a non-string data.
     * @throws InvalidJsonException Using JsonOpt::AS_JSON option and passing invalid JSON string.
     * @throws InvalidArgumentException If data is a resource.
     */
    public function __construct($data = [], int $options = 0)
    {
        // Setting options
        $this->setOptions($options);
        $asJson = (bool)($options & JsonOpt::AS_JSON);

        if (is_string($data)) {
            list($isJsonValid, $decodedData) = $this->validateStringAsJson($data, true);

            if ($isJsonValid) {
                $this->data = $decodedData;
                return;
            }

            // Data contains invalid JSON
            if ($asJson) {
                throw new InvalidJsonException();
            }
        } elseif ($asJson) {
            throw new InvalidArgumentException("Data must be string if using JsonOpt::AS_JSON");
        }

        if (is_resource($data)) {
            throw new InvalidArgumentException("Data must not be a resource");
        }

        $this->data = $data;
    }

    /**
     * Creates a new instance of the class.
     *
     * @see self::__construct()
     * @return self
     */
    public static function new($data = [], int $options = 0)
    {
        return new self($data, $options);
    }

    /**
     * Resets all options.
     *
     * @param int $options A combination of JsonOpt::* constants.
     * @return self
     */
    public function setOptions(int $options = 0)
    {
        $this->options = $options;

        $this->jsonDecodeAlways = (bool)($options & JsonOpt::DECODE_ALWAYS);

        return $this;
    }

    /**
     * Sets an option.
     *
     * @param int $option
     * @return self
     */
    public function addOption(int $option)
    {
        $this->setOptions($this->options | $option);
        return $this;
    }

    /**
     * Unsets an option.
     *
     * @param int $option
     * @return self
     */
    public function removeOption(int $option)
    {
        $this->setOptions($this->options & ~$option);
        return $this;
    }

    /**
     * Tells whether an option is set or not.
     *
     * @param int $option
     * @return bool
     */
    public function isOptionSet(int $option): bool
    {
        return ($this->options & $option) === $option;
    }

    /**
     * Sets recursion depth when encoding/decoding JSON strings.
     *
     * @param int $depth
     * @return self
     * @throws InvalidArgumentException If $depth is less than 1.
     */
    public function setJsonRecursionDepth(int $depth): self
    {
        if ($depth < 1) {
            throw new InvalidArgumentException("Depth must be positive");
        }

        $this->jsonRecursionDepth = $depth;
        return $this;
    }

    /**
     * Sets the randomization function, used when a random integer is needed.
     *
     * @param callable $function It accepts two arguments:
     * 1. The minimum value to be returned,
     * 2. The maximum value to be returned;
     * It must return an integer. You can also use built-in functions like mt_rand().
     * @return self
     * @throws InvalidArgumentException If $function returns an unexpected value.
     */
    public function setRandomizationFunction(callable $function): self
    {
        // For sure, the function is callable, because it is defined as callable
        $this->randomizationFunction = $function;

        $randomValue = $function(0, 1);
        if ($randomValue !== 0 && $randomValue !== 1) {
            throw new InvalidArgumentException("Return value of \$function is invalid");
        }

        return $this;
    }

    /**
     * Decodes a string as a valid JSON if {@see JsonOpt::DECODE_ALWAYS} is set.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function decodeJsonIfNeeded($value)
    {
        if ($this->jsonDecodeAlways && is_string($value)) {
            // Validating JSON string
            try {
                $value = self::decodeJson($value, true);
            } catch (JsonException $e) {
                // Just a try, not a force
            }
        }
        return $value;
    }

    /**
     * Converts all objects inside a value (if exists) to arrays, recursively.
     *
     * @param mixed $value
     * @return mixed
     * @see https://stackoverflow.com/a/54131002/4215651 Thanks to this.
     */
    protected function normalize($value)
    {
        if (is_object($value) || is_array($value)) {
            foreach ((array)($value) as &$item) {
                // Recursion
                $item = $this->normalize($item);
            }
        }
        return $value;
    }

    /**
     * Converts an array or an object to a recursive object.
     *
     * @param mixed $value
     * @param bool $forceObject Whether to convert indexed arrays to objects or not.
     * @return object|array
     */
    protected function toObject($value, bool $forceObject = false)
    {
        return $this->decodeJsonUseProps(
            $this->encodeToJsonUseProps($value, $forceObject ? JSON_FORCE_OBJECT : 0)
        );
    }

    /**
     * Returns a random value by calling {@see self::$randomizationFunction}.
     *
     * @param int $min
     * @param int $max
     * @return int
     * @see self::setRandomizationFunction()
     */
    protected function randomInt(int $min, int $max): int
    {
        return ($this->randomizationFunction)($min, $max);
    }

    /**
     * Determines if data is an array or an object.
     *
     * @param mixed $data
     * @return int 0 if is not any of them, 1 if it is an array and -1 if it is an object.
     */
    protected static function isArrayOrObject($data): int
    {
        return is_array($data) <=> is_object($data);
    }

    /**
     * Tells whether data type is scalar or not.
     * A scalar type, can be an integer, a string, a float, a boolean, or NULL (in PHP itself, NULL
     * is not a scalar value, but here it is considered as scalar).
     *
     * @param mixed $data
     * @return bool
     */
    protected static function isScalar($data): bool
    {
        return is_scalar($data) || $data === null;
    }

    /**
     * Encodes a value as JSON.
     * See json_encode() documentation for more details.
     *
     * @param mixed $value
     * @param int $options
     * @param int $depth
     * @return string
     * @throws JsonException
     */
    public static function encodeToJson($value, int $options = 0, int $depth = 512): string
    {
        $encodedData = json_encode($value, $options, $depth);
        self::handleJsonErrors(json_last_error());
        return $encodedData;
    }

    /**
     * Decodes a JSON string.
     * See json_decode() documentation for more details.
     *
     * @param string $value
     * @param bool $asArray To return the result as an array or not (i.e. an object).
     * @param int $depth
     * @param int $options
     * @return mixed
     * @throws JsonException
     */
    public static function decodeJson(
        string $value,
        bool $asArray = false,
        int $depth = 512,
        int $options = 0
    ) {
        $decodedData = json_decode($value, $asArray, $depth, $options);
        self::handleJsonErrors(json_last_error());
        return $decodedData;
    }

    /**
     * Handles JSON errors and throw exceptions, if needed.
     *
     * @param integer $jsonErrorStat The return value of json_last_error().
     * @return void
     */
    protected static function handleJsonErrors(int $jsonErrorStat)
    {
        switch ($jsonErrorStat) {
            case JSON_ERROR_NONE:
                return;

            case JSON_ERROR_DEPTH:
                $message = "Maximum stack depth exceeded";
                break;

            case JSON_ERROR_STATE_MISMATCH:
            case JSON_ERROR_SYNTAX:
                $message = "Invalid or malformed JSON";
                break;

            case JSON_ERROR_CTRL_CHAR:
            case JSON_ERROR_UTF8:
            case JSON_ERROR_UTF16:
                $message = "Malformed characters, possibly incorrectly encoded JSON";
                break;

            case JSON_ERROR_INF_OR_NAN:
                $message = "NAN and INF cannot be encoded";
                break;

            case JSON_ERROR_INVALID_PROPERTY_NAME:
                $message = "Found an invalid property name";
                break;

            case JSON_ERROR_UNSUPPORTED_TYPE:
                $message = "A value cannot be encoded, possibly a resource";
                break;

            default:
                $message = "Unknown JSON error";
                break;
        }

        throw new JsonException($message, $jsonErrorStat);
    }

    /**
     * Validates a string as a JSON string.
     *
     * @param string $value
     * @param bool $assoc To return decoded JSON as associative array or not.
     * @return array An array of two values:
     * [0]: Is the string a valid JSON or not,
     * [1]: The decoded JSON string, or null if either the string is not a valid JSON or an
     * error occurred while decoding it.
     */
    protected static function validateStringAsJson(string $value, bool $assoc = false): array
    {
        try {
            $decodedValue = self::decodeJson($value, $assoc);
        } catch (JsonException $e) {
            return [false, null];
        }
        return [true, $decodedValue];
    }

    /**
     * Checks if a string is a valid JSON or not.
     *
     * @param string $value
     * @return bool
     */
    public static function isJsonValid(string $value): bool
    {
        return self::validateStringAsJson($value)[0];
    }

    /**
     * Uses needed properties as arguments/options for {@see self::encodeToJson()}.
     *
     * @param mixed $value
     * @param int $options
     * @return string
     */
    protected function encodeToJsonUseProps($value, int $options = 0): string
    {
        return self::encodeToJson($value, $options, $this->jsonRecursionDepth);
    }

    /**
     * Uses needed properties as arguments/options for {@see self::decodeJson()}.
     *
     * @param string $value
     * @param bool $asArray
     * @return mixed
     */
    protected function decodeJsonUseProps(string $value, bool $asArray = false)
    {
        return self::decodeJson($value, $asArray, $this->jsonRecursionDepth);
    }

    /**
     * Finds a specific element using $keys and calls a function on it.
     *
     * @param array $keys The keys to be followed recursively.
     * @param array $data The data. It must be a recursive array or you may encounter errors.
     * @return array{0:array|mixed,1:array|array[],2:mixed}|array{0:array|array[],1:null,2:null}
     * @see Json::do()
     */
    protected function &findElementRecursive(
        array $keys,
        &$data,
        bool $forceCountableValue = false,
        int $indexingType = Indexing::STRICT
    ): array {
        $keysCount = count($keys);

        if ($keysCount > 1) {
            // Get the current key, and remove it from keys array
            $curKey = array_shift($keys);

            if (isset($data[$curKey])) {
                $childData = &$data[$curKey];

                if (is_object($childData)) {
                    $childData = (array)($childData);
                }

                if (!is_array($childData) && $indexingType >= Indexing::SAFE) {
                    throw new UncountableValueException("The key '$curKey' has uncountable value");
                }
            } else {
                if ($indexingType === Indexing::STRICT) {
                    throw new Exception("The key '$curKey' does not exist");
                }
                $data[$curKey] = [];
            }

            // Recursion
            return $this->findElementRecursive(
                $keys,
                $data[$curKey],
                $forceCountableValue,
                $indexingType
            );
        }

        // End of recursion
        else {
            if ($keysCount === 1) {
                $lastKey = $keys[0];

                if (isset($data[$lastKey])) {
                    $childData = &$data[$lastKey];

                    if (is_object($childData)) {
                        $childData = (array)($childData);
                    }
                    if ($forceCountableValue && !is_array($childData)) {
                        throw new UncountableValueException("Cannot use the method on uncountable");
                    }
                } else {
                    if ($indexingType === Indexing::STRICT) {
                        throw new Exception("The key '$lastKey' does not exist");
                    }
                    $data[$lastKey] = null;
                }

                $returnValue = [
                    &$data[$lastKey],
                    &$data,
                    $lastKey,
                ];
            }
            // If $keysCount is 0
            else {
                if ($forceCountableValue && self::isScalar($data)) {
                    throw new UncountableValueException("Cannot use the method on uncountable");
                }

                $returnValue = [
                    &$data,
                    null,
                    null,
                ];
            }

            /*
             * For sure, $returnValue[1] is an array or null. Just we must make sure that
             * $returnValue[0] is an array, for further operations. So:
             */
            $value = &$returnValue[0];
            $value = self::isScalar($value) ? $value : $this->normalize($value);

            return $returnValue;
        }
    }

    /**
     * Extract index to an array of keys using a delimiter.
     *
     * @param ?string|int $index
     * @param string $delimiter
     * @return array
     *
     * @since 0.3.2 Add escaping delimiters, i.e., using delimiters as the part of keys by escaping
     * them using a backslash.
     */
    protected function extractIndex($index = null, string $delimiter = "."): array
    {
        if ($index === null) {
            return [];
        }

        if ($index === "") {
            return [""];
        }

        if (!is_string($index) && !is_int($index)) {
            throw new InvalidArgumentException("Index must be either a string or an integer");
        }

        $replacement = "¬";
        $escapedDelimiter = "\\$delimiter";

        // Replace the escaped delimiter with a less-using character
        $index = str_replace($escapedDelimiter, $replacement, (string)($index));

        $keys = explode($delimiter, $index);

        // Set the escaped delimiters
        foreach ($keys as &$key) {
            $key = str_replace($replacement, $delimiter, $key);
        }

        return $keys;
    }

    /**
     * Finds a specific element using $index and calls a function on it.
     *
     * @param ?callable $function It accepts the following argument(s):
     * 1. The element's value; might be gotten by-reference.
     * 2. The parent element (that is an array); might be gotten by-reference.
     * 3. The last key in the index; might be used to access the element (using the parent element).
     * From within the callable, you can yield as many values as you want, and/or return a value.
     * The return type of the method will be exactly the return type of this callable. Note that if
     * $index is null, the first argument will be the only passing argument.
     * @param ?string|int $index The index of the element to be found, and it's extracted as keys.
     * Pass null if you want to get the data root inside the callback.
     * @param bool $forceCountableValue Force the value be operated to be a countable one, so, the
     * element (i.e. first argument) passing to $function will be an array.
     * @param int $indexingType One of the Indexing::* constants.
     * @param int $options A combination of DoOpt::* constants.
     * @return mixed The return value of $function, whether is a generator or not.F
     * @throws Exception When reaching a key that does not exist and $strictIndexing is true.
     * @throws UncountableValueException If reaching a key that contains an uncountable value.
     * @throws UncountableValueException When reaching an uncountable element and
     * $forceCountableValue is set to true.
     * @throws UncountableValueException If data is scalar and $forceCountableValue is set to true.
     * @todo Remove the at sign operator.
     */
    public function &do(
        callable $function = null,
        $index = null,
        bool $forceCountableValue = false,
        int $indexingType = Indexing::STRICT,
        int $options = 0
    ) {
        $data = &$this->data;
        if (is_object($data)) {
            $data = (array)($data);
        }

        if (self::isScalar($data) && $index !== null) {
            throw new UncountableValueException("Cannot use indexing on uncountable");
        }

        // Set options
        $keepIndex = $options & (bool)(DoOpt::KEEP_INDEX);

        // On debugging, pay attention to the following @ operator!
        @$returnValueReference = &$function(...$this->findElementRecursive(
            $keepIndex ? (array)($index) : $this->extractIndex($index),
            $data,
            $forceCountableValue,
            $indexingType
        ));
        return $returnValueReference;
    }

    /**
     * Gets an element inside data.
     *
     * @param string $index
     * @param int $indexingType One of the Indexing::* constants.
     * @return JsonChild
     */
    public function index(string $index, int $indexingType = Indexing::FREE): JsonChild
    {
        return new JsonChild($this->do(function &(&$element) {
            return $element;
        }, $index, false, $indexingType), get_object_vars($this));
    }

    /**
     * Returns an element's value. All countable values will be returned as arrays.
     *
     * @param ?string|int $index
     * @return mixed Returns null if the index cannot be found.
     */
    public function get($index = null)
    {
        try {
            return $this->do(function ($value) {
                return $value;
            }, $index);
        } catch (UncountableValueException $e) {
            throw $e;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns an element's value as a JSON string.
     *
     * @param int $options The options, like JSON_PRETTY_PRINT. {@link
     * http://php.net/json.constants}
     * @param ?string|int $index
     * @return string
     */
    public function getAsJson(int $options = 0, $index = null): string
    {
        return $this->do(function ($value) use ($options) {
            return $this->encodeToJsonUseProps($value, $options);
        }, $index);
    }

    public function __toString(): string
    {
        return $this->getAsJson();
    }

    /**
     * Returns an element's value as a recursive object.
     *
     * @param bool $indexedArraysToObjects Whether to convert indexed arrays to objects or not.
     * @param ?string|int $index
     * @return object|array
     */
    public function getAsObject(bool $indexedArraysToObjects = false, $index = null)
    {
        return $this->do(function ($value) use ($indexedArraysToObjects) {
            return $this->toObject($value, $indexedArraysToObjects);
        }, $index);
    }

    /**
     * Returns an element by-reference.
     *
     * @param ?string|int $index
     * @return mixed
     */
    public function &getByReference($index = null)
    {
        return $this->do(function &(&$element) {
            return $element;
        }, $index);
    }

    /**
     * Sets an element to a value.
     *
     * @param mixed $value
     * @param ?string|int $index Pass null if data is scalar.
     * @return self
     */
    public function set($value, $index = null): self
    {
        $this->do(function (&$element) use ($value) {
            $element = $this->decodeJsonIfNeeded($value);
        }, $index, false, Indexing::FREE);
        return $this;
    }

    /**
     * Determines if an element exists or not.
     *
     * @param ?string|int $index
     * @return bool Whether the element is set or not. A null value will be considered as not set.
     */
    public function isSet($index = null): bool
    {
        return $this->get($index) !== null;
    }

    /**
     * Unsets an element.
     *
     * @param ?string|int $index
     * @return self
     */
    public function unset($index = null): self
    {
        $this->do(function (&$element, &$parent, $key) {
            // If element is the data itself
            if ($parent === null) {
                $element = null;
            } else {
                unset($parent[$key]);
            }
        }, $index);
        return $this;
    }

    private function throwExceptionArrayAccessOnScalar()
    {
        if (self::isScalar($this->data)) {
            throw new UncountableValueException("Array access is not possible on scalar data");
        }
    }

    public function &offsetGet($index)
    {
        $this->throwExceptionArrayAccessOnScalar();
        return $this->data[$index];
    }

    public function offsetSet($index, $value)
    {
        $this->throwExceptionArrayAccessOnScalar();
        $this->data[$index] = $this->decodeJsonIfNeeded($value);
    }

    public function offsetExists($index): bool
    {
        $this->throwExceptionArrayAccessOnScalar();
        return isset($this->data[$index]);
    }

    public function offsetUnset($index)
    {
        $this->throwExceptionArrayAccessOnScalar();
        unset($this->data[$index]);
    }

    /**
     * Replaces data with a new one.
     *
     * @param mixed $newData
     * @return mixed The previous data.
     */
    public function exchange($newData)
    {
        $prevData = $this->get();
        $this->__construct($newData, $this->options);
        return $prevData;
    }

    /**
     * Determines whether an element is countable or not.
     *
     * @param ?string|int $index
     * @return bool
     */
    public function isCountable($index = null): bool
    {
        try {
            return $this->do(function () {
                return true;
            }, $index, true);
        } catch (UncountableValueException $e) {
            return false;
        }
    }

    /**
     * Returns the numbers elements in a countable.
     *
     * @param ?string|int $index
     * @return int
     */
    public function count($index = null): int
    {
        return $this->do(function ($element) {
            return count($element);
        }, $index, true);
    }

    /**
     * Casts the data to an array (if it is scalar).
     *
     * @param ?string|int $index
     * @return self
     */
    public function toCountable($index = null): self
    {
        $this->do(function (&$value) {
            $value = (array)($value);
        }, $index);
        return $this;
    }

    /**
     * Iterates a countable.
     *
     * @param ?string|int $index
     * @return \Generator
     */
    public function &iterate($index = null): \Generator
    {
        return $this->do(function &(array &$array) {
            foreach ($array as $key => &$value) {
                yield $key => $value;

                $value = $this->decodeJsonIfNeeded($value);
            }
        }, $index, true);
    }

    /**
     * Iterates a countable, but returns each iteration value as a new Json class.
     *
     * @param ?string|int $index
     * @return \Generator
     */
    public function iterateAsJson($index = null): \Generator
    {
        $objectVars = get_object_vars($this);

        return $this->do(function (array &$array) use ($objectVars) {
            foreach ($array as $key => &$value) {
                yield $key => new JsonChild($value, $objectVars);
            }
        }, $index, true);
    }

    /**
     * Calls a function on each member of a countable and returns its first non-null return value.
     *
     * @param callable $function The function to be called on each member until returning a
     * non-null value, that accepts the following arguments:
     * 1. The element's value; might be gotten by-reference.
     * 2. The element's key.
     * 3. The parent element; might be gotten by-reference.
     * @param ?string|int $index
     * @return mixed
     */
    public function forEach(callable $function, $index = null)
    {
        return $this->do(function (array &$array) use ($function) {
            foreach ($array as $key => &$value) {
                $result = $function($value, $key, $array);
                // Returning the first non-null value
                if ($result !== null) {
                    return $result;
                }
            }
        }, $index, true);
    }

    /**
     * Applies a function recursively to every member of a countable.
     *
     * @param callable $function The function to be called on each member, accepts three arguments:
     * 1. The element's value, might be gotten by-reference.
     * 2. The element's key.
     * @param ?string|int $index
     * @return self
     */
    public function forEachRecursive(callable $function, $index = null): self
    {
        $this->do(function (array &$array) use ($function) {
            $this->walkRecursive($array, $function);
        }, $index, true);
        return $this;
    }

    /**
     * Iterates a countable recursively and applies a function on each member of it.
     *
     * @param array $array
     * @param callable $function {@see self::forEachRecursive()}
     * @return self
     */
    protected function walkRecursive(array &$array, callable $function): self
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                // Recursion
                $this->walkRecursive($value, $function);
            } else {
                $function($value, $key);
            }
        }
        return $this;
    }

    /**
     * Appends a value to the end of a countable.
     *
     * @param mixed $value
     * @param ?string|int $index
     * @return self
     */
    public function push($value, $index = null): self
    {
        $this->do(function (array &$array) use ($value) {
            array_push($array, $this->decodeJsonIfNeeded($value));
        }, $index, true);
        return $this;
    }

    /**
     * Pops the last value of a countable and returns it.
     *
     * @param ?string|int $index
     * @return mixed The removed element's value.
     */
    public function pop($index = null)
    {
        return $this->do(function (array &$array) {
            return array_pop($array);
        }, $index, true);
    }

    /**
     * Removes the first element of a countable.
     *
     * @param ?string|int $index Pass null if you want to remove an element from the data root.
     * @return mixed The removed element's value.
     */
    public function shift($index = null)
    {
        return $this->do(function (array $array) {
            return array_shift($array);
        }, $index, true);
    }

    /**
     * Prepends a value to the beginning of a countable.
     *
     * @param ?string|int $index
     * @return self
     */
    public function unshift($value, $index = null): self
    {
        $this->do(function (array &$array) use ($value) {
            array_unshift($array, $value);
        }, $index, true);
        return $this;
    }

    /**
     * Returns all values of a countable.
     *
     * @param ?string|int $index
     * @return array
     */
    public function getValues($index = null): array
    {
        return $this->do(function (array $array) {
            return array_values($array);
        }, $index, true);
    }

    /**
     * Returns all keys of a countable.
     *
     * @param ?string|int $index
     * @return array
     */
    public function getKeys($index = null): array
    {
        return $this->do(function (array $array) {
            return array_keys($array);
        }, $index, true);
    }

    /**
     * Returns a random value from a countable.
     *
     * @param ?string|int $index
     * @return mixed
     */
    public function getRandomValue($index = null)
    {
        return $this->do(function (array $array) {
            return array_values($array)[$this->randomInt(0, count($array) - 1)];
        }, $index, true);
    }

    /**
     * Returns a random key from a countable.
     *
     * @param ?string|int $index
     * @return int|string
     */
    public function getRandomKey($index = null)
    {
        return $this->do(function (array $array) {
            return array_keys($array)[$this->randomInt(0, count($array) - 1)];
        }, $index, true);
    }

    /**
     * Returns a random key/value pair from a countable. The value will be returned by-reference.
     *
     * @param ?string|int $index
     * @return array{0:string|int,1:mixed} An array containing the element's key and the
     * element's value, respectively. You can get it as a list: list($key, $value).
     */
    public function getRandomElement($index = null): array
    {
        return $this->do(function (array &$array) {
            $randomKey = array_keys($array)[$this->randomInt(0, count($array) - 1)];
            $returnValue = [
                $randomKey,
                &$array[$randomKey]
            ];
            return $returnValue;
        }, $index, true);
    }

    /**
     * Returns some random values from a countable.
     *
     * @param int $count
     * @param ?string|int $index
     * @return array
     */
    public function getRandomValues(int $count, $index = null): array
    {
        return $this->do(function (array $array) use ($count) {
            $maxArrayIndex = count($array) - 1;

            if ($count > $maxArrayIndex) {
                throw new OverflowException("Count of values must not reach the countable length");
            }

            $arrayKeys = array_keys($array);

            $randomValues = [];
            while (count($randomValues) < $count) {
                $randomValues[] = $array[$arrayKeys[$this->randomInt(0, $maxArrayIndex)]];
            }

            return $randomValues;
        }, $index, true);
    }

    /**
     * Returns some random keys from a countable.
     *
     * @param integer $count
     * @param ?string|int $index
     * @return array
     */
    public function getRandomKeys(int $count, $index = null): array
    {
        return $this->do(function (array $array) use ($count) {
            $maxArrayIndex = count($array) - 1;

            if ($count > $maxArrayIndex) {
                throw new OverflowException("Count of keys must not reach the countable length");
            }

            $arrayKeys = array_keys($array);

            $randomKeys = [];
            while (count($randomKeys) < $count) {
                $randomKeys[] = $arrayKeys[$this->randomInt(0, $maxArrayIndex)];
            }

            return $randomKeys;
        }, $index, true);
    }

    /**
     * Returns a random subset of a countable.
     *
     * @param integer $size Subset's length.
     * @param ?string|int $index
     * @return array
     */
    public function getRandomSubset(int $size, $index = null): array
    {
        return $this->do(function (array &$array) use ($size) {
            $maxArrayIndex = count($array) - 1;

            if ($size > $maxArrayIndex) {
                throw new OverflowException("Subset size must be lower than countable size");
            }

            $arrayKeys = array_keys($array);

            $randomSubset = [];
            while (count($randomSubset) < $size) {
                $randomKey = $arrayKeys[$this->randomInt(0, $maxArrayIndex)];
                $randomSubset[$randomKey] = $array[$randomKey];
            }

            return $randomSubset;
        }, $index, true);
    }

    /**
     * Merges a countable with another countable.
     *
     * @param mixed $value It will be treated as an array, and must not be a resource.
     * @param int $options A combination of Merge::* constants.
     * @param ?string|int $index
     * @return self
     */
    public function mergeWith($value, int $options = 0, $index = null): self
    {
        // Extracting options
        $reverseOrder = $options & Merge::KEEP_DEFAULT;

        $this->do(function (array &$array) use ($value, $reverseOrder) {
            $value = (array)($this->decodeJsonIfNeeded($value));
            if ($reverseOrder) {
                $array = array_merge($value, $array);
            } else {
                $array = array_merge($array, $value);
            }
        }, $index, true);
        return $this;
    }

    /**
     * Merges a countable in data with another countable data.
     *
     * @param mixed $value It will be treated as an array, and must not be a resource.
     * @param int $options Currently, no options are available.
     * @param ?string|int $index
     * @return self
     */
    public function mergeRecursivelyWith($value, int $options = 0, $index = null): self
    {
        $this->do(function (array &$array) use ($value) {
            $value = (array)($this->decodeJsonIfNeeded($value));
            $array = array_merge_recursive($array, $value);
        }, $index, true);
        return $this;
    }

    /**
     * Removes intersections of a countable with another countable.
     *
     * @param mixed $value Comparing value. It will be treated as an array, and must not be a
     * resource.
     * @param bool $compareKeys To compute intersections in the keys or in the values.
     * @param ?string|int $index
     * @return self
     * @see https://gist.github.com/nunoveloso/1992851 Thanks to this.
     */
    public function difference(
        $value,
        bool $compareKeys = false,
        $index = null
    ): self {
        $diff = function (array $array1, array $array2) {
            $diff = array();

            foreach ($array1 as $value) {
                $diff[$value] = 1;
            }
            foreach ($array2 as $value) {
                unset($diff[$value]);
            }

            return array_keys($diff);
        };

        $diffKey = function (array $array1, array $array2) {
            $diff = array();

            foreach ($array1 as $key => $value) {
                $diff[$key] = $value;
            }
            foreach ($array2 as $key => $value) {
                unset($diff[$key]);
            }

            return $diff;
        };

        $this->do(function (array &$array) use ($value, $compareKeys, $diff, $diffKey) {
            $value = (array)($this->decodeJsonIfNeeded($value));
            $array = $compareKeys ? $diff($array, $value) : $diffKey($array, $value);
        }, $index, true);
        return $this;
    }

    /**
     * Filters a countable using a callable.
     *
     * @param ?callable $function The filtering function. It should return a boolean, or any
     * non-false values is considered as true (i.e. any value that loosely equals false is
     * considered as false, such as 0). It accepts two arguments:
     * 1. The element's value (not passing by-reference).
     * 2. The element's key.
     * The default function removes all null values.
     * @param ?string|int $index
     * @return self
     */
    public function filter(callable $function = null, $index = null): self
    {
        if ($function === null) {
            $function = function ($value) {
                return $value !== null;
            };
        }

        $this->do(function (array &$array) use ($function) {
            $filteredArray = [];
            foreach ($array as $key => $value) {
                if ($function($value, $key)) { // @phan-suppress-current-line PhanParamTooMany
                    $filteredArray[$key] = $value;
                }
            }
            $array = $filteredArray;
        }, $index, true);
        return $this;
    }

    /**
     * Flips values and keys in a countable.
     *
     * @param ?string|int $index
     * @return self
     */
    public function flipValuesAndKeys($index = null): self
    {
        $this->do(function (array &$array) {
            $array = array_flip($array);
        }, $index, true);
        return $this;
    }

    /**
     * Shuffles values of a countable.
     *
     * @param ?string|int $index
     * @return self
     * @see https://stackoverflow.com/a/32035692/4215651 Thanks to this.
     */
    public function shuffle($index = null): self
    {
        $this->do(function (array &$array) {
            $arrayKeys = array_keys($array);
            $shuffledArray = [];
            $arrayIndexLength = count($array) - 1;

            while (count($shuffledArray) <= $arrayIndexLength) {
                $randomKey = $arrayKeys[($this->randomizationFunction)(0, $arrayIndexLength)];
                $shuffledArray[$randomKey] = $array[$randomKey];
            }

            $array = $shuffledArray;
        }, $index, true);
        return $this;
    }

    /**
     * Reverses the order of elements in a countable.
     *
     * @param ?string|int $index
     * @return self
     */
    public function reverse($index = null): self
    {
        $this->do(function (array &$array) {
            $array = array_reverse($array);
        }, $index, true);
        return $this;
    }

    /**
     * Sets an element to an array filled with a value.
     *
     * @param int $startIndex
     * @param int $length
     * @param mixed $value
     * @param ?string|int $index
     * @return self
     */
    public function fill(int $startIndex, int $length, $value, $index = null): self
    {
        $this->do(function (&$element) use ($startIndex, $length, $value) {
            $element = array_fill($startIndex, $length, $value);
        }, $index, false, Indexing::FREE);
        return $this;
    }

    /**
     * Returns the first key of a countable.
     *
     * @param ?string|int $index
     * @return string|int
     */
    public function getFirstKey($index = null)
    {
        return $this->do(function (array $array) {
            foreach ($array as $key => $value) {
                return $key;
            }
        }, $index, true);
    }

    /**
     * Returns the last key of a countable.
     *
     * @param ?string|int $index
     * @return string|int
     * @see https://stackoverflow.com/a/7478419/4215651 Thanks to this.
     */
    public function getLastKey($index = null)
    {
        return $this->do(function (array $array) {
            return key(array_slice($array, -1, 1, true));
        }, $index, true);
    }
}
