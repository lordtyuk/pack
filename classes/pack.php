<?php

class Pack {
    // Calculate the number of bits required to represent numbers between 0 and N
    function requiredBits($N) {
        return (int)ceil(log($N + 1, 2));
    }

// Pack the array of numbers using the exact number of bits
    public function packNumbers(array $numbers, $N) {
        $bitSize = $this->requiredBits($N);
        $packedBits = '';

        foreach ($numbers as $number) {
            // Pack each number into a bitstring of fixed size
            $packedBits .= str_pad(decbin($number), $bitSize, '0', STR_PAD_LEFT);
        }

        // Convert the packed bit string to bytes
        $byteArray = '';
        while (strlen($packedBits) > 0) {
            $byteArray .= chr(bindec(substr($packedBits, 0, 8)));
            $packedBits = substr($packedBits, 8);
        }

        return $byteArray;
    }

// Unpack the binary string back into an array of numbers
    public function unpackNumbers($byteArray, $count, $N) {
        $bitSize = $this->requiredBits($N);
        $bitString = '';

        // Convert each byte back to its binary representation
        foreach (str_split($byteArray) as $char) {
            $bitString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        // Extract the numbers based on the bit size
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $number = bindec(substr($bitString, $i * $bitSize, $bitSize));
            $numbers[] = $number;
        }

        return $numbers;
    }
}
