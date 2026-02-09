<?php
require_once __DIR__ . '/cards.php';
require_once __DIR__ . '/game_end.php';

function find_player_index(array &$game, string $token): int {
    foreach ($game['players'] as $i => $p) {
        if ($p['token'] === $token) return $i;
    }
    return -1;
}

function player_draw_cards(array &$player, int $count): void {
    for ($i = 0; $i < $count; $i++) {
        if (empty($player['deck'])) {
            if (empty($player['discard'])) break;
            $player['deck'] = $player['discard'];
            $player['discard'] = [];
            shuffle($player['deck']);
        }
        if (!empty($player['deck'])) {
            $player['hand'][] = array_shift($player['deck']);
        }
    }
}

function remove_from_array(array &$arr, string $value): bool {
    $idx = array_search($value, $arr);
    if ($idx === false) return false;
    array_splice($arr, $idx, 1);
    return true;
}

function action_play_money(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $catalog = get_card_catalog();
    if (!isset($catalog[$card_id])) {
        return ['ok' => false, 'error' => 'Unknown card'];
    }
    $card = $catalog[$card_id];
    // Allow playing money cards and completed mission cards (which have a value)
    $is_money = $card['type'] === TYPE_MONEY;
    $is_valued_mission = $card['type'] === TYPE_MISSION && ($card['value'] ?? 0) > 0;
    if (!$is_money && !$is_valued_mission) {
        return ['ok' => false, 'error' => 'Not a playable money card'];
    }
    if (!remove_from_array($game['players'][$pi]['hand'], $card_id)) {
        return ['ok' => false, 'error' => 'Card not in hand'];
    }
    $value = $card['value'] ?? 0;
    $game['players'][$pi]['play_area'][] = $card_id;
    $game['players'][$pi]['money'] += $value;
    $game['log'][] = $game['players'][$pi]['name'] . " played {$card['name']} (+\${$value})";
    return ['ok' => true];
}

function action_play_agent(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $base_idx = $params['base_index'] ?? -1;
    $catalog = get_card_catalog();

    if (!isset($catalog[$card_id]) || $catalog[$card_id]['type'] !== TYPE_AGENT) {
        return ['ok' => false, 'error' => 'Not an agent card'];
    }
    if (!remove_from_array($game['players'][$pi]['hand'], $card_id)) {
        return ['ok' => false, 'error' => 'Card not in hand'];
    }
    if (!isset($game['players'][$pi]['bases'][$base_idx])) {
        // Put card back
        $game['players'][$pi]['hand'][] = $card_id;
        return ['ok' => false, 'error' => 'Invalid base'];
    }
    if ($game['players'][$pi]['bases'][$base_idx]['agent'] !== null) {
        $game['players'][$pi]['hand'][] = $card_id;
        return ['ok' => false, 'error' => 'Base already occupied'];
    }
    $game['players'][$pi]['bases'][$base_idx]['agent'] = $card_id;
    $game['log'][] = $game['players'][$pi]['name'] . " deployed {$catalog[$card_id]['name']} to base " . ($base_idx + 1);
    return ['ok' => true];
}

function action_equip_tech(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $base_idx = $params['base_index'] ?? -1;
    $catalog = get_card_catalog();

    if (!isset($catalog[$card_id]) || $catalog[$card_id]['type'] !== TYPE_TECH) {
        return ['ok' => false, 'error' => 'Not a tech card'];
    }
    if (!remove_from_array($game['players'][$pi]['hand'], $card_id)) {
        return ['ok' => false, 'error' => 'Card not in hand'];
    }
    $base = &$game['players'][$pi]['bases'][$base_idx] ?? null;
    if (!$base || $base['agent'] === null) {
        $game['players'][$pi]['hand'][] = $card_id;
        return ['ok' => false, 'error' => 'No agent at this base'];
    }
    $agent_card = $catalog[$base['agent']];
    $max_tech = $agent_card['max_tech'] ?? 0;
    if (count($base['tech']) >= $max_tech) {
        $game['players'][$pi]['hand'][] = $card_id;
        return ['ok' => false, 'error' => "Agent can only hold {$max_tech} tech"];
    }
    $base['tech'][] = $card_id;
    $game['log'][] = $game['players'][$pi]['name'] . " equipped {$catalog[$card_id]['name']} to {$agent_card['name']}";
    return ['ok' => true];
}

function action_play_plot(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $catalog = get_card_catalog();

    if (!isset($catalog[$card_id]) || $catalog[$card_id]['type'] !== TYPE_PLOT) {
        return ['ok' => false, 'error' => 'Not a plot card'];
    }
    if (!remove_from_array($game['players'][$pi]['hand'], $card_id)) {
        return ['ok' => false, 'error' => 'Card not in hand'];
    }

    $effect = $catalog[$card_id]['effect'] ?? 'none';

    switch ($effect) {
        case 'none':
            // Red Tape - does nothing
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Red Tape (does nothing)";
            break;

        case 'draw2':
            // Para-drop
            $game['players'][$pi]['play_area'][] = $card_id;
            player_draw_cards($game['players'][$pi], 2);
            $game['log'][] = $game['players'][$pi]['name'] . " played Para-drop and drew 2 cards";
            break;

        case 'trash':
            // Burn Notice - trash a card specified by target_card and target_area
            $target_card = $params['target_card'] ?? '';
            $target_area = $params['target_area'] ?? '';
            if (!$target_card || !$target_area) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Must specify target_card and target_area'];
            }
            $valid_areas = ['hand', 'play_area', 'discard'];
            if (!in_array($target_area, $valid_areas)) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Invalid target area'];
            }
            if (!remove_from_array($game['players'][$pi][$target_area], $target_card)) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Target card not found in ' . $target_area];
            }
            // Card is trashed (removed from game entirely)
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Burn Notice, trashing {$catalog[$target_card]['name']} from {$target_area}";
            break;

        case 'paperwork':
            // Paperwork - every other player gains a Red Tape in their discard
            $game['players'][$pi]['play_area'][] = $card_id;
            foreach ($game['players'] as $oi => &$other) {
                if ($oi !== $pi) {
                    $other['discard'][] = 'red_tape';
                }
            }
            unset($other);
            $game['log'][] = $game['players'][$pi]['name'] . " played Paperwork — every other player gains a Red Tape!";
            break;

        case 'multitask':
            // Multitasking - allows an additional mission this turn
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['players'][$pi]['extra_missions'] = ($game['players'][$pi]['extra_missions'] ?? 0) + 1;
            $game['log'][] = $game['players'][$pi]['name'] . " played Multitasking — may complete an additional mission this turn!";
            break;

        case 'backup':
            // Got Your Back! - play an agent from hand as backup
            $agent_card_id = $params['agent_card_id'] ?? '';
            if (!$agent_card_id || !isset($catalog[$agent_card_id]) || $catalog[$agent_card_id]['type'] !== TYPE_AGENT) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Must specify a valid agent card'];
            }
            if (!remove_from_array($game['players'][$pi]['hand'], $agent_card_id)) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Agent not in hand'];
            }
            $game['players'][$pi]['backup_agent'] = $agent_card_id;
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['players'][$pi]['play_area'][] = $agent_card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Got Your Back! with {$catalog[$agent_card_id]['name']} as backup";
            break;
    }

    return ['ok' => true];
}

function action_buy_card(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $slot = $params['slot'] ?? -1;
    $catalog = get_card_catalog();

    // Always-available cards
    $always_available = array_filter($catalog, fn($c) => ($c['always_available'] ?? false) && $c['type'] === TYPE_AGENT);
    if (isset($always_available[$card_id])) {
        $cost = $catalog[$card_id]['cost'];
        if ($game['players'][$pi]['money'] < $cost) {
            return ['ok' => false, 'error' => 'Not enough money'];
        }
        $game['players'][$pi]['money'] -= $cost;
        $game['players'][$pi]['discard'][] = $card_id;
        $game['log'][] = $game['players'][$pi]['name'] . " bought {$catalog[$card_id]['name']} for \${$cost}";
        return ['ok' => true];
    }

    // Buy from marketplace
    if ($slot < 0 || $slot >= count($game['marketplace']) || $game['marketplace'][$slot] === null) {
        return ['ok' => false, 'error' => 'Invalid marketplace slot'];
    }
    $market_card = $game['marketplace'][$slot];
    if ($market_card !== $card_id) {
        return ['ok' => false, 'error' => 'Card mismatch'];
    }
    $cost = $catalog[$card_id]['cost'] ?? 0;
    if ($game['players'][$pi]['money'] < $cost) {
        return ['ok' => false, 'error' => 'Not enough money'];
    }
    $game['players'][$pi]['money'] -= $cost;
    $game['players'][$pi]['discard'][] = $card_id;
    $game['marketplace'][$slot] = null; // Empty slot, refilled at end of turn
    $game['log'][] = $game['players'][$pi]['name'] . " bought {$catalog[$card_id]['name']} for \${$cost}";
    return ['ok' => true];
}

function get_base_cost(int $extra_count): int {
    // $5, $8, $12, ...
    $costs = [5, 8, 12];
    if ($extra_count < count($costs)) return $costs[$extra_count];
    // Beyond defined costs, keep incrementing
    return $costs[count($costs) - 1] + ($extra_count - count($costs) + 1) * 4;
}

function action_buy_base(array &$game, int $pi, array $params): array {
    $extra = $game['players'][$pi]['extra_base_count'];
    $cost = get_base_cost($extra);
    if ($game['players'][$pi]['money'] < $cost) {
        return ['ok' => false, 'error' => "Not enough money (need \${$cost})"];
    }
    $game['players'][$pi]['money'] -= $cost;
    $game['players'][$pi]['extra_base_count']++;
    // Extra bases are always Hideaways
    $game['players'][$pi]['bases'][] = [
        'type' => 'hideaway',
        'agent' => null,
        'tech' => [],
    ];
    $game['log'][] = $game['players'][$pi]['name'] . " bought a new Hideaway for \${$cost}";
    return ['ok' => true];
}

function refill_marketplace(array &$game): void {
    // Pad marketplace to 5 slots
    while (count($game['marketplace']) < 5) {
        $game['marketplace'][] = null;
    }
    foreach ($game['marketplace'] as $i => $card) {
        if ($card === null && !empty($game['market_deck'])) {
            $drawn = array_shift($game['market_deck']);
            // Dedup: if this card already exists in the marketplace, place on top and draw again
            $existing = array_filter($game['marketplace'], fn($c) => $c === $drawn);
            if (!empty($existing)) {
                // "Place on top" — the slot keeps same card id, effectively stacking
                // Put drawn card on top of the matching slot, then draw another for this empty slot
                $game['marketplace'][$i] = $drawn;
                // Try to draw a different card for a new pass
                // Actually per spec: "place the new card on top of it and draw again"
                // So the duplicate goes onto the existing slot (no visible change), and we re-draw for this slot
                // Find the existing slot that matches
                foreach ($game['marketplace'] as $j => $mc) {
                    if ($j !== $i && $mc === $drawn) {
                        // Stack: put it back conceptually, re-draw
                        $game['marketplace'][$i] = null;
                        // Put the drawn card under the existing one (or just try next card)
                        // Simplest: keep trying to draw unique cards, give up after deck exhausted
                        $attempts = 0;
                        while (!empty($game['market_deck']) && $attempts < count($game['market_deck']) + 1) {
                            $next = array_shift($game['market_deck']);
                            $already_in = in_array($next, array_filter($game['marketplace'], fn($c) => $c !== null));
                            if ($already_in) {
                                // Put at bottom of deck and try again
                                $game['market_deck'][] = $next;
                                $attempts++;
                            } else {
                                $game['marketplace'][$i] = $next;
                                break;
                            }
                        }
                        // If we couldn't find a unique card, just place whatever
                        if ($game['marketplace'][$i] === null && !empty($game['market_deck'])) {
                            $game['marketplace'][$i] = array_shift($game['market_deck']);
                        }
                        break;
                    }
                }
            } else {
                $game['marketplace'][$i] = $drawn;
            }
        }
    }
}

function action_refresh_market(array &$game, int $pi, array $params): array {
    if ($game['players'][$pi]['money'] < 2) {
        return ['ok' => false, 'error' => 'Not enough money (need $2)'];
    }
    $game['players'][$pi]['money'] -= 2;
    // Discard current marketplace cards (removed from game)
    $game['marketplace'] = [null, null, null, null, null];
    // Refill with dedup logic
    refill_marketplace($game);
    $game['log'][] = $game['players'][$pi]['name'] . " refreshed the marketplace for \$2";
    return ['ok' => true];
}

/**
 * Resolve icons for a card, with auto-resolution of choices to satisfy mission requirements.
 * $choices is an array of explicit player choices (for manual override).
 * Returns array of resolved icon strings.
 */
function resolve_icons(string $card_id, array $choices, array $catalog): array {
    $card = $catalog[$card_id];
    $icons = [];
    $choice_idx = 0;
    foreach ($card['icons'] as $icon) {
        if (is_array($icon)) {
            $chosen = $choices[$choice_idx] ?? $icon[0];
            if (!in_array($chosen, $icon)) {
                $chosen = $icon[0];
            }
            $icons[] = $chosen;
            $choice_idx++;
        } else {
            $icons[] = $icon;
        }
    }
    return $icons;
}

/**
 * Collect all cards involved in a mission attempt (agent, tech, backup) with their choice slots.
 * Returns array of ['card_id' => ..., 'icons' => [...]] where choice icons are arrays.
 */
function collect_mission_cards(array $base, ?string $backup, array $catalog): array {
    $cards = [];
    if ($base['agent']) {
        $cards[] = $base['agent'];
    }
    foreach ($base['tech'] as $t) {
        $cards[] = $t;
    }
    if ($backup) {
        $cards[] = $backup;
    }
    return $cards;
}

/**
 * Auto-resolve icon choices to satisfy mission requirements.
 * Uses backtracking to find a valid assignment.
 */
function auto_resolve_choices(array $requirements, array $card_ids, array $catalog): ?array {
    // Gather all icon slots: fixed icons and choice slots
    $slots = []; // [{card_idx, icon_idx, options: string|array}]
    foreach ($card_ids as $ci => $card_id) {
        $card = $catalog[$card_id];
        foreach ($card['icons'] as $ii => $icon) {
            $slots[] = ['card_idx' => $ci, 'icon_idx' => $ii, 'options' => $icon];
        }
    }

    // Try all combinations of choices via backtracking
    $choice_slots = [];
    $fixed_icons = [];
    foreach ($slots as $si => $slot) {
        if (is_array($slot['options'])) {
            $choice_slots[] = $si;
        } else {
            $fixed_icons[] = $slot['options'];
        }
    }

    // If no choices, just check directly
    if (empty($choice_slots)) {
        if (check_requirements($requirements, $fixed_icons)) {
            return []; // no choices needed
        }
        return null;
    }

    // Try all combinations
    $num_choices = count($choice_slots);
    $combo = array_fill(0, $num_choices, 0);

    $total = 1;
    foreach ($choice_slots as $si) {
        $total *= count($slots[$si]['options']);
    }

    for ($attempt = 0; $attempt < $total; $attempt++) {
        $icons = $fixed_icons;
        $resolved = [];
        foreach ($choice_slots as $ci => $si) {
            $opt_idx = $combo[$ci];
            $chosen = $slots[$si]['options'][$opt_idx];
            $icons[] = $chosen;
            $resolved[] = [
                'card_idx' => $slots[$si]['card_idx'],
                'icon_idx' => $slots[$si]['icon_idx'],
                'chosen' => $chosen,
            ];
        }

        if (check_requirements($requirements, $icons)) {
            // Build choices structure by card
            $choices_by_card = [];
            foreach ($resolved as $r) {
                $choices_by_card[$r['card_idx']][$r['icon_idx']] = $r['chosen'];
            }
            return $choices_by_card;
        }

        // Increment combo
        for ($ci = $num_choices - 1; $ci >= 0; $ci--) {
            $combo[$ci]++;
            if ($combo[$ci] < count($slots[$choice_slots[$ci]]['options'])) break;
            $combo[$ci] = 0;
        }
    }

    return null; // no valid assignment found
}

function check_requirements(array $requirements, array $available): bool {
    $avail = $available; // copy
    foreach ($requirements as $req) {
        if ($req === 'any') {
            if (empty($avail)) return false;
            array_shift($avail);
        } else {
            $idx = array_search($req, $avail);
            if ($idx === false) return false;
            array_splice($avail, $idx, 1);
        }
    }
    return true;
}

function action_complete_mission(array &$game, int $pi, array $params): array {
    $mission_id = $params['mission_id'] ?? '';
    $base_idx = $params['base_index'] ?? -1;
    $catalog = get_card_catalog();

    if (!isset($catalog[$mission_id]) || $catalog[$mission_id]['type'] !== TYPE_MISSION) {
        return ['ok' => false, 'error' => 'Not a valid mission'];
    }

    $mission = $catalog[$mission_id];

    // Check mission limit (default 1 per turn, +1 per Multitasking played)
    $missions_allowed = 1 + ($game['players'][$pi]['extra_missions'] ?? 0);
    $missions_completed = $game['players'][$pi]['missions_this_turn'] ?? 0;
    if ($missions_completed >= $missions_allowed) {
        return ['ok' => false, 'error' => 'No more missions allowed this turn'];
    }

    // Find mission in grid or check if it's always available
    $found_tier = null;
    $found_idx = null;
    $is_always = $catalog[$mission_id]['always_available'] ?? false;

    if (!$is_always) {
        foreach ($game['mission_grid'] as $tier => $missions) {
            $idx = array_search($mission_id, $missions);
            if ($idx !== false) {
                $found_tier = $tier;
                $found_idx = $idx;
                break;
            }
        }
        if ($found_tier === null) {
            return ['ok' => false, 'error' => 'Mission not available in grid'];
        }
    }

    // Validate base has an agent
    if (!isset($game['players'][$pi]['bases'][$base_idx]) || $game['players'][$pi]['bases'][$base_idx]['agent'] === null) {
        return ['ok' => false, 'error' => 'No agent at that base'];
    }

    $base = &$game['players'][$pi]['bases'][$base_idx];
    $backup = $game['players'][$pi]['backup_agent'] ?? null;

    // Collect all card IDs involved
    $card_ids = collect_mission_cards($base, $backup, $catalog);

    // Auto-resolve icon choices
    $resolution = auto_resolve_choices($mission['requirements'], $card_ids, $catalog);
    if ($resolution === null) {
        return ['ok' => false, 'error' => 'Agent skills do not meet mission requirements'];
    }

    // Success! Complete the mission
    $agent_id = $base['agent'];
    $game['players'][$pi]['discard'][] = $agent_id;
    foreach ($base['tech'] as $t) {
        $game['players'][$pi]['discard'][] = $t;
    }
    $base['agent'] = null;
    $base['tech'] = [];

    // Clear backup agent (it was already in play_area)
    if ($backup) {
        $game['players'][$pi]['backup_agent'] = null;
    }

    // Remove mission from grid
    if (!$is_always && $found_tier !== null) {
        array_splice($game['mission_grid'][$found_tier], $found_idx, 1);
    }

    // Add mission card to discard (it has stars and/or value)
    $game['players'][$pi]['discard'][] = $mission_id;

    // Track missions completed this turn
    $game['players'][$pi]['missions_this_turn'] = $missions_completed + 1;

    $game['log'][] = $game['players'][$pi]['name'] . " completed mission {$mission['name']}!";

    // Check for game end trigger
    check_game_end($game);

    return ['ok' => true];
}

function action_vacate_base(array &$game, int $pi, array $params): array {
    $base_idx = $params['base_index'] ?? -1;
    $catalog = get_card_catalog();

    if (!isset($game['players'][$pi]['bases'][$base_idx])) {
        return ['ok' => false, 'error' => 'Invalid base'];
    }
    $base = &$game['players'][$pi]['bases'][$base_idx];
    if ($base['agent'] === null) {
        return ['ok' => false, 'error' => 'Base is already empty'];
    }

    $agent_name = $catalog[$base['agent']]['name'] ?? $base['agent'];
    $game['players'][$pi]['discard'][] = $base['agent'];
    foreach ($base['tech'] as $t) {
        $game['players'][$pi]['discard'][] = $t;
    }
    $base['agent'] = null;
    $base['tech'] = [];
    $game['log'][] = $game['players'][$pi]['name'] . " vacated base " . ($base_idx + 1) . " ({$agent_name})";
    return ['ok' => true];
}

function action_end_turn(array &$game, int $pi, array $params): array {
    $p = &$game['players'][$pi];

    // Discard play area
    foreach ($p['play_area'] as $card) {
        $p['discard'][] = $card;
    }
    $p['play_area'] = [];
    // Discard remaining hand
    foreach ($p['hand'] as $card) {
        $p['discard'][] = $card;
    }
    $p['hand'] = [];
    $p['money'] = 0;
    $p['backup_agent'] = null;
    $p['extra_missions'] = 0;
    $p['missions_this_turn'] = 0;

    // Refill marketplace (with dedup: if drawn card matches one already in market, stack and draw again)
    refill_marketplace($game);

    // Refill mission grid
    foreach ([1, 2, 3] as $tier) {
        while (count($game['mission_grid'][$tier]) < 3 && count($game['mission_decks'][$tier]) > 0) {
            $game['mission_grid'][$tier][] = array_shift($game['mission_decks'][$tier]);
        }
    }

    // Draw 5 cards
    player_draw_cards($p, 5);

    // Check end conditions
    check_game_end($game);

    if (is_game_over($game)) {
        finalize_game($game);
        return ['ok' => true];
    }

    // Advance to next player
    $game['current_player'] = ($game['current_player'] + 1) % count($game['players']);
    $game['turn_number']++;
    $next_name = $game['players'][$game['current_player']]['name'];
    $game['log'][] = "It's now {$next_name}'s turn.";

    return ['ok' => true];
}

function process_action(array &$game, string $token, string $action, array $params): array {
    $pi = find_player_index($game, $token);
    if ($pi < 0) return ['ok' => false, 'error' => 'Player not found'];

    if ($game['ended'] ?? false) {
        return ['ok' => false, 'error' => 'Game is over'];
    }

    if ($game['current_player'] !== $pi) {
        return ['ok' => false, 'error' => 'Not your turn'];
    }

    $result = match($action) {
        'play_money' => action_play_money($game, $pi, $params),
        'play_agent' => action_play_agent($game, $pi, $params),
        'equip_tech' => action_equip_tech($game, $pi, $params),
        'play_plot' => action_play_plot($game, $pi, $params),
        'buy_card' => action_buy_card($game, $pi, $params),
        'buy_base' => action_buy_base($game, $pi, $params),
        'refresh_market' => action_refresh_market($game, $pi, $params),
        'complete_mission' => action_complete_mission($game, $pi, $params),
        'vacate_base' => action_vacate_base($game, $pi, $params),
        'end_turn' => action_end_turn($game, $pi, $params),
        default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
    };

    if ($result['ok'] ?? false) {
        $game['version']++;
    }

    return $result;
}
