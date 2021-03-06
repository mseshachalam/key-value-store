<?php

/*
 * This file is part of the webmozart/key-value-store package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\KeyValueStore;

use Exception;
use stdClass;
use Webmozart\KeyValueStore\Api\KeyValueStore;
use Webmozart\KeyValueStore\Api\ReadException;
use Webmozart\KeyValueStore\Api\SerializationFailedException;
use Webmozart\KeyValueStore\Api\WriteException;
use Webmozart\KeyValueStore\Api\UnsupportedValueException;
use Webmozart\KeyValueStore\Assert\Assert;

/**
 * A key-value store backed by a JSON file.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class JsonFileStore implements KeyValueStore
{
    /**
     * This seems to be the biggest float supported by json_encode()/json_decode()
     */
    const MAX_FLOAT = 1.0E+14;

    private $path;

    private $cacheStore;

    public function __construct($path, $cache = true)
    {
        Assert::string($path, 'The path must be a string. Got: %s');
        Assert::notEmpty($path, 'The path must not be empty.');
        Assert::boolean($cache, 'The cache argument must be a boolean. Got: %s');

        $this->path = $path;

        if ($cache) {
            $this->cacheStore = new ArrayStore();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value)
    {
        Assert::key($key);

        if (is_float($value) && $value > self::MAX_FLOAT) {
            throw new UnsupportedValueException('The JSON file store cannot handle floats larger than 1.0E+14.');
        }

        if (is_resource($value)) {
            throw SerializationFailedException::forValue($value);
        }

        if ($this->cacheStore) {
            $this->cacheStore->set($key, $value);
        }

        $data = $this->load();

        if (is_object($value) || is_string($value) || is_array($value)) {
            try {
                $value = serialize($value);
            } catch (Exception $e) {
                throw SerializationFailedException::forValue($value, $e->getCode(), $e);
            }
        }

        $data->$key = $value;

        $this->save($data);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        Assert::key($key);

        if ($this->cacheStore && $this->cacheStore->has($key)) {
            return $this->cacheStore->get($key);
        }

        $data = $this->load();

        if (!property_exists($data, $key)) {
            return $default;
        }

        $value = $data->$key;

        if (is_string($value)) {
            $value = unserialize($value);
        }

        if ($this->cacheStore) {
            $this->cacheStore->set($key, $value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        Assert::key($key);

        if ($this->cacheStore) {
            $this->cacheStore->remove($key);
        }

        $data = $this->load();

        if (!property_exists($data, $key)) {
            return false;
        }

        unset($data->$key);

        $this->save($data);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        Assert::key($key);

        if ($this->cacheStore && $this->cacheStore->has($key)) {
            return true;
        }

        $data = $this->load();

        return property_exists($data, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->save(new stdClass());

        if ($this->cacheStore) {
            $this->cacheStore->clear();
        }
    }

    private function load()
    {
        $contents = file_exists($this->path)
            ? trim(file_get_contents($this->path))
            : null;

        if (!$contents) {
            return new stdClass();
        }

        $decoded = json_decode($contents);

        if (JSON_ERROR_NONE !== ($error = json_last_error())) {
            throw new ReadException(sprintf(
                'Could not decode JSON data: %s',
                self::getErrorMessage($error)
            ));
        }

        return $decoded;
    }

    private function save($data)
    {
        if (!file_exists($dir = dirname($this->path))) {
            mkdir($dir, 0777, true);
        }

        $encoded = json_encode($data);

        if (JSON_ERROR_NONE !== ($error = json_last_error())) {
            if (JSON_ERROR_UTF8 === $error) {
                throw UnsupportedValueException::forType('binary', $this);
            }

            throw new WriteException(sprintf(
                'Could not encode data as JSON: %s',
                self::getErrorMessage($error)
            ));
        }

        file_put_contents($this->path, $encoded);
    }

    /**
     * Returns the error message of a JSON error code.
     *
     * Needed for PHP < 5.5, where `json_last_error_msg()` is not available.
     *
     * @param int $error The error code.
     *
     * @return string The error message.
     */
    private function getErrorMessage($error)
    {
        switch ($error) {
            case JSON_ERROR_NONE:
                return 'JSON_ERROR_NONE';
            case JSON_ERROR_DEPTH:
                return 'JSON_ERROR_DEPTH';
            case JSON_ERROR_STATE_MISMATCH:
                return 'JSON_ERROR_STATE_MISMATCH';
            case JSON_ERROR_CTRL_CHAR:
                return 'JSON_ERROR_CTRL_CHAR';
            case JSON_ERROR_SYNTAX:
                return 'JSON_ERROR_SYNTAX';
            case JSON_ERROR_UTF8:
                return 'JSON_ERROR_UTF8';
        }

        if (version_compare(PHP_VERSION, '5.5.0', '>=')) {
            switch ($error) {
                case JSON_ERROR_RECURSION:
                    return 'JSON_ERROR_RECURSION';
                case JSON_ERROR_INF_OR_NAN:
                    return 'JSON_ERROR_INF_OR_NAN';
                case JSON_ERROR_UNSUPPORTED_TYPE:
                    return 'JSON_ERROR_UNSUPPORTED_TYPE';
            }
        }

        return 'JSON_ERROR_UNKNOWN';
    }
}
