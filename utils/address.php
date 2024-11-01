<?php

function format_address($address) {
    // Get everything up to the first number with a regex
    $hasMatch = preg_match('/^[^0-9]*/', $address, $match);
    // If no matching is possible, return the supplied string as the street
    if (!$hasMatch) {
        return array( 'street' => $address, 'number' => '');
    }
    // Remove the street from the address.
    $address = str_replace($match[0], "", $address);
    $street = trim($match[0]);
    // Nothing left to split, return
    if (strlen($address == 0)) {
        return array('street' => $street, 'number' => '');
    }
    // Explode address to an array
    $addrArray = explode(" ", $address);
    // Shift the first element off the array, that is the house number
    $number = array_shift($addrArray);

    return array('street' => $street, 'number' => $number);
}
?>