<?php


if (!function_exists('find_role')) {
    function find_role($needle, $property, $objects)
    {
        foreach ($objects as $object) {
            if ($object->$property === $needle) {
                return $object;
            }
        }

        return false;
    }
}
