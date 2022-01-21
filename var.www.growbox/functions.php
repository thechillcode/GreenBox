
<?php
// PHP Functions

// Is number in range
function in_range($number, $min, $max, $default)
{
    return ($number >= $min && $number <= $max) ? $number : $default;
}

?>