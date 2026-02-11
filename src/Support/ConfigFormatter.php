<?php

namespace Iperamuna\SelfDeploy\Support;

class ConfigFormatter
{
    /**
     * Format a PHP array with custom spacing for configuration files.
     */
    public static function format(array $array, int $indent = 0): string
    {
        $indentStr = str_repeat('    ', $indent);
        $nextIndentStr = str_repeat('    ', $indent + 1);

        $output = "[\n\n";

        $count = count($array);
        $i = 0;

        foreach ($array as $key => $value) {
            $i++;

            $output .= $nextIndentStr;

            $output .= is_string($key)
                ? "'".addslashes($key)."' => "
                : $key.' => ';

            if (is_array($value)) {
                $output .= self::format($value, $indent + 1);
            } elseif (is_string($value)) {
                $output .= "'".addslashes($value)."'";
            } elseif (is_bool($value)) {
                $output .= $value ? 'true' : 'false';
            } elseif ($value === null) {
                $output .= 'null';
            } else {
                $output .= $value;
            }

            $output .= ",\n";

            if ($i < $count) {
                $output .= "\n";
            }
        }

        $output .= "\n".$indentStr.']';

        return $output;
    }
}
