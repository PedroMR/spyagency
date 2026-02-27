<?php
require_once __DIR__ . '/cards.php';
require_once __DIR__ . '/game_end.php';

function normalize_base(array &$player): void {
    $base = $player['base'] ?? 'safe_house';
    if (is_string($base)) {
        $player['base'] = ['card' => $base, 'agent' => null, 'tech' => []];
    }
}

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

/**
 * Spend a cost using money first, then gems for the remainder.
 * Returns true if affordable, false otherwise. Deducts automatically.
 */
function spend_cost(array &$player, int $cost, array &$log_parts): bool {
    $money = $player['money'];
    $gems = $player['gems'] ?? 0;
    if ($money + $gems < $cost) return false;
    $gems_needed = max(0, $cost - $money);
    $money_spent = $cost - $gems_needed;
    $player['money'] -= $money_spent;
    $player['gems'] -= $gems_needed;
    if ($gems_needed > 0) {
        $log_parts[] = "using {$gems_needed} gem(s)";
    }
    return true;
}

function action_play_money(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $catalog = get_card_catalog();
    if (!isset($catalog[$card_id])) {
        return ['ok' => false, 'error' => 'Unknown card'];
    }
    $card = $catalog[$card_id];
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
    $log_msg = $game['players'][$pi]['name'] . " played {$card['name']} (+\${$value})";
    // Extra buy effect
    if ($card['extra_buy'] ?? false) {
        $game['players'][$pi]['extra_buys'] = ($game['players'][$pi]['extra_buys'] ?? 0) + 1;
        $log_msg .= ' (+1 Buy)';
    }
    $game['log'][] = $log_msg;
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
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Red Tape (does nothing)";
            break;

        case 'draw2':
            $game['players'][$pi]['play_area'][] = $card_id;
            player_draw_cards($game['players'][$pi], 2);
            $game['log'][] = $game['players'][$pi]['name'] . " played Para-drop and drew 2 cards";
            break;

        case 'trash':
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
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Burn Notice, trashing {$catalog[$target_card]['name']} from {$target_area}";
            break;

        case 'paperwork':
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['players'][$pi]['money'] += 1;
            $game['log'][] = $game['players'][$pi]['name'] . " played Paperwork (+\$1) — attacking all opponents!";

            // Build list of defenders (all opponents)
            $defenders = [];
            for ($oi = 0; $oi < count($game['players']); $oi++) {
                if ($oi !== $pi) $defenders[] = $oi;
            }

            if (count($defenders) > 0) {
                $game['pending_attack'] = [
                    'attacker' => $pi,
                    'card' => 'paperwork',
                    'defenders' => $defenders,
                    'responses' => [],
                ];
            }
            break;

        case 'burglary':
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['players'][$pi]['money'] += 2;
            $game['log'][] = $game['players'][$pi]['name'] . " played Burglary (+\$2) — attacking all opponents!";

            // Build list of defenders (all opponents)
            $defenders = [];
            for ($oi = 0; $oi < count($game['players']); $oi++) {
                if ($oi !== $pi) $defenders[] = $oi;
            }

            if (count($defenders) > 0) {
                $game['pending_attack'] = [
                    'attacker' => $pi,
                    'card' => 'burglary',
                    'defenders' => $defenders,
                    'responses' => [],
                ];
            }
            break;

        case 'multitask':
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['players'][$pi]['extra_missions'] = ($game['players'][$pi]['extra_missions'] ?? 0) + 1;
            $game['log'][] = $game['players'][$pi]['name'] . " played Multitasking — may complete an additional op this turn!";
            break;

        case 'backup':
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

        case 'training':
            // Training Procedure: trash an agent from hand, play area, discard, or base
            $trash_agent = $params['trash_agent'] ?? '';
            $trash_from = $params['trash_from'] ?? 'hand'; // 'hand', 'play_area', 'discard', or 'base'
            $gain_agent = $params['gain_agent'] ?? '';
            $gain_slot = $params['gain_slot'] ?? -1;
            if (!$trash_agent || !isset($catalog[$trash_agent]) || $catalog[$trash_agent]['type'] !== TYPE_AGENT) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Must specify an agent to trash'];
            }
            $valid_areas = ['hand', 'play_area', 'discard', 'base'];
            if (!in_array($trash_from, $valid_areas)) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Invalid trash area'];
            }

            // Remove agent from the chosen area
            if ($trash_from === 'base') {
                normalize_base($game['players'][$pi]);
                $base = &$game['players'][$pi]['base'];
                if ($base['agent'] !== $trash_agent) {
                    $game['players'][$pi]['hand'][] = $card_id;
                    return ['ok' => false, 'error' => 'Agent not found in base'];
                }
                // Discard any tech attached to the base agent
                foreach ($base['tech'] as $tid) {
                    $game['players'][$pi]['discard'][] = $tid;
                }
                $base['agent'] = null;
                $base['tech'] = [];
                unset($base);
            } else {
                if (!remove_from_array($game['players'][$pi][$trash_from], $trash_agent)) {
                    $game['players'][$pi]['hand'][] = $card_id;
                    return ['ok' => false, 'error' => 'Agent not found in ' . str_replace('_', ' ', $trash_from)];
                }
            }

            $trash_cost = $catalog[$trash_agent]['cost'] ?? 0;
            $max_cost = $trash_cost + 3;

            // Gain agent: from marketplace slot or always-available
            if (!$gain_agent || !isset($catalog[$gain_agent]) || $catalog[$gain_agent]['type'] !== TYPE_AGENT) {
                // Restore agent (put back in discard as best-effort)
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Must specify a valid agent to gain'];
            }
            $gain_cost = $catalog[$gain_agent]['cost'] ?? 0;
            if ($gain_cost > $max_cost) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => "Agent costs \${$gain_cost}, max is \${$max_cost}"];
            }

            // Check source: always-available or marketplace slot
            $always_available = ($catalog[$gain_agent]['always_available'] ?? false);
            if ($always_available) {
                // OK, infinite supply
            } elseif ($gain_slot >= 0 && $gain_slot < count($game['marketplace']) && !empty($game['marketplace'][$gain_slot]) && $game['marketplace'][$gain_slot][0] === $gain_agent) {
                array_shift($game['marketplace'][$gain_slot]);
            } else {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'Agent not available in marketplace'];
            }

            // Trash the agent (removed from game), gain the new one directly to hand
            $game['players'][$pi]['hand'][] = $gain_agent;
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Training Procedure: trashed {$catalog[$trash_agent]['name']}, gained {$catalog[$gain_agent]['name']}";
            break;
    }

    return ['ok' => true];
}

function action_buy_card(array &$game, int $pi, array $params): array {
    $card_id = $params['card_id'] ?? '';
    $slot = $params['slot'] ?? -1;
    $catalog = get_card_catalog();

    // Buy limit: 1 + extra buys per turn
    $buy_limit = 1 + ($game['players'][$pi]['extra_buys'] ?? 0);
    if (($game['players'][$pi]['buys_this_turn'] ?? 0) >= $buy_limit) {
        return ['ok' => false, 'error' => 'Already bought maximum cards this turn'];
    }

    // Always-available cards
    $always_available = array_filter($catalog, fn($c) => ($c['always_available'] ?? false) && $c['type'] === TYPE_AGENT);
    if (isset($always_available[$card_id])) {
        $cost = $catalog[$card_id]['cost'];
        $log_parts = [];
        if (!spend_cost($game['players'][$pi], $cost, $log_parts)) {
            return ['ok' => false, 'error' => 'Not enough money/gems'];
        }
        $game['players'][$pi]['hand'][] = $card_id;
        $game['players'][$pi]['buys_this_turn'] = ($game['players'][$pi]['buys_this_turn'] ?? 0) + 1;
        $msg = $game['players'][$pi]['name'] . " bought {$catalog[$card_id]['name']} for \${$cost}";
        if (!empty($log_parts)) $msg .= ' (' . implode(', ', $log_parts) . ')';
        $game['log'][] = $msg;
        return ['ok' => true];
    }

    // Buy from marketplace (stacked slots)
    if ($slot < 0 || $slot >= count($game['marketplace']) || empty($game['marketplace'][$slot])) {
        return ['ok' => false, 'error' => 'Invalid marketplace slot'];
    }
    $market_card = $game['marketplace'][$slot][0];
    if ($market_card !== $card_id) {
        return ['ok' => false, 'error' => 'Card mismatch'];
    }
    $cost = $catalog[$card_id]['cost'] ?? 0;
    $log_parts = [];
    if (!spend_cost($game['players'][$pi], $cost, $log_parts)) {
        return ['ok' => false, 'error' => 'Not enough money/gems'];
    }
    $primed = $catalog[$card_id]['primed'] ?? false;
    if ($primed) {
        $game['players'][$pi]['hand'][] = $card_id;
        $log_parts[] = 'Primed — goes to hand';
    } else {
        $game['players'][$pi]['discard'][] = $card_id;
    }
    array_shift($game['marketplace'][$slot]); // Remove top card from stack
    $game['players'][$pi]['buys_this_turn'] = ($game['players'][$pi]['buys_this_turn'] ?? 0) + 1;
    $msg = $game['players'][$pi]['name'] . " bought {$catalog[$card_id]['name']} for \${$cost}";
    if (!empty($log_parts)) $msg .= ' (' . implode(', ', $log_parts) . ')';
    $game['log'][] = $msg;
    return ['ok' => true];
}

function marketplace_top(array $slot): ?string {
    return !empty($slot) ? $slot[0] : null;
}

function refill_marketplace(array &$game): void {
    // Ensure 7 slots
    while (count($game['marketplace']) < 7) {
        $game['marketplace'][] = [];
    }
    foreach ($game['marketplace'] as $i => $stack) {
        if (empty($stack)) {
            // Try to fill this empty slot
            while (!empty($game['market_deck'])) {
                $drawn = array_shift($game['market_deck']);
                // Check if this card matches an existing non-empty slot
                $stacked = false;
                for ($j = 0; $j < count($game['marketplace']); $j++) {
                    if ($j !== $i && !empty($game['marketplace'][$j]) && $game['marketplace'][$j][0] === $drawn) {
                        $game['marketplace'][$j][] = $drawn;
                        $stacked = true;
                        break;
                    }
                }
                if (!$stacked) {
                    $game['marketplace'][$i] = [$drawn];
                    break;
                }
            }
        }
    }
}

function refill_mission_grid(array &$game): void {
    foreach ([1, 2, 3] as $tier) {
        // Remove empty stacks
        $game['mission_grid'][$tier] = array_values(array_filter(
            $game['mission_grid'][$tier],
            fn($slot) => !empty($slot)
        ));
        while (count($game['mission_grid'][$tier]) < 3 && !empty($game['mission_decks'][$tier])) {
            $drawn = array_shift($game['mission_decks'][$tier]);
            $stacked = false;
            foreach ($game['mission_grid'][$tier] as &$slot) {
                if (!empty($slot) && $slot[0] === $drawn) {
                    $slot[] = $drawn;
                    $stacked = true;
                    break;
                }
            }
            unset($slot);
            if (!$stacked) {
                $game['mission_grid'][$tier][] = [$drawn];
            }
        }
    }
}

function action_refresh_market(array &$game, int $pi, array $params): array {
    $log_parts = [];
    if (!spend_cost($game['players'][$pi], 2, $log_parts)) {
        return ['ok' => false, 'error' => 'Not enough money/gems (need $2)'];
    }
    // Move all marketplace cards to bottom of Market Deck
    foreach ($game['marketplace'] as $stack) {
        foreach ($stack as $card_id) {
            $game['market_deck'][] = $card_id;
        }
    }
    $game['marketplace'] = [[], [], [], [], [], [], []];
    refill_marketplace($game);
    $msg = $game['players'][$pi]['name'] . " restocked the marketplace for \$2";
    if (!empty($log_parts)) $msg .= ' (' . implode(', ', $log_parts) . ')';
    $game['log'][] = $msg;
    return ['ok' => true];
}

function action_buy_gem(array &$game, int $pi, array $params): array {
    if ($game['players'][$pi]['money'] < 3) {
        return ['ok' => false, 'error' => 'Not enough money (need $3)'];
    }
    $game['players'][$pi]['money'] -= 3;
    $game['players'][$pi]['gems'] = ($game['players'][$pi]['gems'] ?? 0) + 1;
    $game['log'][] = $game['players'][$pi]['name'] . " bought a gem for \$3";
    return ['ok' => true];
}

function action_cash_gems(array &$game, int $pi, array $params): array {
    $amount = (int)($params['amount'] ?? 0);
    $gems = $game['players'][$pi]['gems'] ?? 0;
    if ($amount <= 0) {
        return ['ok' => false, 'error' => 'Must cash at least 1 gem'];
    }
    if ($amount > $gems) {
        return ['ok' => false, 'error' => "Not enough gems (have {$gems})"];
    }
    $game['players'][$pi]['gems'] -= $amount;
    $game['players'][$pi]['money'] += $amount;
    $game['log'][] = $game['players'][$pi]['name'] . " cashed {$amount} gem(s) for \${$amount}";
    return ['ok' => true];
}

/**
 * Auto-resolve icon choices to satisfy mission requirements.
 * Uses backtracking to find a valid assignment.
 */
function auto_resolve_choices(array $requirements, array $card_ids, array $catalog): ?array {
    $slots = [];
    foreach ($card_ids as $ci => $card_id) {
        $card = $catalog[$card_id];
        foreach (($card['icons'] ?? []) as $ii => $icon) {
            $slots[] = ['card_idx' => $ci, 'icon_idx' => $ii, 'options' => $icon];
        }
    }

    $choice_slots = [];
    $fixed_icons = [];
    foreach ($slots as $si => $slot) {
        if (is_array($slot['options'])) {
            $choice_slots[] = $si;
        } else {
            $fixed_icons[] = $slot['options'];
        }
    }

    if (empty($choice_slots)) {
        if (check_requirements($requirements, $fixed_icons)) {
            return [];
        }
        return null;
    }

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
            $choices_by_card = [];
            foreach ($resolved as $r) {
                $choices_by_card[$r['card_idx']][$r['icon_idx']] = $r['chosen'];
            }
            return $choices_by_card;
        }

        for ($ci = $num_choices - 1; $ci >= 0; $ci--) {
            $combo[$ci]++;
            if ($combo[$ci] < count($slots[$choice_slots[$ci]]['options'])) break;
            $combo[$ci] = 0;
        }
    }

    return null;
}

function check_requirements(array $requirements, array $available): bool {
    $avail = $available;
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

function action_put_agent_in_base(array &$game, int $pi, array $params): array {
    $agent_id = $params['agent_id'] ?? '';
    $tech_ids = $params['tech_ids'] ?? [];
    $catalog = get_card_catalog();
    $p = &$game['players'][$pi];

    normalize_base($p);
    $base = &$p['base'];

    if (!$agent_id || !isset($catalog[$agent_id]) || $catalog[$agent_id]['type'] !== TYPE_AGENT) {
        return ['ok' => false, 'error' => 'Not a valid agent'];
    }
    if (!in_array($agent_id, $p['hand'])) {
        return ['ok' => false, 'error' => 'Agent not in hand'];
    }

    $max_tech = $catalog[$agent_id]['max_tech'] ?? 0;
    if (count($tech_ids) > $max_tech) {
        return ['ok' => false, 'error' => "Too many tech cards (max {$max_tech} for this agent)"];
    }

    // Validate tech in hand
    $hand_copy = $p['hand'];
    remove_from_array($hand_copy, $agent_id);
    foreach ($tech_ids as $tid) {
        if (!isset($catalog[$tid]) || $catalog[$tid]['type'] !== TYPE_TECH) {
            return ['ok' => false, 'error' => "{$tid} is not a tech card"];
        }
        if (!remove_from_array($hand_copy, $tid)) {
            return ['ok' => false, 'error' => 'Tech not in hand'];
        }
    }

    // Discard existing base agent if any
    if ($base['agent']) {
        $old_name = $catalog[$base['agent']]['name'] ?? $base['agent'];
        $p['discard'][] = $base['agent'];
        foreach ($base['tech'] as $tid) {
            $p['discard'][] = $tid;
        }
        $game['log'][] = $p['name'] . " removed {$old_name} from base";
    }

    // Remove from hand
    remove_from_array($p['hand'], $agent_id);
    foreach ($tech_ids as $tid) {
        remove_from_array($p['hand'], $tid);
    }

    // Place in base
    $base['agent'] = $agent_id;
    $base['tech'] = array_values($tech_ids);

    $agent_name = $catalog[$agent_id]['name'];
    $log = $p['name'] . " placed {$agent_name} in base";
    if (!empty($tech_ids)) {
        $tech_names = array_map(fn($t) => $catalog[$t]['name'] ?? $t, $tech_ids);
        $log .= " with " . implode(', ', $tech_names);
    }
    $game['log'][] = $log;

    return ['ok' => true];
}

function action_add_tech_to_base(array &$game, int $pi, array $params): array {
    $tech_ids = $params['tech_ids'] ?? [];
    $catalog = get_card_catalog();
    $p = &$game['players'][$pi];

    normalize_base($p);
    $base = &$p['base'];

    if (!$base['agent']) {
        return ['ok' => false, 'error' => 'No agent in base to add tech to'];
    }
    if (empty($tech_ids)) {
        return ['ok' => false, 'error' => 'No tech cards specified'];
    }

    $agent = $catalog[$base['agent']];
    $max_tech = $agent['max_tech'] ?? 0;
    $slots_remaining = $max_tech - count($base['tech']);

    if (count($tech_ids) > $slots_remaining) {
        return ['ok' => false, 'error' => "Only {$slots_remaining} tech slot(s) remaining for {$agent['name']}"];
    }

    $hand_copy = $p['hand'];
    foreach ($tech_ids as $tid) {
        if (!isset($catalog[$tid]) || $catalog[$tid]['type'] !== TYPE_TECH) {
            return ['ok' => false, 'error' => "{$tid} is not a tech card"];
        }
        if (!remove_from_array($hand_copy, $tid)) {
            return ['ok' => false, 'error' => 'Tech not in hand'];
        }
    }

    foreach ($tech_ids as $tid) {
        remove_from_array($p['hand'], $tid);
        $base['tech'][] = $tid;
    }

    $tech_names = array_map(fn($t) => $catalog[$t]['name'] ?? $t, $tech_ids);
    $game['log'][] = $p['name'] . " equipped " . implode(', ', $tech_names) . " to {$agent['name']} in base";

    return ['ok' => true];
}

function action_discard_base_agent(array &$game, int $pi, array $params): array {
    $p = &$game['players'][$pi];
    normalize_base($p);
    $base = &$p['base'];

    if (!$base['agent']) {
        return ['ok' => false, 'error' => 'No agent in base'];
    }

    $catalog = get_card_catalog();
    $agent_name = $catalog[$base['agent']]['name'] ?? $base['agent'];

    $p['discard'][] = $base['agent'];
    foreach ($base['tech'] as $tid) {
        $p['discard'][] = $tid;
    }
    $base['agent'] = null;
    $base['tech'] = [];

    $game['log'][] = $p['name'] . " discarded {$agent_name} from base";
    return ['ok' => true];
}

/**
 * Complete a mission by playing agents + tech from hand.
 * Params: mission_id, agent_ids (array), tech_ids (array)
 */
function action_complete_mission(array &$game, int $pi, array $params): array {
    $mission_id = $params['mission_id'] ?? '';
    $agent_ids = $params['agent_ids'] ?? [];
    $tech_ids = $params['tech_ids'] ?? [];
    $catalog = get_card_catalog();

    if (!isset($catalog[$mission_id]) || $catalog[$mission_id]['type'] !== TYPE_MISSION) {
        return ['ok' => false, 'error' => 'Not a valid mission'];
    }

    $mission = $catalog[$mission_id];

    // Check mission limit
    $missions_allowed = 1 + ($game['players'][$pi]['extra_missions'] ?? 0);
    $missions_completed = $game['players'][$pi]['missions_this_turn'] ?? 0;
    if ($missions_completed >= $missions_allowed) {
        return ['ok' => false, 'error' => 'No more ops allowed this turn'];
    }

    // Find mission in grid or check always-available
    $found_tier = null;
    $found_idx = null;
    $is_always = $catalog[$mission_id]['always_available'] ?? false;

    if (!$is_always) {
        foreach ($game['mission_grid'] as $tier => $slots) {
            foreach ($slots as $idx => $slot) {
                $top = is_array($slot) ? ($slot[0] ?? null) : $slot;
                if ($top === $mission_id) {
                    $found_tier = $tier;
                    $found_idx = $idx;
                    break 2;
                }
            }
        }
        if ($found_tier === null) {
            return ['ok' => false, 'error' => 'Op not available in grid'];
        }
    }

    // Also include backup agent if one was played via Got Your Back!
    $backup = $game['players'][$pi]['backup_agent'] ?? null;

    // Also optionally use the base agent
    $use_base_agent = !empty($params['use_base_agent']);
    $base_agent_id = null;
    $base_tech_ids = [];
    if ($use_base_agent) {
        normalize_base($game['players'][$pi]);
        $base = $game['players'][$pi]['base'];
        if (!$base['agent']) {
            return ['ok' => false, 'error' => 'No agent in base to use'];
        }
        $base_agent_id = $base['agent'];
        $base_tech_ids = $base['tech'] ?? [];
        if (!isset($catalog[$base_agent_id]) || $catalog[$base_agent_id]['type'] !== TYPE_AGENT) {
            return ['ok' => false, 'error' => 'Base agent is invalid'];
        }
    }

    // Must have at least one agent (from hand or base)
    if (empty($agent_ids) && !$base_agent_id) {
        return ['ok' => false, 'error' => 'Must commit at least one agent'];
    }

    // Validate all agent_ids are agents in hand
    $hand_copy = $game['players'][$pi]['hand'];
    foreach ($agent_ids as $aid) {
        if (!isset($catalog[$aid]) || $catalog[$aid]['type'] !== TYPE_AGENT) {
            return ['ok' => false, 'error' => "{$aid} is not an agent"];
        }
        if (!remove_from_array($hand_copy, $aid)) {
            return ['ok' => false, 'error' => "Agent {$catalog[$aid]['name']} not in hand"];
        }
    }

    // Validate tech: each tech must be in hand, and respect max_tech per agent
    $total_max_tech = 0;
    foreach ($agent_ids as $aid) {
        $total_max_tech += $catalog[$aid]['max_tech'] ?? 0;
    }
    if ($backup) {
        $total_max_tech += $catalog[$backup]['max_tech'] ?? 0;
    }
    // Base agent's remaining open slots (max_tech minus already-equipped tech)
    if ($base_agent_id) {
        $base_slots_remaining = ($catalog[$base_agent_id]['max_tech'] ?? 0) - count($base_tech_ids);
        $total_max_tech += max(0, $base_slots_remaining);
    }

    if (count($tech_ids) > $total_max_tech) {
        return ['ok' => false, 'error' => "Too many tech cards (max {$total_max_tech} for these agents)"];
    }

    foreach ($tech_ids as $tid) {
        if (!isset($catalog[$tid]) || $catalog[$tid]['type'] !== TYPE_TECH) {
            return ['ok' => false, 'error' => "{$tid} is not a tech card"];
        }
        if (!remove_from_array($hand_copy, $tid)) {
            return ['ok' => false, 'error' => "Tech {$catalog[$tid]['name']} not in hand"];
        }
    }

    // Collect all card IDs for icon resolution
    $all_card_ids = array_merge($agent_ids, $tech_ids);
    if ($backup) {
        $all_card_ids[] = $backup;
    }
    if ($base_agent_id) {
        $all_card_ids[] = $base_agent_id;
        $all_card_ids = array_merge($all_card_ids, $base_tech_ids);
    }

    // Auto-resolve icon choices
    $resolution = auto_resolve_choices($mission['requirements'], $all_card_ids, $catalog);
    if ($resolution === null) {
        return ['ok' => false, 'error' => 'Cards do not meet mission requirements'];
    }

    // Success! Remove cards from hand
    foreach ($agent_ids as $aid) {
        remove_from_array($game['players'][$pi]['hand'], $aid);
        $game['players'][$pi]['discard'][] = $aid;
    }
    foreach ($tech_ids as $tid) {
        remove_from_array($game['players'][$pi]['hand'], $tid);
        $game['players'][$pi]['discard'][] = $tid;
    }

    // Clear backup agent
    if ($backup) {
        $game['players'][$pi]['backup_agent'] = null;
    }

    // Clear base agent if used
    if ($base_agent_id) {
        $game['players'][$pi]['discard'][] = $base_agent_id;
        foreach ($base_tech_ids as $tid) {
            $game['players'][$pi]['discard'][] = $tid;
        }
        $game['players'][$pi]['base']['agent'] = null;
        $game['players'][$pi]['base']['tech'] = [];
    }

    // Remove mission from grid (pop from stack, remove slot if empty)
    if (!$is_always && $found_tier !== null) {
        $slot = &$game['mission_grid'][$found_tier][$found_idx];
        if (is_array($slot)) {
            array_shift($slot);
            if (empty($slot)) {
                array_splice($game['mission_grid'][$found_tier], $found_idx, 1);
            }
        } else {
            array_splice($game['mission_grid'][$found_tier], $found_idx, 1);
        }
        unset($slot);
    }

    // Build agent/tech used description for logging
    $used_names = [];
    foreach ($agent_ids as $aid) {
        $used_names[] = $catalog[$aid]['name'] ?? $aid;
    }
    if ($backup) {
        $used_names[] = ($catalog[$backup]['name'] ?? $backup) . ' (backup)';
    }
    if ($base_agent_id) {
        $used_names[] = ($catalog[$base_agent_id]['name'] ?? $base_agent_id) . ' (base)';
    }
    foreach ($tech_ids as $tid) {
        $used_names[] = $catalog[$tid]['name'] ?? $tid;
    }
    foreach ($base_tech_ids as $tid) {
        $used_names[] = ($catalog[$tid]['name'] ?? $tid) . ' (base)';
    }
    $used_str = !empty($used_names) ? ' using ' . implode(', ', $used_names) : '';

    // Handle Heist: award gems, card does NOT go into deck
    $mission_gems = $mission['gems'] ?? 0;
    if ($mission_gems > 0) {
        // Count total icons committed
        $total_icons = 0;
        foreach ($all_card_ids as $cid) {
            $total_icons += count($catalog[$cid]['icons'] ?? []);
        }
        // 1-2 icons → 1 gem, 3-4 icons → 2 gems, 5+ icons → 3 gems
        if ($total_icons >= 5) {
            $gems_awarded = 3;
        } elseif ($total_icons >= 3) {
            $gems_awarded = 2;
        } else {
            $gems_awarded = 1;
        }
        $game['players'][$pi]['gems'] = ($game['players'][$pi]['gems'] ?? 0) + $gems_awarded;
        $game['log'][] = $game['players'][$pi]['name'] . " completed Heist{$used_str} ({$total_icons} icons) and earned {$gems_awarded} gem(s)!";
    } else {
        // Normal mission: add mission card to mission area (provides recurring income)
        $game['players'][$pi]['mission_area'][] = $mission_id;
        $game['log'][] = $game['players'][$pi]['name'] . " completed {$mission['name']}{$used_str}";
        // Apply mission bonus immediately (including the turn it was completed)
        if (($mission['value'] ?? 0) > 0) {
            $game['players'][$pi]['money'] += $mission['value'];
        }
        if ($mission['extra_mission'] ?? false) {
            $game['players'][$pi]['extra_missions'] = ($game['players'][$pi]['extra_missions'] ?? 0) + 1;
        }
        if ($mission['extra_buy'] ?? false) {
            $game['players'][$pi]['extra_buys'] = ($game['players'][$pi]['extra_buys'] ?? 0) + 1;
        }
    }

    $game['players'][$pi]['missions_this_turn'] = $missions_completed + 1;

    check_game_end($game);

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
    $p['buys_this_turn'] = 0;
    $p['extra_buys'] = 0;
    $p['backup_agent'] = null;
    $p['extra_missions'] = 0;
    $p['missions_this_turn'] = 0;
    // gems persist between turns

    // Refill marketplace
    refill_marketplace($game);

    // Refill mission grid (with stacking: if drawn card matches existing, stack and draw again)
    refill_mission_grid($game);

    // Draw 5 cards
    player_draw_cards($p, 5);

    check_game_end($game);

    if (is_game_over($game)) {
        finalize_game($game);
        return ['ok' => true];
    }

    // Advance to next player
    $game['current_player'] = ($game['current_player'] + 1) % count($game['players']);
    $game['turn_number']++;
    // Increment round when it comes back to the first player
    $first_player = $game['first_player'] ?? 0;
    if ($game['current_player'] === $first_player) {
        $game['round'] = ($game['round'] ?? 1) + 1;
    }
    $next_name = $game['players'][$game['current_player']]['name'];
    $game['log'][] = "It's now {$next_name}'s turn.";

    // Apply passive mission area bonuses for the next player
    $next_pi = $game['current_player'];
    $catalog = get_card_catalog();
    $mission_income = 0;
    $mission_extra_missions = 0;
    $mission_extra_buys = 0;
    foreach ($game['players'][$next_pi]['mission_area'] ?? [] as $mid) {
        $mc = $catalog[$mid] ?? [];
        $mission_income += $mc['value'] ?? 0;
        if ($mc['extra_mission'] ?? false) $mission_extra_missions++;
        if ($mc['extra_buy'] ?? false) $mission_extra_buys++;
    }
    $bonus_parts = [];
    if ($mission_income > 0) {
        $game['players'][$next_pi]['money'] += $mission_income;
        $bonus_parts[] = "\${$mission_income}";
    }
    if ($mission_extra_missions > 0) {
        $game['players'][$next_pi]['extra_missions'] = ($game['players'][$next_pi]['extra_missions'] ?? 0) + $mission_extra_missions;
        $bonus_parts[] = "+{$mission_extra_missions} op";
    }
    if ($mission_extra_buys > 0) {
        $game['players'][$next_pi]['extra_buys'] = ($game['players'][$next_pi]['extra_buys'] ?? 0) + $mission_extra_buys;
        $bonus_parts[] = "+{$mission_extra_buys} buy";
    }
    if (!empty($bonus_parts)) {
        $game['log'][] = $game['players'][$next_pi]['name'] . " gets " . implode(', ', $bonus_parts) . " from completed ops.";
    }

    return ['ok' => true];
}

function action_vote_rematch(array &$game, int $pi, array $params): array {
    if (!($game['ended'] ?? false)) {
        return ['ok' => false, 'error' => 'Game is not over yet'];
    }

    $vote = !empty($params['vote']);
    if (!isset($game['rematch_votes'])) {
        $game['rematch_votes'] = [];
    }

    $player_token = $game['players'][$pi]['token'];
    if ($vote) {
        $game['rematch_votes'][$player_token] = true;
    } else {
        unset($game['rematch_votes'][$player_token]);
    }

    // Check if all HUMAN players voted yes (AI players auto-vote yes)
    $human_players = array_filter($game['players'], fn($p) => !($p['is_ai'] ?? false));
    $human_tokens = array_map(fn($p) => $p['token'], $human_players);
    $human_votes = count(array_filter(
        $game['rematch_votes'],
        fn($_, $tok) => in_array($tok, $human_tokens),
        ARRAY_FILTER_USE_BOTH
    ));
    $total_human = count($human_players);

    if ($human_votes >= 1 && $human_votes === $total_human) {
        // Trigger rematch: create new game in the same room
        $room_id = $game['room_id'] ?? null;
        if ($room_id) {
            require_once __DIR__ . '/game_init.php';
            require_once __DIR__ . '/../api/helpers.php';

            $room_path = data_path('rooms', $room_id);
            $room = read_json($room_path);
            if ($room) {
                $new_game_id = gen_id();
                $new_game = init_game($room['players']);
                $new_game['room_id'] = $room_id;
                write_json(data_path('games', $new_game_id), $new_game);

                $room['previous_game_ids'][] = $room['game_id']; // track for cleanup
                $room['game_id'] = $new_game_id;
                write_json($room_path, $room);

                $game['rematch_game_id'] = $new_game_id;
            }
        }
    }

    return ['ok' => true];
}

function action_resign(array &$game, int $pi, array $params): array {
    $name = $game['players'][$pi]['name'];
    $game['log'][] = "{$name} resigned from the game!";

    // Clean up pending attack if exists
    if (isset($game['pending_attack'])) {
        $attack = &$game['pending_attack'];
        // Remove from defenders list
        $attack['defenders'] = array_values(array_filter($attack['defenders'], fn($d) => $d !== $pi));
        // Remove response if any
        unset($attack['responses'][$pi]);
        // Adjust indices for players after the removed one
        $new_defenders = [];
        foreach ($attack['defenders'] as $d) {
            $new_defenders[] = $d > $pi ? $d - 1 : $d;
        }
        $attack['defenders'] = $new_defenders;
        $new_responses = [];
        foreach ($attack['responses'] as $d => $r) {
            $new_responses[$d > $pi ? $d - 1 : $d] = $r;
        }
        $attack['responses'] = $new_responses;
        // Adjust attacker index
        if ($attack['attacker'] > $pi) {
            $attack['attacker']--;
        }
        // If no defenders left or all responded, clear attack
        if (empty($attack['defenders']) || count($attack['responses']) >= count($attack['defenders'])) {
            unset($game['pending_attack']);
        }
    }

    // Remove the player
    array_splice($game['players'], $pi, 1);
    $num_players = count($game['players']);

    // If only 1 (or 0) players left, end the game
    if ($num_players <= 1) {
        finalize_game($game);
        return ['ok' => true];
    }

    // Adjust current_player index
    $cp = $game['current_player'];
    if ($pi < $cp) {
        // Removed player was before current player, shift down
        $game['current_player'] = $cp - 1;
    } elseif ($pi === $cp) {
        // The resigning player was the current player — next player takes over
        // If we removed the last index, wrap to 0
        $game['current_player'] = $cp % $num_players;
        // Reset turn state for new current player
        $next = &$game['players'][$game['current_player']];
        $next_name = $next['name'];
        $game['log'][] = "It's now {$next_name}'s turn.";
    }
    // If pi > cp, current_player index stays the same

    // Adjust final_round_starter if set
    if (isset($game['final_round_starter'])) {
        $frs = $game['final_round_starter'];
        if ($pi < $frs) {
            $game['final_round_starter'] = $frs - 1;
        } elseif ($pi === $frs) {
            // The player who triggered final round left; use next player
            $game['final_round_starter'] = $frs % $num_players;
        }
    }

    // Adjust first_player if set
    if (isset($game['first_player'])) {
        $fp = $game['first_player'];
        if ($pi < $fp) {
            $game['first_player'] = $fp - 1;
        } elseif ($pi === $fp) {
            $game['first_player'] = $fp % $num_players;
        }
    }

    return ['ok' => true];
}

function action_defend_attack(array &$game, int $pi, array $params): array {
    if (!isset($game['pending_attack'])) {
        return ['ok' => false, 'error' => 'No pending attack'];
    }
    $attack = &$game['pending_attack'];
    if (!in_array($pi, $attack['defenders'])) {
        return ['ok' => false, 'error' => 'You are not a defender in this attack'];
    }
    if (isset($attack['responses'][$pi])) {
        return ['ok' => false, 'error' => 'You already responded to this attack'];
    }

    $choice = $params['choice'] ?? '';
    $card_id = $params['card_id'] ?? '';
    $catalog = get_card_catalog();
    $name = $game['players'][$pi]['name'];

    $attack_card = $attack['card'];
    $attack_name = $catalog[$attack_card]['name'] ?? $attack_card;

    switch ($choice) {
        case 'suffer':
            if ($attack_card === 'burglary') {
                $hand = &$game['players'][$pi]['hand'];
                $target_size = 3;
                $must_discard = count($hand) - $target_size;
                if ($must_discard <= 0) {
                    $attack['responses'][$pi] = 'suffer:none';
                    $game['log'][] = "{$name} has " . count($hand) . " cards — Burglary has no effect!";
                } else {
                    $discard_cards = $params['discard_cards'] ?? [];
                    if (count($discard_cards) !== $must_discard) {
                        return ['ok' => false, 'error' => "Must discard exactly {$must_discard} card(s)"];
                    }
                    foreach ($discard_cards as $dc) {
                        if (!remove_from_array($hand, $dc)) {
                            return ['ok' => false, 'error' => "Card not in hand"];
                        }
                        $game['players'][$pi]['discard'][] = $dc;
                    }
                    $attack['responses'][$pi] = 'suffer:' . implode(',', $discard_cards);
                    $game['log'][] = "{$name} suffers Burglary and discards down to 3 cards!";
                }
            } else {
                // Paperwork: gain a Red Tape
                $game['players'][$pi]['discard'][] = 'red_tape';
                $attack['responses'][$pi] = 'suffer';
                $game['log'][] = "{$name} suffers the {$attack_name} and gains a Red Tape!";
            }
            break;

        case 'discard':
            $discard_from = $params['discard_from'] ?? 'hand'; // 'hand' or 'base'
            if (!$card_id || !isset($catalog[$card_id]) || $catalog[$card_id]['type'] !== 'agent') {
                return ['ok' => false, 'error' => 'Must specify a valid agent to discard'];
            }
            // For Burglary, agent must have muscle or drive icons
            if ($attack_card === 'burglary') {
                $agent_icons = $catalog[$card_id]['icons'] ?? [];
                if (!in_array('muscle', $agent_icons) && !in_array('drive', $agent_icons)) {
                    return ['ok' => false, 'error' => 'Agent must have 💪 or 🚘 to defend against Burglary'];
                }
            }
            if ($discard_from === 'base') {
                normalize_base($game['players'][$pi]);
                $base = &$game['players'][$pi]['base'];
                if ($base['agent'] !== $card_id) {
                    return ['ok' => false, 'error' => 'Agent not in base'];
                }
                foreach ($base['tech'] as $tid) {
                    $game['players'][$pi]['discard'][] = $tid;
                }
                $base['agent'] = null;
                $base['tech'] = [];
                unset($base);
            } else {
                if (!remove_from_array($game['players'][$pi]['hand'], $card_id)) {
                    return ['ok' => false, 'error' => 'Agent not in hand'];
                }
            }
            $game['players'][$pi]['discard'][] = $card_id;
            $attack['responses'][$pi] = 'discard:' . $card_id;
            $game['log'][] = "{$name} discards {$catalog[$card_id]['name']} to defend against {$attack_name}!";
            break;

        case 'sentinel':
            if (!$card_id || !isset($catalog[$card_id]) || !($catalog[$card_id]['defend'] ?? false)) {
                return ['ok' => false, 'error' => 'Must specify a valid Sentinel to reveal'];
            }
            if (!in_array($card_id, $game['players'][$pi]['hand'])) {
                return ['ok' => false, 'error' => 'Sentinel not in hand'];
            }
            // Sentinel stays in hand — just reveal it
            $attack['responses'][$pi] = 'sentinel:' . $card_id;
            $game['log'][] = "{$name} reveals {$catalog[$card_id]['name']} to defend against {$attack_name}!";
            break;

        default:
            return ['ok' => false, 'error' => 'Invalid choice: must be suffer, discard, or sentinel'];
    }

    // Check if all defenders have responded
    if (count($attack['responses']) >= count($attack['defenders'])) {
        unset($game['pending_attack']);
    }

    return ['ok' => true];
}

function process_action(array &$game, string $token, string $action, array $params): array {
    $pi = find_player_index($game, $token);
    if ($pi < 0) return ['ok' => false, 'error' => 'Player not found'];

    // Rematch voting works after the game ends
    if ($action === 'vote_rematch') {
        $result = action_vote_rematch($game, $pi, $params);
        if ($result['ok'] ?? false) $game['version']++;
        return $result;
    }

    // Defend attack works for non-active players when pending_attack is set
    if ($action === 'defend_attack') {
        if ($game['ended'] ?? false) {
            return ['ok' => false, 'error' => 'Game is over'];
        }
        $result = action_defend_attack($game, $pi, $params);
        if ($result['ok'] ?? false) $game['version']++;
        return $result;
    }

    // Resign works any time (not just your turn), but not after game ended
    if ($action === 'resign') {
        if ($game['ended'] ?? false) {
            return ['ok' => false, 'error' => 'Game is already over'];
        }
        $result = action_resign($game, $pi, $params);
        if ($result['ok'] ?? false) $game['version']++;
        return $result;
    }

    // Debug: force end game
    if ($action === 'debug_end_game') {
        if (!($game['ended'] ?? false)) {
            finalize_game($game);
            $game['version']++;
        }
        return ['ok' => true];
    }

    // Debug: add gems/buys/missions (only meaningful on your turn, but no turn check here)
    if ($action === 'debug_add_gems') {
        $game['players'][$pi]['gems'] = ($game['players'][$pi]['gems'] ?? 0) + 10;
        $game['version']++;
        return ['ok' => true];
    }
    if ($action === 'debug_add_buys') {
        $game['players'][$pi]['extra_buys'] = ($game['players'][$pi]['extra_buys'] ?? 0) + 5;
        $game['version']++;
        return ['ok' => true];
    }
    if ($action === 'debug_add_missions') {
        $game['players'][$pi]['extra_missions'] = ($game['players'][$pi]['extra_missions'] ?? 0) + 5;
        $game['version']++;
        return ['ok' => true];
    }

    if ($game['ended'] ?? false) {
        return ['ok' => false, 'error' => 'Game is over'];
    }

    if ($game['current_player'] !== $pi) {
        return ['ok' => false, 'error' => 'Not your turn'];
    }

    // Block all actions while an attack is pending
    if (isset($game['pending_attack'])) {
        return ['ok' => false, 'error' => 'Waiting for attack responses'];
    }

    $result = match($action) {
        'play_money' => action_play_money($game, $pi, $params),
        'play_plot' => action_play_plot($game, $pi, $params),
        'buy_card' => action_buy_card($game, $pi, $params),
        'refresh_market' => action_refresh_market($game, $pi, $params),
        'buy_gem' => action_buy_gem($game, $pi, $params),
        'cash_gems' => action_cash_gems($game, $pi, $params),
        'complete_mission' => action_complete_mission($game, $pi, $params),
        'put_agent_in_base' => action_put_agent_in_base($game, $pi, $params),
        'add_tech_to_base' => action_add_tech_to_base($game, $pi, $params),
        'discard_base_agent' => action_discard_base_agent($game, $pi, $params),
        'end_turn' => action_end_turn($game, $pi, $params),
        default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
    };

    if ($result['ok'] ?? false) {
        $game['version']++;
    }

    return $result;
}
