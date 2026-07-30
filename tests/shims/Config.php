<?php

namespace Leaf;

/*
 * Minimal stand-in for leaf core's Config, enough for leafs/http's
 * request()/response() helpers to hand out singletons. That lets tests
 * inspect the Response instance (status, headers) after a render.
 */
class Config
{
    protected static $items = [];
    protected static $singletons = [];

    public static function singleton($key, $factory)
    {
        static::$singletons[$key] = $factory;
    }

    public static function getStatic($key)
    {
        return static::$singletons[$key] ?? static::$items[$key] ?? null;
    }

    public static function get($key)
    {
        if (isset(static::$items[$key])) {
            return static::$items[$key];
        }

        if (isset(static::$singletons[$key])) {
            return static::$items[$key] = call_user_func(static::$singletons[$key]);
        }

        return null;
    }

    public static function set($key, $value = null)
    {
        if (is_array($key)) {
            static::$items = array_merge(static::$items, $key);
        } else {
            static::$items[$key] = $value;
        }
    }

    public static function reset()
    {
        static::$items = [];
        static::$singletons = [];
    }
}
