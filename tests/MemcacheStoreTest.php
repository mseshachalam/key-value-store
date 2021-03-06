<?php

/*
 * This file is part of the webmozart/key-value-store package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\KeyValueStore\Tests;

use Memcache;
use Webmozart\KeyValueStore\MemcacheStore;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MemcacheStoreTest extends AbstractKeyValueStoreTest
{
    private static $supported;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!class_exists('\Memcache')) {
            self::$supported = false;

            return;
        }

        $client = new Memcache();

        self::$supported = @$client->connect('127.0.0.1');
    }

    protected function setUp()
    {
        if (!self::$supported) {
            $this->markTestSkipped('Memcache is not supported.');
        }

        parent::setUp();
    }

    protected function createStore()
    {
        return new MemcacheStore();
    }
}
