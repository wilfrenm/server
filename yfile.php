<?php

namespace App\Helpers;

use DateTime;

class CustomHelpers
{
    const DATE_FORMAT = 'd, M Y';
    const TIME_FORMAT = 'g:i A';

    /**
     * Formats a date string to DD-MM-YYYY format.
     *
     * @param string $dateString - The date string in YYYY-MM-DD format.
     * @return string - The formatted date string.
     */
    public static function formatDate($dateString)
    {
        if (empty($dateString)) {
            return '-';
        }
        $dateParts = explode('-', $dateString);
        if (count($dateParts) !== 3) {
            return $dateString; // Return the original string if it doesn't match the expected format.
        }
        return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
    }

    public static function convertDateFormat($dateStr)
    {
        // Split the date string into components
        $parts = explode('-', $dateStr);
        $year = $parts[0];
        $month = $parts[1];
        $day = $parts[2];

        // Array of month names
        $monthNames = [
            "Jan",
            "Feb",
            "Mar",
            "Apr",
            "May",
            "Jun",
            "Jul",
            "Aug",
            "Sep",
            "Oct",
            "Nov",
            "Dec"
        ];

        // Convert the month number to month name
        $monthName = $monthNames[intval($month) - 1];

        // Construct the new date format
        return "{$day}, {$monthName} {$year}";
    }

    /**
     * Returns the format for meeting dates.
     * 
     * @return string - The formatted date string.
     */
    public static function meetingDateFormat()
    {
        return 'd, M Y';
    }

    /**
     * Returns the format for meeting times.
     * 
     * @return string - The formatted time string.
     */
    public static function meetingTimeFormat()
    {
        return 'h:i A';
    }

    /**
     * Converts 24-hour time format to 12-hour format.
     * 
     * @param string $time - The time in 24-hour format.
     * @return string - The time in 12-hour format with AM/PM.
     */
    public static function convertTo12HourFormat($time)
    {
        // Create a DateTime object from the input time
        $dateTime = DateTime::createFromFormat('H:i:s', $time);

        // Check if the time is valid
        if (!$dateTime) {
            return "Invalid time format";
        }

        // Format the time to 12-hour format with AM/PM
        return $dateTime->format('g:i A');
    }

    /**
     * Formats dates and times in an array.
     * 
     * @param array $array - The array containing date and time strings.
     * @return array - The array with formatted date and time strings.
     */
    public static function formatDatesInArray(array $array): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::formatDatesInArray($value);
            } elseif (self::isDate($value)) {
                $date = DateTime::createFromFormat('d-m-Y', $value) ?: DateTime::createFromFormat('Y-m-d', $value);
                $value = $date ? $date->format(self::DATE_FORMAT) : $value;
            } elseif (self::isDateTime($value)) {
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $value) ?: DateTime::createFromFormat('Y-m-d H:i', $value);
                $value = $dateTime ? $dateTime->format(self::DATE_FORMAT) : $value;
            } elseif (self::isTime($value)) {
                $time = DateTime::createFromFormat('H:i:s', $value) ?: DateTime::createFromFormat('H:i', $value) ?: DateTime::createFromFormat('H:i A', $value);
                $value = $time ? $time->format(self::TIME_FORMAT) : $value;
            }
        }
        return $array;
    }

    /**
     * Checks if a value is a valid date string.
     * 
     * @param string $value - The value to check.
     * @return bool - True if the value is a valid date, false otherwise.
     */
    public static function isDate($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $formats = ['d-m-Y', 'Y-m-d'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a value is a valid date-time string.
     * 
     * @param string $value - The value to check.
     * @return bool - True if the value is a valid date-time, false otherwise.
     */
    public static function isDateTime($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $value);
            if ($dateTime && $dateTime->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a value is a valid time string.
     * 
     * @param string $value - The value to check.
     * @return bool - True if the value is a valid time, false otherwise.
     */
    public static function isTime($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $formats = ['H:i:s', 'H:i', 'h:i A'];
        foreach ($formats as $format) {
            $time = DateTime::createFromFormat($format, $value);
            if ($time && $time->format($format) === $value) {
                return true;
            }
        }

        return false;
    }
    //strip tag helper
    public static function stripTagsFromSubArrays($input)
    {
        $output = array_map(function ($subArray) {
            return array_map('strip_tags', $subArray);
        }, $input);

        return $output;
    }

    public static function datetimeformat($datetime)
    {

        $date = DateTime::createFromFormat('m/d/Y, h:i:s A', $datetime);
        $formattedDate = $date->format('Y-m-d H:i:s');
        return $formattedDate;
    }
    /** 
     * @param string $dateTimeString The date-time string in the format 'Y-m-d H:i:s P'.
     * @return string The formatted date & time string in the format 'DD, MMM YYYY (hh:mm AM/PM)'. | Returns '-' if the input is empty.
     * 
     * @purpose To convert raw date-time strings into a user-friendly format for display.
     * 
     * @author Ajmal Akram S
     */
    public static function formatDateTime($dateTimeString)
    {
        if (empty($dateTimeString)) {
            return '-';
        }

        // Create a DateTime object that accounts for the timezone offset
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s P', $dateTimeString);

        if (!$dateTime) {
            return "Invalid date-time format";
        }

        // Format date using the existing convertDateFormat logic
        $formattedDate = $dateTime->format('Y-m-d');
        $formattedDate = self::convertDateFormat($formattedDate);

        // Format time using the meetingTimeFormat
        $formattedTime = $dateTime->format(self::meetingTimeFormat());

        // Combine date and time
        return "{$formattedDate} ({$formattedTime})";
    }
}

