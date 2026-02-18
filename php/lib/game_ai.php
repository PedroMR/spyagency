<?php
require_once __DIR__ . '/game_logic.php';
require_once __DIR__ . '/cards.php';

/**
 * Return all size-$size combinations of $arr elements.
 */
function ai_combinations(array $arr, int $size): array {
    if ($size === 0) return [[]];
    if (empty($arr) || $size > count($arr)) return [];
    $first = array_shift($arr);
    $with = array_map(fn($c) => array_merge([$first], $c), ai_combinations($arr, $size - 1));
    $without = ai_combinations($arr, $size);
    return array_merge($with, $without);
}

/**
 * Return all subsets of $arr with size 0...$max_size.
 */
function ai_all_subsets(array $arr, int $max_size): array {
    $result = [[]];
    for ($size = 1; $size <= $max_size && $size <= count($arr); $size++) {
        $result = array_merge($result, ai_combinations($arr, $size));
    }
    return $result;
}

/**
 * Score a card for purchase desirability (higher = better to buy).
 */
function ai_card_score(array $card): int {
    if (empty($card)) return -99;
    $score = 0;
    $score += ($card['stars'] ?? 0) * 20;
    $score += count($card['icons'] ?? []) * 3;
    $score += $card['cost'] ?? 0;
    if ($card['type'] === TYPE_MONEY) $score -= 10;
    if ($card['type'] === TYPE_PLOT && ($card['effect'] ?? '') === 'none') $score -= 15;
    return $score;
}

/**
 * Find a valid agent+tech combo from the player's hand (and optionally base) that
 * satisfies the given mission's requirements.
 * Returns ['agent_ids' => [...], 'tech_ids' => [...], 'use_base_agent' => bool] or null.
 */
function ai_find_mission_combo(array $player, array $mission, array $catalog): ?array {
    $agents = array_values(array_filter(
        $player['hand'] ?? [],
        fn($id) => isset($catalog[$id]) && $catalog[$id]['type'] === TYPE_AGENT
    ));
    $tech = array_values(array_filter(
        $player['hand'] ?? [],
        fn($id) => isset($catalog[$id]) && $catalog[$id]['type'] === TYPE_TECH
    ));

    $base_agent_id = null;
    $base_tech_ids = [];
    $base = $player['base'] ?? null;
    if (is_array($base) && ($base['agent'] ?? null)) {
        $base_agent_id = $base['agent'];
        $base_tech_ids = $base['tech'] ?? [];
    }

    // Try without base agent first, then with
    foreach ([false, true] as $use_base) {
        if ($use_base && !$base_agent_id) continue;

        $agent_subsets = ai_all_subsets($agents, count($agents));
        foreach ($agent_subsets as $hand_agents) {
            if (empty($hand_agents) && !$use_base) continue;

            $max_tech = 0;
            foreach ($hand_agents as $aid) {
                $max_tech += $catalog[$aid]['max_tech'] ?? 0;
            }
            if ($use_base) {
                $base_max = $catalog[$base_agent_id]['max_tech'] ?? 0;
                $max_tech += max(0, $base_max - count($base_tech_ids));
            }

            $tech_subsets = ai_all_subsets($tech, min($max_tech, count($tech)));
            foreach ($tech_subsets as $hand_tech) {
                $all_cards = array_merge($hand_agents, $hand_tech);
                if ($use_base) {
                    $all_cards[] = $base_agent_id;
                    $all_cards = array_merge($all_cards, $base_tech_ids);
                }
                $resolution = auto_resolve_choices($mission['requirements'], $all_cards, $catalog);
                if ($resolution !== null) {
                    return [
                        'agent_ids' => $hand_agents,
                        'tech_ids' => $hand_tech,
                        'use_base_agent' => $use_base,
                    ];
                }
            }
        }
    }

    return null;
}

/**
 * Pick the best mission the AI can complete this turn.
 * Returns params array for action_complete_mission, or null.
 */
function ai_pick_best_mission(array &$game, int $pi, array $catalog): ?array {
    $p = $game['players'][$pi];
    $missions_allowed = 1 + ($p['extra_missions'] ?? 0);
    $missions_done = $p['missions_this_turn'] ?? 0;
    if ($missions_done >= $missions_allowed) return null;

    $best = null;
    $best_score = -1;

    // Check mission grid
    foreach ($game['mission_grid'] as $tier => $slots) {
        foreach ($slots as $slot) {
            $mid = is_array($slot) ? ($slot[0] ?? null) : $slot;
            if (!$mid || !isset($catalog[$mid])) continue;
            $mission = $catalog[$mid];
            $combo = ai_find_mission_combo($p, $mission, $catalog);
            if (!$combo) continue;
            // Score: stars are best, then money value
            $score = ($mission['stars'] ?? 0) * 10 + ($mission['value'] ?? 0);
            if ($score > $best_score) {
                $best_score = $score;
                $best = array_merge(['mission_id' => $mid], $combo);
            }
        }
    }

    // Try Heist if no starred mission is available
    if ($best_score <= 0 && isset($catalog['heist'])) {
        $combo = ai_find_mission_combo($p, $catalog['heist'], $catalog);
        if ($combo) {
            $best = array_merge(['mission_id' => 'heist'], $combo);
        }
    }

    return $best;
}

/**
 * Pick the best card to buy from the marketplace.
 * Never buys always-available basic agents (Muscle/Shadow) — leftover money
 * is converted to gems separately in run_ai_turn.
 * Returns params for action_buy_card, or null.
 */
function ai_pick_best_buy(array &$game, int $pi, array $catalog): ?array {
    $p = $game['players'][$pi];
    $buy_limit = 1 + ($p['extra_buys'] ?? 0);
    if (($p['buys_this_turn'] ?? 0) >= $buy_limit) return null;

    $money = $p['money'] + ($p['gems'] ?? 0);
    if ($money <= 0) return null;

    $best = null;
    $best_score = -99;

    // Only check actual marketplace slots; skip always-available basic agents
    foreach ($game['marketplace'] as $slot => $stack) {
        if (empty($stack)) continue;
        $cid = $stack[0];
        if (!isset($catalog[$cid])) continue;
        if ($catalog[$cid]['always_available'] ?? false) continue;
        $cost = $catalog[$cid]['cost'] ?? 0;
        if ($cost > $money) continue;
        $score = ai_card_score($catalog[$cid]);
        if ($score > $best_score) {
            $best_score = $score;
            $best = ['card_id' => $cid, 'slot' => $slot];
        }
    }

    // Don't buy if nothing meaningful is available
    if ($best_score < 0) return null;

    return $best;
}

/**
 * AI defends against a pending attack. Tries: Sentinel > discard agent > suffer.
 */
function ai_handle_defense(array &$game, int $pi, array $catalog): void {
    if (!isset($game['pending_attack'])) return;
    $attack = &$game['pending_attack'];
    if (!in_array($pi, $attack['defenders']) || isset($attack['responses'][$pi])) return;

    $p = &$game['players'][$pi];
    $name = $p['name'];
    $attack_card = $attack['card'];
    $attack_name = $catalog[$attack_card]['name'] ?? $attack_card;

    // Try Sentinel
    foreach ($p['hand'] as $cid) {
        if (isset($catalog[$cid]) && ($catalog[$cid]['defend'] ?? false)) {
            $attack['responses'][$pi] = 'sentinel:' . $cid;
            $game['log'][] = "{$name} reveals {$catalog[$cid]['name']} — defended against {$attack_name}!";
            if (count($attack['responses']) >= count($attack['defenders'])) unset($game['pending_attack']);
            return;
        }
    }

    // Try to discard a hand agent
    foreach ($p['hand'] as $cid) {
        if (!isset($catalog[$cid]) || $catalog[$cid]['type'] !== TYPE_AGENT) continue;
        if ($attack_card === 'burglary') {
            $icons = $catalog[$cid]['icons'] ?? [];
            if (!in_array('muscle', $icons) && !in_array('drive', $icons)) continue;
        }
        remove_from_array($p['hand'], $cid);
        $p['discard'][] = $cid;
        $attack['responses'][$pi] = 'discard:' . $cid;
        $game['log'][] = "{$name} discards {$catalog[$cid]['name']} to defend against {$attack_name}!";
        if (count($attack['responses']) >= count($attack['defenders'])) unset($game['pending_attack']);
        return;
    }

    // Try base agent
    if (is_array($p['base'] ?? null) && ($p['base']['agent'] ?? null)) {
        $baid = $p['base']['agent'];
        $eligible = true;
        if ($attack_card === 'burglary') {
            $icons = $catalog[$baid]['icons'] ?? [];
            if (!in_array('muscle', $icons) && !in_array('drive', $icons)) $eligible = false;
        }
        if ($eligible) {
            foreach ($p['base']['tech'] ?? [] as $tid) $p['discard'][] = $tid;
            $p['discard'][] = $baid;
            $p['base']['agent'] = null;
            $p['base']['tech'] = [];
            $attack['responses'][$pi] = 'discard:' . $baid;
            $game['log'][] = "{$name} discards {$catalog[$baid]['name']} from base to defend!";
            if (count($attack['responses']) >= count($attack['defenders'])) unset($game['pending_attack']);
            return;
        }
    }

    // Suffer the attack
    if ($attack_card === 'burglary') {
        $hand = &$p['hand'];
        $must_discard = count($hand) - 3;
        if ($must_discard <= 0) {
            $attack['responses'][$pi] = 'suffer:none';
            $game['log'][] = "{$name} has " . count($hand) . " cards — Burglary has no effect!";
        } else {
            // Discard weakest cards first
            usort($hand, fn($a, $b) => ai_card_score($catalog[$a] ?? []) - ai_card_score($catalog[$b] ?? []));
            $discarded = array_splice($hand, 0, $must_discard);
            foreach ($discarded as $dc) $p['discard'][] = $dc;
            $attack['responses'][$pi] = 'suffer:' . implode(',', $discarded);
            $game['log'][] = "{$name} suffers Burglary — discards {$must_discard} card(s)!";
        }
    } else {
        $p['discard'][] = 'red_tape';
        $attack['responses'][$pi] = 'suffer';
        $game['log'][] = "{$name} suffers {$attack_name} — gains a Red Tape!";
    }

    if (count($attack['responses']) >= count($attack['defenders'])) unset($game['pending_attack']);
}

/**
 * Run a complete AI turn for player $pi.
 */
function run_ai_turn(array &$game, int $pi, array $catalog): void {
    // 1. Play all money cards and valued missions from hand
    $hand_snapshot = $game['players'][$pi]['hand'];
    foreach ($hand_snapshot as $cid) {
        if (!isset($catalog[$cid])) continue;
        $type = $catalog[$cid]['type'];
        $is_money = $type === TYPE_MONEY;
        $is_valued_mission = $type === TYPE_MISSION && ($catalog[$cid]['value'] ?? 0) > 0;
        if ($is_money || $is_valued_mission) {
            action_play_money($game, $pi, ['card_id' => $cid]);
        }
    }

    // 2. Complete missions (loop in case extra missions are available)
    while (true) {
        if ($game['ended'] ?? false) break;
        $mission_params = ai_pick_best_mission($game, $pi, $catalog);
        if (!$mission_params) break;
        $result = action_complete_mission($game, $pi, $mission_params);
        if (!($result['ok'] ?? false)) break;
    }

    // 3. Buy best marketplace card if available (loop for extra buys)
    while (true) {
        if ($game['ended'] ?? false) break;
        $buy_params = ai_pick_best_buy($game, $pi, $catalog);
        if (!$buy_params) break;
        $result = action_buy_card($game, $pi, $buy_params);
        if (!($result['ok'] ?? false)) break;
    }

    // 4. Convert leftover money to gems ($3 each) to save up for expensive cards
    while (!($game['ended'] ?? false) && $game['players'][$pi]['money'] >= 3) {
        $result = action_buy_gem($game, $pi, []);
        if (!($result['ok'] ?? false)) break;
    }

    // 5. End turn
    if (!($game['ended'] ?? false)) {
        action_end_turn($game, $pi, []);
    }
}

/**
 * Process all pending AI actions in the game (defenses + active turn).
 * Called by the AI turn endpoint. Always returns ['ok' => true].
 */
function run_all_ai_actions(array &$game): array {
    if (($game['ended'] ?? false) || $game['status'] !== 'active') return ['ok' => true];

    $catalog = get_card_catalog();

    // Handle any AI defenders in a pending attack
    if (isset($game['pending_attack'])) {
        $defenders = $game['pending_attack']['defenders'];
        foreach ($defenders as $di) {
            if (!isset($game['pending_attack'])) break; // was fully cleared
            if (!isset($game['pending_attack']['responses'][$di]) && ($game['players'][$di]['is_ai'] ?? false)) {
                ai_handle_defense($game, $di, $catalog);
            }
        }
        // If human defenders still haven't responded, stop here
        if (isset($game['pending_attack'])) {
            $game['version']++;
            return ['ok' => true];
        }
    }

    // Handle the active AI player's turn
    $pi = $game['current_player'];
    if (!($game['players'][$pi]['is_ai'] ?? false)) {
        return ['ok' => true];
    }
    if ($game['ended'] ?? false) return ['ok' => true];

    run_ai_turn($game, $pi, $catalog);
    $game['version']++;
    return ['ok' => true];
}
