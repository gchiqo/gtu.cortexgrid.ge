<?php
declare(strict_types=1);

/**
 * Georgian → Latin transliteration (ISO 9984-ish, simplified for search).
 *
 * The mapping is intentionally many-to-one in the Latin direction: კ and ქ both
 * become "k", ც and წ both become "ts", თ and ტ both become "t", etc. This
 * mirrors how Georgian names are usually spelled in passports / English text
 * and matches the Latin forms that already appear in the IMS PDFs (e.g.
 * "ცხომელიძე" → "tskhomelidze" matches the existing "Tskhomelidze").
 *
 * Non-Georgian characters pass through unchanged, so calling this on already-
 * Latin or mixed text is safe.
 */
function ka_to_latin_primary_map(): array {
    return [
        'ა' => 'a',  'ბ' => 'b',  'გ' => 'g',  'დ' => 'd',  'ე' => 'e',
        'ვ' => 'v',  'ზ' => 'z',  'თ' => 't',  'ი' => 'i',  'კ' => 'k',
        'ლ' => 'l',  'მ' => 'm',  'ნ' => 'n',  'ო' => 'o',  'პ' => 'p',
        'ჟ' => 'zh', 'რ' => 'r',  'ს' => 's',  'ტ' => 't',  'უ' => 'u',
        'ფ' => 'p',  'ქ' => 'k',  'ღ' => 'gh', 'ყ' => 'q',  'შ' => 'sh',
        'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch',
        'ხ' => 'kh', 'ჯ' => 'j',  'ჰ' => 'h',
    ];
}

function transliterate_ka_to_latin(string $s, array $overrides = []): string {
    $map = $overrides ? array_merge(ka_to_latin_primary_map(), $overrides) : ka_to_latin_primary_map();
    return strtr($s, $map);
}

/**
 * Several Georgian letters have widely-used alternate Latin spellings:
 *   ფ — usually transliterated as "p" but commonly as "f" or "ph"
 *       (so ფიზიკა → "pizika" / "fizika" / "phizika" all reach the same word)
 *   ხ — "kh" is standard, but "h" and "x" also appear in older transliterations
 *   ქ — "k" standard, "q" sometimes used
 *
 * Returning all variants lets users find the same Georgian word however they
 * choose to romanize it.
 */
function transliterate_variants(string $s): array {
    $variants = [transliterate_ka_to_latin($s)];
    foreach ([
        ['ფ' => 'f'],   // ფიზიკა → fizika
        ['ფ' => 'ph'],  // ფიზიკა → phizika
        ['ხ' => 'h'],   // ხ → h (some passport forms)
        ['ქ' => 'q'],   // ქ → q (alt)
    ] as $override) {
        $variants[] = transliterate_ka_to_latin($s, $override);
    }
    return array_values(array_unique($variants));
}

/**
 * Build the searchable haystack stored alongside each name. Concatenates the
 * lowercased original with its Latin transliteration (when those differ), so
 * a single LIKE '%tskhom%' query hits both forms.
 *
 * For purely-Latin names, the transliteration is identical and we just store
 * the lowercased original — no duplication, no wasted bytes.
 */
function searchable_text(?string $original): ?string {
    if ($original === null) return null;
    $original = trim($original);
    if ($original === '') return '';

    $lower = mb_strtolower($original, 'UTF-8');
    $variants = transliterate_variants($lower);

    $parts = [$lower];
    foreach ($variants as $v) {
        if ($v !== '' && $v !== $lower && !in_array($v, $parts, true)) {
            $parts[] = $v;
        }
    }
    return implode(' ', $parts);
}
