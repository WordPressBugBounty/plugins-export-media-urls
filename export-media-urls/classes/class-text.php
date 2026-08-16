<?php

namespace Export_Media_URLs;

defined('ABSPATH') || exit;

/**
 * Text normalisation for exported values.
 *
 *  1. ensure_utf8()   Always applied; invalid UTF-8 would corrupt the download.
 *  2. fix_mojibake()  Repairs "â€™" back to "’". Conservative by design.
 *  3. decode_entities() / to_ascii()  Entities, and optional ASCII folding.
 *
 * Plain byte handling throughout: no mbstring or iconv dependency.
 */
class EMU_Text
{
    /**
     * Windows-1252 byte (0x80-0x9F) => codepoint. The only bytes where CP1252
     * differs from ISO-8859-1, and the ones that show up mangled.
     *
     * @return array
     */
    private static function cp1252()
    {
        return array(
            0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E,
            0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6,
            0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
            0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C,
            0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
            0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
            0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
        );
    }

    /**
     * Run the configured normalisation pipeline over one value.
     *
     * @param string $value
     * @param array  $o Sanitized options (text_repair / text_entities / text_ascii).
     * @return string
     */
    public static function normalize($value, $o)
    {
        $value = self::ensure_utf8($value);

        if ($value === '') {
            return $value;
        }

        if (!empty($o['text_repair'])) {
            $value = self::fix_mojibake($value);
        }
        if (!empty($o['text_entities'])) {
            $value = self::decode_entities($value);
        }
        if (!empty($o['text_ascii'])) {
            $value = self::to_ascii($value);
        }

        return $value;
    }

    /* --------------------------------------------------------------------- */
    /* UTF-8 validity                                                        */
    /* --------------------------------------------------------------------- */

    /**
     * Whether the string is well-formed UTF-8. Uses PCRE's validator, falling
     * back to the manual scanner if PCRE lacks UTF-8 support.
     *
     * @param string $value
     * @return bool
     */
    public static function is_utf8($value)
    {
        if ($value === '') {
            return true;
        }

        $result = preg_match('//u', $value);
        if ($result === false && preg_last_error() === PREG_INTERNAL_ERROR) {
            return self::codepoints($value) !== null;
        }

        return $result === 1;
    }

    /**
     * Well-formed UTF-8 version of the value. Stray bytes are read as
     * Windows-1252 and re-encoded, so "caf\xE9" becomes "café" rather than
     * being discarded.
     *
     * @param string $value
     * @return string
     */
    public static function ensure_utf8($value)
    {
        $value = (string) $value;
        if ($value === '' || self::is_utf8($value)) {
            return $value;
        }

        $map = self::cp1252();
        $out = '';
        $len = strlen($value);
        $i = 0;

        while ($i < $len) {
            $byte = ord($value[$i]);

            if ($byte < 0x80) {
                $out .= $value[$i];
                $i++;
                continue;
            }

            $seq = self::sequence_length($byte);
            if ($seq > 0 && self::valid_sequence($value, $i, $seq, $len)) {
                $out .= substr($value, $i, $seq);
                $i += $seq;
                continue;
            }

            // Lone legacy byte: read as Windows-1252.
            $cp = isset($map[$byte]) ? $map[$byte] : $byte;
            $out .= self::encode_codepoint($cp);
            $i++;
        }

        return $out;
    }

    /**
     * Byte length of the sequence a lead byte introduces, 0 if it cannot start one.
     *
     * @param int $byte
     * @return int
     */
    private static function sequence_length($byte)
    {
        if ($byte >= 0xC2 && $byte <= 0xDF) {
            return 2;
        }
        if ($byte >= 0xE0 && $byte <= 0xEF) {
            return 3;
        }
        if ($byte >= 0xF0 && $byte <= 0xF4) {
            return 4;
        }
        return 0;
    }

    /**
     * Whether $length bytes at $offset form a valid, non-overlong, non-surrogate
     * UTF-8 sequence inside the Unicode range.
     *
     * @param string $value
     * @param int    $offset
     * @param int    $length
     * @param int    $len    Total string length.
     * @return bool
     */
    private static function valid_sequence($value, $offset, $length, $len)
    {
        if ($offset + $length > $len) {
            return false;
        }

        $lead = ord($value[$offset]);
        $cp = ($length === 2) ? ($lead & 0x1F) : (($length === 3) ? ($lead & 0x0F) : ($lead & 0x07));

        for ($k = 1; $k < $length; $k++) {
            $cont = ord($value[$offset + $k]);
            if ($cont < 0x80 || $cont > 0xBF) {
                return false;
            }
            $cp = ($cp << 6) | ($cont & 0x3F);
        }

        if ($length === 3 && $cp < 0x800) {
            return false;
        }
        if ($length === 4 && $cp < 0x10000) {
            return false;
        }
        if ($cp >= 0xD800 && $cp <= 0xDFFF) {
            return false;
        }

        return $cp <= 0x10FFFF;
    }

    /**
     * Decode a UTF-8 string into codepoints, or null when it is not valid UTF-8.
     *
     * @param string $value
     * @return int[]|null
     */
    private static function codepoints($value)
    {
        $out = array();
        $len = strlen($value);
        $i = 0;

        while ($i < $len) {
            $byte = ord($value[$i]);

            if ($byte < 0x80) {
                $out[] = $byte;
                $i++;
                continue;
            }

            $seq = self::sequence_length($byte);
            if ($seq === 0 || !self::valid_sequence($value, $i, $seq, $len)) {
                return null;
            }

            $cp = ($seq === 2) ? ($byte & 0x1F) : (($seq === 3) ? ($byte & 0x0F) : ($byte & 0x07));
            for ($k = 1; $k < $seq; $k++) {
                $cp = ($cp << 6) | (ord($value[$i + $k]) & 0x3F);
            }

            $out[] = $cp;
            $i += $seq;
        }

        return $out;
    }

    /**
     * Encode a single codepoint as UTF-8.
     *
     * @param int $cp
     * @return string
     */
    private static function encode_codepoint($cp)
    {
        if ($cp < 0x80) {
            return chr($cp);
        }
        if ($cp < 0x800) {
            return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        }
        if ($cp < 0x10000) {
            return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
        }
        return chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F))
            . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }

    /* --------------------------------------------------------------------- */
    /* Mojibake repair                                                       */
    /* --------------------------------------------------------------------- */

    /**
     * Repair UTF-8 stored through a Windows-1252 / ISO-8859-1 lens
     * ("â€™" -> "’", "CafÃ©" -> "Café"), including doubly damaged text.
     *
     * @param string $value Well-formed UTF-8.
     * @return string
     */
    public static function fix_mojibake($value)
    {
        // Cheap pre-filter: mojibake always starts with one of these lead bytes.
        if ($value === '' || !preg_match('/[\xC2-\xC5\xD0-\xD1][\x80-\xBF]/', $value)) {
            return $value;
        }

        // Only adopt the restored copy if a reversal actually succeeds, or we
        // would swap a visible U+FFFD for an invisible control character.
        $candidate = self::restore_lost_bytes($value);
        $repaired = null;

        for ($pass = 0; $pass < 3; $pass++) {
            $next = self::unmojibake_once($candidate);
            if ($next === null) {
                break;
            }
            $candidate = $next;
            $repaired = $next;
        }

        return ($repaired === null) ? $value : $repaired;
    }

    /**
     * One reversal pass: read each character back as the byte a Windows-1252
     * decoder produced. Null means "leave the value alone".
     *
     * @param string $value
     * @return string|null
     */
    private static function unmojibake_once($value)
    {
        $cps = self::codepoints($value);
        if ($cps === null) {
            return null;
        }

        $reverse = array_flip(self::cp1252());
        $bytes = '';
        $saw_high = false;

        foreach ($cps as $cp) {
            if ($cp < 0x80) {
                $bytes .= chr($cp);
                continue;
            }
            if (isset($reverse[$cp])) {
                $bytes .= chr($reverse[$cp]);
                $saw_high = true;
                continue;
            }
            if ($cp <= 0xFF) {
                $bytes .= chr($cp);
                $saw_high = true;
                continue;
            }
            // No single-byte encoding could produce this: real content.
            return null;
        }

        if (!$saw_high || $bytes === $value) {
            return null;
        }

        // Must be valid UTF-8 AND plausible content. Validity alone is far too
        // weak: "NESTLÉ® packshot" reverses to a valid but meaningless U+024E.
        $produced = self::codepoints($bytes);
        if ($produced === null) {
            return null;
        }

        return self::plausible_repair($produced) ? $bytes : null;
    }

    /**
     * Whether a reversal's output looks like real text rather than an accident.
     *
     *  1. Some blocks are never the answer: C1 controls, Latin Extended-B, IPA,
     *     modifiers and combining marks are what "É®", "É…" and "É™" produce.
     *  2. Damage is systematic. Greek, Cyrillic, Arabic or CJK mojibake reverses
     *     to whole words; a lone such character between ASCII is an accident.
     *     This is what saves engineering text like "Ø 250 mm".
     *
     * Latin-1, Latin Extended-A, punctuation and currency are exempt from rule 2,
     * since "CafÃ©" and "Atlasâ€™" legitimately produce a single "é" or "’".
     *
     * @param int[] $produced Codepoints the reversal produced.
     * @return bool
     */
    private static function plausible_repair($produced)
    {
        $count = count($produced);
        $saw_multibyte = false;

        for ($i = 0; $i < $count; $i++) {
            $cp = $produced[$i];
            if ($cp < 0x80) {
                continue;
            }
            $saw_multibyte = true;

            // Rule 1.
            if (($cp >= 0x80 && $cp <= 0x9F)       // C1 controls
                || ($cp >= 0x180 && $cp <= 0x36F)  // Latin Ext-B, IPA, modifiers, combining
                || $cp === 0xFFFD                  // replacement character
            ) {
                return false;
            }

            // Rule 2.
            if (!self::common_repair_target($cp)) {
                $prev = ($i > 0) ? $produced[$i - 1] : 0;
                $next = ($i + 1 < $count) ? $produced[$i + 1] : 0;

                $has_neighbour = ($prev >= 0x80 && !self::common_repair_target($prev))
                    || ($next >= 0x80 && !self::common_repair_target($next));

                if (!$has_neighbour) {
                    return false;
                }
            }
        }

        return $saw_multibyte;
    }

    /**
     * Characters that routinely appear alone as the result of a genuine repair.
     *
     * @param int $cp
     * @return bool
     */
    private static function common_repair_target($cp)
    {
        return ($cp >= 0xA0 && $cp <= 0x17F)      // Latin-1 Supplement + Latin Extended-A
            || ($cp >= 0x2000 && $cp <= 0x206F)   // General Punctuation
            || ($cp >= 0x20A0 && $cp <= 0x20CF)   // Currency Symbols
            || ($cp >= 0x2100 && $cp <= 0x214F)   // Letterlike Symbols
            || ($cp >= 0x2190 && $cp <= 0x22FF);  // Arrows + Mathematical Operators
    }

    /**
     * Put back the byte a Windows-1252 decoder could not represent.
     *
     * 0x9D has no CP1252 character, so the decoder emitted U+FFFD instead. The
     * only sequence this bites in practice is "â€" + 0x9D, a right double
     * quotation mark. Runs before the reversal passes, never after.
     *
     * @param string $value
     * @return string
     */
    private static function restore_lost_bytes($value)
    {
        if (strpos($value, "\xEF\xBF\xBD") === false) {
            return $value;
        }

        // "â" U+00E2 . "€" U+20AC . U+FFFD  ->  "â" . "€" . U+009D
        return str_replace(
            "\xC3\xA2\xE2\x82\xAC\xEF\xBF\xBD",
            "\xC3\xA2\xE2\x82\xAC\xC2\x9D",
            $value
        );
    }

    /* --------------------------------------------------------------------- */
    /* Entities and ASCII folding                                            */
    /* --------------------------------------------------------------------- */

    /**
     * Turn HTML entities into the characters they stand for.
     *
     * @param string $value
     * @return string
     */
    public static function decode_entities($value)
    {
        if ($value === '' || strpos($value, '&') === false) {
            return $value;
        }

        // Replaced before decoding, and only in entity form, so a real U+00A0
        // the author typed is left alone.
        $value = str_ireplace(array('&nbsp;', '&#160;', '&#xA0;', '&#x00A0;'), ' ', $value);

        return html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Fold typographic punctuation to plain ASCII. Lossy, so opt-in; letters and
     * non-Latin scripts are never touched.
     *
     * @param string $value
     * @return string
     */
    public static function to_ascii($value)
    {
        if ($value === '') {
            return $value;
        }

        $map = array(
            "\xE2\x80\x98" => "'",   // ‘
            "\xE2\x80\x99" => "'",   // ’
            "\xE2\x80\x9A" => "'",   // ‚
            "\xE2\x80\x9B" => "'",   // ‛
            "\xE2\x80\xB2" => "'",   // ′
            "\xC2\xB4"     => "'",   // ´
            "\xCB\x8A"     => "'",   // ˊ
            "\xE2\x80\x9C" => '"',   // “
            "\xE2\x80\x9D" => '"',   // ”
            "\xE2\x80\x9E" => '"',   // „
            "\xE2\x80\x9F" => '"',   // ‟
            "\xE2\x80\xB3" => '"',   // ″
            "\xC2\xAB"     => '"',   // «
            "\xC2\xBB"     => '"',   // »
            "\xE2\x80\xB9" => "'",   // ‹
            "\xE2\x80\xBA" => "'",   // ›
            "\xE2\x80\x90" => '-',   // ‐
            "\xE2\x80\x91" => '-',   // ‑
            "\xE2\x80\x92" => '-',   // ‒
            "\xE2\x80\x93" => '-',   // –
            "\xE2\x80\x94" => '-',   // —
            "\xE2\x80\x95" => '-',   // ―
            "\xE2\x88\x92" => '-',   // −
            "\xE2\x80\xA6" => '...', // …
            "\xE2\x80\xA2" => '*',   // •
            "\xC2\xB7"     => '*',   // ·
            "\xC2\xA0"     => ' ',   // non-breaking space
            "\xE2\x80\x8B" => '',    // zero-width space
            "\xEF\xBB\xBF" => '',    // zero-width no-break space / stray BOM
            "\xE2\x84\xA2" => '(TM)',
            "\xC2\xAE"     => '(R)',
            "\xC2\xA9"     => '(C)',
        );

        return strtr($value, $map);
    }

    /* --------------------------------------------------------------------- */
    /* CSV-specific flattening                                               */
    /* --------------------------------------------------------------------- */

    /**
     * Collapse anything that makes a CSV record span more than one physical line.
     * Line breaks inside a quoted field are valid RFC 4180, but line-oriented
     * importers split the record there and read it as several broken ones.
     *
     * @param string $value
     * @param string $separator Text put in place of each run of line breaks.
     * @return string
     */
    public static function flatten($value, $separator = ' ')
    {
        $value = (string) $value;
        if ($value === '') {
            return $value;
        }

        // Runs of CR / LF / form feed / vertical tab become one separator.
        $value = preg_replace('/[\r\n\x0B\f]+/', $separator, $value);

        // Tabs become spaces; remaining control characters are dropped.
        $value = str_replace("\t", ' ', $value);
        $value = self::strip_controls($value);

        return trim($value);
    }

    /**
     * Drop control characters, keeping tab, CR and LF.
     *
     * @param string $value
     * @return string
     */
    public static function strip_controls($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return $value;
        }

        return preg_replace('/[\x00-\x08\x0E-\x1F\x7F]/', '', $value);
    }
}
