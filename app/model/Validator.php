<?php

namespace Develoweb\App\Model;

use DateTime;

class Validator
{
    public static function string ($value, $min = 1, $max = INF)
    {
        $value = trim($value);

        return strlen($value) >= $min && strlen($value) <= $max;
    }

    public static function email ($value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function integer ($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT);
    }

    public static function double ($value)
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT);
    }

    public static function number_int ($value)
    {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
    public static function isDate ($date, $format = 'Y-m-d') {
        $dateTime = DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }

}