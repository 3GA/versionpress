<?php

namespace VersionPress\Utils\Serialization;

use Nette\Utils\Strings;
use VersionPress\Utils\StringUtils;

class SerializedDataToIniConverter {

    private $serializedMarker;
    private $index = 0;
    private $value;


    public function __construct($serializedMarker) {
        $this->serializedMarker = $serializedMarker;
    }

    public function toIniLines($key, $serializedData) {
        $this->value = $serializedData;
        $parsingResult = self::parseSerializedString();
        $iniLines = self::convertParsingResultToIni($key, $parsingResult);
        $this->index = 0;
        $this->value = null;

        // Add marker
        $iniLines[0] = StringUtils::replaceFirst(' = ', " = {$this->serializedMarker} ", $iniLines[0]);

        return $iniLines;
    }

    /**
     * Restores the PHP-serialized value from INI.
     * For examples see the tests.
     *
     * @param string $key The original top-level key.
     * @param string[] $lines Lines related to the $key. Hierarchical structures are saved as multiple lines.
     * @return string Original result of PHP serialization.
     */
    public function fromIniLines($key, $lines) {
        $value = substr($lines[$key], strlen($this->serializedMarker) + 1); // + space
        unset($lines[$key]);

        return self::convertValueToSerializedString($value, $lines);
    }

    /**
     * Transforms PHP primitives, arrays, objects etc. to INI. Complex structures like arrays and objects
     * can take multiple lines (every scalar value takes one line).
     *
     * @param string $key Key used in INI.
     * @param mixed $value Value to serialize
     * @return array
     */
    private function parseSerializedString() {
        $type = $this->value[$this->index];
        $this->index += 2; // <type>:

        switch ($type) {
            case 's':
                $length = intval(self::substr($this->value, $this->index, strpos($this->value, ':', $this->index)));
                $this->index += strlen($length) + 2; // :"
                $str = substr($this->value, $this->index, $length);
                $this->index += strlen($str) + 2; // ";

                return ['type' => 'string', 'value' => $str];
            case 'i':
            case 'd':
                $number = self::substr($this->value, $this->index, strpos($this->value, ';', $this->index));
                $this->index += strlen($number) + 1; // ;
                return ['type' => $type === 'i' ? 'int' : 'double', 'value' => $number];

            case 'b':
                $strVal = self::substr($this->value, $this->index, strpos($this->value, ';', $this->index));
                $this->index += 2; // <0|1>;
                return ['type' => 'boolean', 'value' => $strVal === '1'];
            case 'a':
                $length = intval(self::substr($this->value, $this->index, strpos($this->value, ':', $this->index)));
                $this->index += strlen($length) + 2; // :{

                $subItems = [];
                for ($i = 0; $i < $length; $i++) {
                    $key = $this->parseSerializedString()['value'];
                    $value = $this->parseSerializedString();

                    $subItems[$key] = $value;
                }

                $this->index += 1; // }

                return ['type' => 'array', 'value' => $subItems];
            case 'O':
                $classNameLength = intval(self::substr($this->value, $this->index, strpos($this->value, ':', $this->index)));
                $this->index += strlen($classNameLength) + 2; // :"
                $className = substr($this->value, $this->index, $classNameLength);
                $this->index += $classNameLength + 2; // ":
                $attributeCount = intval(self::substr($this->value, $this->index, strpos($this->value, ':', $this->index)));
                $this->index += strlen($attributeCount) + 2; // :{

                $attribute = [];
                for ($i = 0; $i < $attributeCount; $i++) {
                    $attributeName = $this->parseSerializedString()['value'];

                    $attributeName = str_replace("\0*\0", '*', $attributeName);
                    $attributeName = str_replace("\0{$className}\0", '-', $attributeName);

                    $attributeValue = $this->parseSerializedString();

                    $attribute[$attributeName] = $attributeValue;
                }

                $this->index += 1; // }

                return ['type' => 'object', 'class' => $className, 'value' => $attribute];
            case 'N':
                return ['type' => 'null'];
            case 'r':
            case 'R':
                $number = self::substr($this->value, $this->index, strpos($this->value, ';', $this->index));
                $this->index += strlen($number) + 1; // ;

                return ['type' => $type === 'r' ? 'pointer' : 'reference', 'value' => $number];
            default:
                return [];
        }
    }

    public static function convertParsingResultToIni($key, $parsingResult) {
        $type = $parsingResult['type'];

        switch ($type) {
            case 'string':
            case 'int':
            case 'double':
                return [self::banan($key, null, $parsingResult['value'])];
            case 'boolean':
                return [self::banan($key, $type, $parsingResult['value'])];
            case 'array':
                $lines = [self::banan($key, $type)];
                foreach ($parsingResult['value'] as $subKey => $subItem) {
                    $subKey = self::primitiveToEscapedString($subKey);
                    $lines = array_merge($lines, self::convertParsingResultToIni("{$key}[$subKey]", $subItem));
                }
                return $lines;
            case 'object':
                $lines = [self::banan($key, $parsingResult['class'])];
                foreach ($parsingResult['value'] as $subKey => $subItem) {
                    $subKey = self::primitiveToEscapedString($subKey);
                    $lines = array_merge($lines, self::convertParsingResultToIni("{$key}[$subKey]", $subItem));
                }
                return $lines;
            case 'null':
                return [self::banan($key, $type)];
            case 'pointer':
                return [self::banan($key, $type, $parsingResult['value'])];
            case 'reference':
                return [self::banan($key, $type, $parsingResult['value'])];
        }
    }

    public static function banan($key, $type = null, $value = null) {
        $parts = [$key, '='];

        if ($type !== null) {
            $parts[] = "<$type>";
        }

        if ($value !== null) {
            $parts[] = self::primitiveToEscapedString($value);
        }

        return join(' ', $parts);
    }

    public static function substr($str, $from, $to) {
        return substr($str, $from, $to - $from);
    }

    private static function primitiveToEscapedString($value) {
        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return '"' . str_replace('"', '\"', $value) . '"';
    }

    /**
     * Converts single value (string, number, array, object, ...) to PHP-like serialized string.
     * Takes the top-level value + lines related to it.
     * It's called recursively for hierarchical structures (array, object).
     *
     * @param $value
     * @param array $relatedKeys
     * @return string
     */
    private static function convertValueToSerializedString($value, $relatedKeys = []) {
        $type = null; // string or number

        // https://regex101.com/r/gJ1oF2/1
        if (preg_match('/^<([\w\d\\\\]+)> ?(.*)/', $value, $matches)) {
            $type = $matches[1]; // detect type and value from eg. `<boolean> false`
            $value = $matches[2];
        }

        if ($type === null && is_numeric($value)) {
            return (Strings::contains($value, '.') ? 'd' : 'i') . ':' . $value . ';';
        }

        if ($type === 'boolean') {
            return 'b:' . ($value === 'false' ? 0 : 1) . ';';
        }

        if ($type === 'array') {
            return self::convertArrayToSerializedString($relatedKeys);
        }

        if (class_exists($type)) {
            return self::convertObjectToSerializedString($type, $relatedKeys);
        }

        if ($type === 'null') {
            return 'N;';
        }

        if ($type === 'reference') {
            return 'R:' . $value . ';';
        }

        if ($type === 'pointer') {
            return 'r:' . $value . ';';
        }

        if (Strings::startsWith($value, '"')) { // plain serialized strings are in quotes because of `<<<serialized>>> "string"`
            $value = preg_replace('/^"(.*)"$/', '$1', $value);
        }

        return 's:' . strlen($value) . ':' . self::primitiveToEscapedString($value) . ';';
    }

    /**
     * Converts array items saved in INI to PHP-like serialized string.
     *
     * @param $relatedKeys
     * @return string
     */
    private static function convertArrayToSerializedString($relatedKeys) {
        $subItems = self::getSubItems($relatedKeys);
        return 'a:' . count($subItems) . ':{' . join('', $subItems) . '}';
    }

    /**
     * Converts object saved in INI to PHP-like serialized string.
     * Protected fields are prefixed with "*" in INI. PHP serialization saves them with prefix \0*\0 (where \0 is a NULL byte).
     * Private fields are prefixed with "-". PHP saves them with prefix \0<full class name>\0.
     *
     * @param $type
     * @param $relatedKeys
     * @return string
     */
    private static function convertObjectToSerializedString($type, $relatedKeys) {
        $subItems = self::getSubItems($relatedKeys, function ($subkey) use ($type) {
            if (strpos($subkey, '*') === 1) {
                return "\"\0*\0" . substr($subkey, 2);
            }

            if (strpos($subkey, '-') === 1) {
                return "\"\0{$type}\0" . substr($subkey, 2);
            }

            return $subkey;
        });

        return 'O:' . strlen($type) . ':"' . $type . '":' . count($subItems) . ':{' . join('', $subItems) . '}';
    }

    /**
     * Takes lines of INI representing array items or class fields and returns them as PHP-like serialized string.
     *
     * Finds original key and value at every line  (eg. line `[ 'some_data[0]' => 'some value' ]` contains key `0`
     * and value `some value`) and converts them to the PHP-like serialized string (in this case `i:0;s:10:"some value";`.
     *
     * @param $relatedKeys
     * @param callable|null $subkeyTransformFn
     * @return array
     */
    private static function getSubItems($relatedKeys, $subkeyTransformFn = null) {
        $items = [];
        foreach ($relatedKeys as $relatedKey => $valueOfRelatedKey) {

            $indexAfterFirstOpeningBracket = strpos($relatedKey, '[') + 1;
            $indexOfFirstClosingBracket = strpos($relatedKey, ']');
            $keyLength = $indexOfFirstClosingBracket - $indexAfterFirstOpeningBracket;

            $subkey = substr($relatedKey, $indexAfterFirstOpeningBracket, $keyLength);

            if (is_callable($subkeyTransformFn)) {
                $subkey = $subkeyTransformFn($subkey);
            }

            if (strpos($relatedKey, '[', $indexOfFirstClosingBracket) === false) {
                $relatedKeysOfSubItem = self::findRelatedKeys($relatedKeys, $relatedKey);
                $items[] = self::convertValueToSerializedString($subkey) . self::convertValueToSerializedString($valueOfRelatedKey, $relatedKeysOfSubItem);
            }
        }
        return $items;
    }

    /**
     * Finds array items with prefix $commonKey and cuts the prefix off.
     * Useful for finding items of array / class fields.
     *
     * Example:
     *
     *  For common key "some_key"
     *  and array [ "some_key" => "<array>", "some_key[0]" => "item 1", "some_key[1]" => "item 2" ]
     *  it returns [ "[0]" => "item 1", "[1]" => "item 2" ].
     *
     *
     * @param $maybeRelatedKeys
     * @param $commonKey
     * @return array
     */
    private static function findRelatedKeys($maybeRelatedKeys, $commonKey) {
        $rel = [];
        $lengthOfCommonPart = strlen($commonKey);

        foreach ($maybeRelatedKeys as $key => $value) {
            if (Strings::startsWith($key, $commonKey) && $key !== $commonKey) {
                $rel[substr($key, $lengthOfCommonPart)] = $value;
            }
        }
        return $rel;
    }
}
