<?php

namespace App\Support;

/**
 * Code 39 — the barcode printed under the order reference on a receipt.
 *
 * Code 39 is the right symbology here because it needs no check digit and
 * encodes exactly the character set our references use (digits, capitals and
 * the hyphen), so the bars printed on the receipt are the reference itself and
 * a counter scanner reads back `VD-POS-260824-0007` verbatim.
 *
 * Each character is nine elements wide — five bars and four spaces, starting
 * and ending on a bar — of which three are wide. Characters are separated by
 * one narrow space, and the whole symbol is wrapped in the `*` start/stop
 * character. Widths come back proportional rather than in pixels so the
 * symbol can be laid out at whatever width the receipt happens to be.
 */
final class Code39
{
    /** N = narrow, W = wide, read bar-space-bar-space-… */
    private const PATTERNS = [
        '0' => 'NNNWWNWNN', '1' => 'WNNWNNNNW', '2' => 'NNWWNNNNW', '3' => 'WNWWNNNNN',
        '4' => 'NNNWWNNNW', '5' => 'WNNWWNNNN', '6' => 'NNWWWNNNN', '7' => 'NNNWNNWNW',
        '8' => 'WNNWNNWNN', '9' => 'NNWWNNWNN',
        'A' => 'WNNNNWNNW', 'B' => 'NNWNNWNNW', 'C' => 'WNWNNWNNN', 'D' => 'NNNNWWNNW',
        'E' => 'WNNNWWNNN', 'F' => 'NNWNWWNNN', 'G' => 'NNNNNWWNW', 'H' => 'WNNNNWWNN',
        'I' => 'NNWNNWWNN', 'J' => 'NNNNWWWNN', 'K' => 'WNNNNNNWW', 'L' => 'NNWNNNNWW',
        'M' => 'WNWNNNNWN', 'N' => 'NNNNWNNWW', 'O' => 'WNNNWNNWN', 'P' => 'NNWNWNNWN',
        'Q' => 'NNNNNNWWW', 'R' => 'WNNNNNWWN', 'S' => 'NNWNNNWWN', 'T' => 'NNNNWNWWN',
        'U' => 'WWNNNNNNW', 'V' => 'NWWNNNNNW', 'W' => 'WWWNNNNNN', 'X' => 'NWNNWNNNW',
        'Y' => 'WWNNWNNNN', 'Z' => 'NWWNWNNNN',
        '-' => 'NWNNNNWNW', '.' => 'WWNNNNWNN', ' ' => 'NWWNNNWNN', '$' => 'NWNWNWNNN',
        '/' => 'NWNWNNNWN', '+' => 'NWNNNWNWN', '%' => 'NNNWNWNWN', '*' => 'NWNNWNWNN',
    ];

    private const NARROW = 1;

    private const WIDE = 3;

    /** A narrow space sits between characters. */
    private const GAP = 1;

    /**
     * The symbol as a run of elements.
     *
     * @return array{elements: array<int, array{bar: bool, units: int}>, units: int}
     */
    public static function symbol(string $value): array
    {
        $characters = str_split('*'.self::normalise($value).'*');
        $elements = [];
        $total = 0;

        foreach ($characters as $index => $character) {
            if ($index > 0) {
                $elements[] = ['bar' => false, 'units' => self::GAP];
                $total += self::GAP;
            }

            foreach (str_split(self::PATTERNS[$character]) as $position => $width) {
                $units = $width === 'W' ? self::WIDE : self::NARROW;

                $elements[] = ['bar' => $position % 2 === 0, 'units' => $units];
                $total += $units;
            }
        }

        return ['elements' => $elements, 'units' => $total];
    }

    /** Anything the symbology cannot carry is dropped rather than mis-encoded. */
    private static function normalise(string $value): string
    {
        $upper = strtoupper($value);
        $out = '';

        foreach (str_split($upper) as $character) {
            if ($character !== '*' && isset(self::PATTERNS[$character])) {
                $out .= $character;
            }
        }

        return $out;
    }
}
