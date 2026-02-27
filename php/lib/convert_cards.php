#!/usr/bin/env php
<?php
/**
 * Converts docs/cards.csv into lib/cards.php
 * Usage: php lib/convert_cards.php
 * Run from the php/ directory.
 */

$csv_path = __DIR__ . '/../../docs/cards.csv';
$out_path = __DIR__ . '/cards.php';

if (!file_exists($csv_path)) {
    fwrite(STDERR, "CSV not found: $csv_path\n");
    exit(1);
}

// Emoji → icon key mapping
$emoji_to_icon = [
    '🚘' => 'drive',
    '💪' => 'muscle',
    '🥸' => 'disguise',
    '🔑' => 'key',
];
$icon_regex = '/🚘|💪|🥸|🔑|❓/u';

function slug(string $name): string {
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

function parse_icons_field(string $text, array $emoji_to_icon): array {
    // For agent/tech description fields like "🚘/💪" or "💪🔑" or "💪💪🚘"
    $text = trim($text);
    // Remove trailing ", always available" etc.
    $text = preg_replace('/,\s*always available.*/iu', '', $text);
    $text = trim($text);
    if ($text === '') return [];

    // Split by space to get icon groups, but emojis may be adjacent
    // Strategy: split on "/" first to detect choice groups, then parse each segment
    // Actually, icons can be: "🚘/💪" (choice), "💪🔑" (both), "💪💪🚘" (multiple)
    // The "/" only appears between two single emojis meaning OR-choice.

    $icons = [];
    // Tokenize: walk through the string character by character (multibyte)
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $i = 0;
    $len = count($chars);

    // Re-join into grapheme clusters for emoji matching
    // Simpler: use regex to find all emojis and slashes in order
    preg_match_all('/🚘|💪|🥸|🔑|❓|\//u', $text, $matches);
    $tokens = $matches[0];

    $result = [];
    $idx = 0;
    while ($idx < count($tokens)) {
        $t = $tokens[$idx];
        if ($t === '/') { $idx++; continue; }
        // Check if next token is "/"
        if (isset($tokens[$idx + 1]) && $tokens[$idx + 1] === '/' && isset($tokens[$idx + 2])) {
            $a = $t;
            $b = $tokens[$idx + 2];
            $icon_a = $emoji_to_icon[$a] ?? 'any';
            $icon_b = $emoji_to_icon[$b] ?? 'any';
            $result[] = [$icon_a, $icon_b]; // choice
            $idx += 3;
        } else {
            $result[] = $emoji_to_icon[$t] ?? 'any';
            $idx++;
        }
    }
    return $result;
}

function parse_mission_requirements(string $cost_field, array $emoji_to_icon): array {
    // Mission "Cost" column has emojis like 🚘💪 or ❓
    preg_match_all('/🚘|💪|🥸|🔑|❓/u', $cost_field, $m);
    $reqs = [];
    foreach ($m[0] as $e) {
        $reqs[] = $emoji_to_icon[$e] ?? 'any';
    }
    return $reqs;
}

function parse_mission_rewards(string $desc): array {
    // Extract stars: "1⭐" or "4⭐"
    $stars = 0;
    if (preg_match('/(\d+)\s*⭐/', $desc, $m)) {
        $stars = (int)$m[1];
    }
    // Extract money value: "$4" means the mission card itself is worth $4
    $value = 0;
    if (preg_match('/\$(\d+)/', $desc, $m)) {
        $value = (int)$m[1];
    }
    // Extract gems: "💎1" or "💎3"
    $gems = 0;
    if (preg_match('/💎(\d+)/u', $desc, $m)) {
        $gems = (int)$m[1];
    }
    return [$stars, $value, $gems];
}

// Parse CSV — columns looked up by header name so order doesn't matter
$rows = [];
$fh = fopen($csv_path, 'r');
$raw_header = fgetcsv($fh);
$col = array_flip(array_map('strtolower', array_map('trim', $raw_header)));

function col(array $row, array $col, string $key, string $default = ''): string {
    $i = $col[$key] ?? null;
    return $i !== null && isset($row[$i]) ? trim($row[$i]) : $default;
}

while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < 3) continue;
    $rows[] = [
        'id'           => col($row, $col, 'id'),
        'name'         => col($row, $col, 'name'),
        'count'        => (int)(col($row, $col, 'copies') ?: 0),
        'type'         => strtolower(col($row, $col, 'type')),
        'tier'         => col($row, $col, 'tier') !== '' ? (int)col($row, $col, 'tier') : 0,
        'cost_raw'     => col($row, $col, 'cost'),
        'requirements' => col($row, $col, 'requirements'),
        'effect'       => col($row, $col, 'effect'),
        'ability'      => col($row, $col, 'ability'),
        'special'      => col($row, $col, 'special'),
        'max_tech'     => col($row, $col, 'max tech') !== '' ? (int)col($row, $col, 'max tech') : null,
    ];
}
fclose($fh);

// Build card entries
$cards = [];
$market_counts = []; // slug => count for non-always-available agents, tech, plots
$mission_counts = [1 => [], 2 => [], 3 => []]; // tier => [slug => count]

// Always include money cards (not in CSV)
$cards['money_1'] = [
    'id' => 'money_1', 'name' => '$1', 'type' => 'money',
    'tier' => 0, 'cost' => 0, 'value' => 1, 'stars' => 0,
    'description' => '$1',
];
$cards['money_2'] = [
    'id' => 'money_2', 'name' => '$2', 'type' => 'money',
    'tier' => 0, 'cost' => 0, 'value' => 2, 'stars' => 0,
    'description' => '$2',
];
$cards['money_3'] = [
    'id' => 'money_3', 'name' => '$3', 'type' => 'money',
    'tier' => 0, 'cost' => 0, 'value' => 3, 'stars' => 0,
    'description' => '$3',
];

foreach ($rows as $row) {
    $id = $row['id'] !== '' ? $row['id'] : slug($row['name']);
    $entry = [
        'id' => $id,
        'name' => $row['name'],
        'type' => $row['type'],
        'tier' => $row['tier'],
        'stars' => 0,
    ];

    switch ($row['type']) {
        case 'base':
            $entry['cost'] = 0;
            $entry['indestructible'] = stripos($row['ability'], 'indestructible') !== false;
            $entry['description'] = $row['ability'];
            break;

        case 'hazard':
            // No extra fields needed
            break;

        case 'agent':
            $cost_val = (int)preg_replace('/[^0-9]/', '', $row['cost_raw']);
            $entry['cost'] = $cost_val;
            $entry['max_tech'] = $row['max_tech'] ?? 0;
            $entry['icons'] = parse_icons_field($row['effect'], $emoji_to_icon);
            $entry['always_available'] = stripos($row['special'], 'always available') !== false;
            if (stripos($row['ability'], 'defend') !== false) {
                $entry['defend'] = true;
            }
            $icon_display = trim($row['effect']);
            $entry['description'] = $icon_display . ($entry['always_available'] ? " — Always available (\${$cost_val})" : " (\${$cost_val})");
            if (!$entry['always_available']) {
                $market_counts[$id] = $row['count'];
            }
            break;

        case 'tech':
            $cost_val = (int)preg_replace('/[^0-9]/', '', $row['cost_raw']);
            $entry['cost'] = $cost_val;
            $entry['icons'] = parse_icons_field($row['effect'], $emoji_to_icon);
            $icon_display = trim($row['effect']);
            $entry['description'] = "{$icon_display} (\${$cost_val})";
            $market_counts[$id] = $row['count'];
            break;

        case 'plot':
            $cost_val = (int)preg_replace('/[^0-9]/', '', $row['cost_raw']);
            $entry['cost'] = $cost_val;
            $desc_lower = strtolower($row['effect']);
            if (stripos($desc_lower, 'trash an agent') !== false && stripos($desc_lower, 'gain an agent') !== false) {
                $entry['effect'] = 'training';
            } elseif (stripos($desc_lower, 'trash') !== false) {
                $entry['effect'] = 'trash';
            } elseif (stripos($desc_lower, 'return an agent') !== false || stripos($desc_lower, 'recall') !== false) {
                $entry['effect'] = 'recall';
            } elseif (stripos($desc_lower, 'draw two') !== false || stripos($desc_lower, 'draw 2') !== false) {
                $entry['effect'] = 'draw2';
            } elseif (stripos($desc_lower, 'backup') !== false || stripos($desc_lower, 'got your back') !== false || stripos($desc_lower, 'play an agent from your hand') !== false) {
                $entry['effect'] = 'backup';
            } elseif (stripos($desc_lower, 'gain a red tape') !== false || stripos($desc_lower, 'gains a red tape') !== false) {
                $entry['effect'] = 'paperwork';
            } elseif (stripos($desc_lower, 'discard down to') !== false) {
                $entry['effect'] = 'burglary';
            } elseif (stripos($desc_lower, 'additional op') !== false) {
                $entry['effect'] = 'multitask';
            } else {
                $entry['effect'] = 'none';
            }
            $entry['description'] = $row['effect'] . ($cost_val > 0 ? " (\${$cost_val})" : '');
            if ($row['tier'] > 0) {
                $market_counts[$id] = $row['count'];
            }
            break;

        case 'money':
            $cost_val = (int)preg_replace('/[^0-9]/', '', $row['cost_raw']);
            $entry['cost'] = $cost_val;
            $value = 0;
            if (preg_match('/\$(\d+)/', $row['effect'], $m)) {
                $value = (int)$m[1];
            }
            $entry['value'] = $value;
            if (stripos($row['effect'], '+1 buy') !== false) {
                $entry['extra_buy'] = true;
            }
            $entry['description'] = "\${$value}" . ($cost_val > 0 ? " (\${$cost_val})" : '');
            if ($row['tier'] > 0) {
                $market_counts[$id] = $row['count'];
            }
            break;

        case 'mission':
            $entry['cost'] = 0;
            $entry['requirements'] = parse_mission_requirements($row['requirements'], $emoji_to_icon);
            [$stars, $value, $gems] = parse_mission_rewards($row['effect']);
            // Heist uses "💎1-3" — just needs gems > 0 as a flag
            if ($gems === 0 && preg_match('/💎/u', $row['effect'])) $gems = 1;
            $entry['stars'] = $stars;
            $entry['value'] = $value;
            $entry['gems'] = $gems;
            $entry['always_available'] = stripos($row['special'], 'permanently available') !== false
                                      || stripos($row['special'], 'always available') !== false;
            if (preg_match('/\+\s*1\s*(mission|op)/i', $row['effect'])) {
                $entry['extra_mission'] = true;
            }
            if (preg_match('/\+\s*1\s*buy/i', $row['effect'])) {
                $entry['extra_buy'] = true;
            }
            $req_display = $row['requirements'];
            $reward_parts = [];
            if ($stars > 0) $reward_parts[] = "{$stars}⭐";
            if ($value > 0) $reward_parts[] = "\${$value}/turn";
            if ($entry['extra_mission'] ?? false) $reward_parts[] = '+1 op/turn';
            if ($entry['extra_buy'] ?? false) $reward_parts[] = '+1 buy/turn';
            $reward_str = implode(' ', $reward_parts);
            $entry['description'] = "Requires: {$req_display} — Reward: {$reward_str}";
            if ($entry['always_available']) {
                $entry['description'] .= '. Always available.';
            }
            if (!$entry['always_available'] && $row['tier'] >= 1 && $row['tier'] <= 3) {
                $mission_counts[$row['tier']][$id] = $row['count'];
            }
            break;
    }

    $cards[$id] = $entry;
}

// --- Generate PHP output ---

$output = "<?php\n\n";
$output .= "// AUTO-GENERATED by convert_cards.php — do not edit manually\n";
$output .= "// Source: docs/cards.csv\n\n";

$output .= "// Icon constants\n";
$output .= "define('ICON_DRIVE', 'drive');\n";
$output .= "define('ICON_MUSCLE', 'muscle');\n";
$output .= "define('ICON_DISGUISE', 'disguise');\n";
$output .= "define('ICON_KEY', 'key');\n\n";

$output .= "// Card type constants\n";
$output .= "define('TYPE_MONEY', 'money');\n";
$output .= "define('TYPE_AGENT', 'agent');\n";
$output .= "define('TYPE_TECH', 'tech');\n";
$output .= "define('TYPE_PLOT', 'plot');\n";
$output .= "define('TYPE_BASE', 'base');\n";
$output .= "define('TYPE_MISSION', 'mission');\n\n";

$output .= "function get_icon_label(string \$icon): string {\n";
$output .= "    \$map = [\n";
$output .= "        ICON_DRIVE => '🚘',\n";
$output .= "        ICON_MUSCLE => '💪',\n";
$output .= "        ICON_DISGUISE => '🥸',\n";
$output .= "        ICON_KEY => '🔑',\n";
$output .= "    ];\n";
$output .= "    return \$map[\$icon] ?? \$icon;\n";
$output .= "}\n\n";

// Generate catalog
$output .= "function get_card_catalog(): array {\n";
$output .= "    return [\n";
foreach ($cards as $id => $card) {
    $output .= "        " . var_export($id, true) . " => [\n";
    foreach ($card as $k => $v) {
        $output .= "            " . var_export($k, true) . " => " . export_value($v) . ",\n";
    }
    $output .= "        ],\n";
}
$output .= "    ];\n";
$output .= "}\n\n";

// Generate build_market_deck
$output .= "function build_market_deck(): array {\n";
$output .= "    \$deck = [];\n";
$output .= "    \$market_cards = [\n";
foreach ($market_counts as $id => $count) {
    $output .= "        " . var_export($id, true) . " => {$count},\n";
}
$output .= "    ];\n";
$output .= "    foreach (\$market_cards as \$id => \$count) {\n";
$output .= "        for (\$i = 0; \$i < \$count; \$i++) {\n";
$output .= "            \$deck[] = \$id;\n";
$output .= "        }\n";
$output .= "    }\n";
$output .= "    shuffle(\$deck);\n";
$output .= "    return \$deck;\n";
$output .= "}\n\n";

// Generate build_mission_decks
$output .= "function build_mission_decks(): array {\n";
$output .= "    \$missions = [\n";
foreach ($mission_counts as $tier => $missions) {
    $output .= "        {$tier} => [\n";
    foreach ($missions as $id => $count) {
        $output .= "            " . var_export($id, true) . " => {$count},\n";
    }
    $output .= "        ],\n";
}
$output .= "    ];\n";
$output .= "    \$decks = [];\n";
$output .= "    foreach (\$missions as \$tier => \$cards) {\n";
$output .= "        \$deck = [];\n";
$output .= "        foreach (\$cards as \$id => \$count) {\n";
$output .= "            for (\$i = 0; \$i < \$count; \$i++) {\n";
$output .= "                \$deck[] = \$id;\n";
$output .= "            }\n";
$output .= "        }\n";
$output .= "        shuffle(\$deck);\n";
$output .= "        \$decks[\$tier] = \$deck;\n";
$output .= "    }\n";
$output .= "    return \$decks;\n";
$output .= "}\n\n";

// Generate get_starter_deck
$output .= "function get_starter_deck(): array {\n";
$output .= "    \$deck = [];\n";
$output .= "    for (\$i = 0; \$i < 6; \$i++) \$deck[] = 'money_1';\n";
$output .= "    \$deck[] = 'muscle';\n";
$output .= "    \$deck[] = 'muscle';\n";
$output .= "    \$deck[] = 'shadow';\n";
$output .= "    \$deck[] = 'red_tape';\n";
$output .= "    shuffle(\$deck);\n";
$output .= "    return \$deck;\n";
$output .= "}\n";

file_put_contents($out_path, $output);
echo "Generated: $out_path\n";
echo count($cards) . " cards written.\n";

// Show summary
$types = [];
foreach ($cards as $c) {
    $types[$c['type']] = ($types[$c['type']] ?? 0) + 1;
}
foreach ($types as $t => $n) {
    echo "  {$t}: {$n}\n";
}

function export_value($v): string {
    if (is_null($v)) return 'null';
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v) || is_float($v)) return (string)$v;
    if (is_string($v)) return var_export($v, true);
    if (is_array($v)) {
        // Check if sequential
        if (array_values($v) === $v) {
            if (empty($v)) return '[]';
            $items = array_map('export_value', $v);
            $inline = '[' . implode(', ', $items) . ']';
            if (strlen($inline) < 100) return $inline;
            return "[\n" . implode(",\n", array_map(fn($i) => "                $i", $items)) . "\n            ]";
        }
        // Associative
        $items = [];
        foreach ($v as $k => $val) {
            $items[] = var_export($k, true) . ' => ' . export_value($val);
        }
        return '[' . implode(', ', $items) . ']';
    }
    return var_export($v, true);
}
