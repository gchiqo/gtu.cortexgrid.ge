<?php
declare(strict_types=1);

/**
 * The 10 GTU faculties whose "additional courses" PDFs we ingest.
 *
 * `match` is the list of Georgian substrings we look for inside each <a> anchor's
 * text on the leqtori homepage. The first key whose `match` strings ALL appear in
 * the anchor text wins. Order keys from most-specific to least-specific so that
 * narrow matches (e.g. "ინფორმატიკისა + მართვის") win over broad ones.
 */
function faculties(): array {
    return [
        // Most-specific entries first so they win classify_faculty's
        // first-match-wins iteration.
        'design_school' => [
            'name_ka' => 'დიზაინის საერთაშორისო სკოლა',
            'name_en' => 'International Design School',
            'match'   => ['დიზაინის საერთაშორისო სკოლა'],
        ],
        'exam_center_1' => [
            'name_ka' => 'საგამოცდო ცენტრი 1',
            'name_en' => 'Exam Center 1',
            'match'   => ['საგამოცდო ცენტრი 1'],
        ],
        'exam_center_2' => [
            'name_ka' => 'საგამოცდო ცენტრი 2',
            'name_en' => 'Exam Center 2',
            'match'   => ['საგამოცდო ცენტრი 2'],
        ],
        'ims' => [
            'name_ka' => 'ინფორმატიკისა და მართვის სისტემების ფაკულტეტი',
            'name_en' => 'Faculty of Informatics and Control Systems',
            'match'   => ['ინფორმატიკისა', 'მართვის სისტემების'],
        ],
        'mining' => [
            'name_ka' => 'სამთო-გეოლოგიური და მთის მდგრადი განვითარების ფაკულტეტი',
            'name_en' => 'Faculty of Mining Geology and Sustainable Mountain Development',
            'match'   => ['სამთო', 'გეოლოგიური'],
        ],
        'agrarian' => [
            'name_ka' => 'აგრარული მეცნიერებისა და ქიმიური ტექნოლოგიების ფაკულტეტი',
            'name_en' => 'Faculty of Agricultural Sciences and Chemical Technologies',
            'match'   => ['აგრარული'],
        ],
        'transport' => [
            'name_ka' => 'სატრანსპორტო სისტემები და მექანიკის ინჟინერიის ფაკულტეტი',
            'name_en' => 'Faculty of Transport Systems and Mechanical Engineering',
            'match'   => ['სატრანსპორტო'],
        ],
        'architecture' => [
            'name_ka' => 'არქიტექტურა, ურბანისტიკა და დიზაინის ფაკულტეტი',
            'name_en' => 'Faculty of Architecture, Urbanism and Design',
            'match'   => ['არქიტექტურა'],
        ],
        'law' => [
            'name_ka' => 'სამართალი და საერთაშორისო ურთიერთობების ფაკულტეტი',
            'name_en' => 'Faculty of Law and International Relations',
            'match'   => ['სამართ', 'საერთაშორისო'],
        ],
        'business' => [
            'name_ka' => 'ბიზნესტექნოლოგიების ფაკულტეტი',
            'name_en' => 'Faculty of Business Technologies',
            'match'   => ['ბიზნესტექნოლოგიების'],
        ],
        'social' => [
            'name_ka' => 'სოციალურ მეცნიერებათა ფაკულტეტი',
            'name_en' => 'Faculty of Social Sciences',
            'match'   => ['სოციალურ'],
        ],
        'power' => [
            'name_ka' => 'ენერგეტიკის ფაკულტეტი',
            'name_en' => 'Faculty of Power Engineering',
            'match'   => ['ენერგეტიკის'],
        ],
        'construction' => [
            'name_ka' => 'სამშენებლო ფაკულტეტი',
            'name_en' => 'Faculty of Construction',
            'match'   => ['სამშენებლო'],
        ],
    ];
}

function classify_faculty(string $anchorText): ?string {
    $text = $anchorText;
    foreach (faculties() as $slug => $cfg) {
        $allMatch = true;
        foreach ($cfg['match'] as $needle) {
            if (mb_strpos($text, $needle) === false) { $allMatch = false; break; }
        }
        if ($allMatch) return $slug;
    }
    return null;
}
