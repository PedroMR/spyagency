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
 * $needed_icons: icon => weight map from ai_needed_icons(), used to boost
 * cards that address gaps in the available op grid.
 */
function ai_card_score(array $card, array $needed_icons = []): int {
    if (empty($card)) return -99;
    $score = 0;
    $score += ($card['stars'] ?? 0) * 20;
    $score += count($card['icons'] ?? []) * 3;
    $score += $card['cost'] ?? 0;
    if ($card['type'] === TYPE_MONEY) $score -= 10;
    if ($card['type'] === 'hazard') $score -= 20;
    if ($card['type'] === TYPE_PLOT && ($card['effect'] ?? '') === 'none') $score -= 15;
    // Bonus for icons that help complete ops currently in the grid
    foreach ($card['icons'] ?? [] as $icon) {
        $score += (int)(($needed_icons[$icon] ?? 0) * 4);
    }
    return $score;
}

/**
 * Survey the mission grid and return a map of icon => need-weight, where weight
 * is the total mission value (stars * 10 + income) of grid missions that are
 * blocked by a shortage of that icon, given the AI's current icon pool.
 * Icons already covered by the AI's hand/base/mission-area don't count.
 */
function ai_needed_icons(array $game, int $pi, array $catalog): array {
    $p = $game['players'][$pi];

    // Build the AI's current icon pool from all sources
    $pool = [];
    foreach (array_unique($p['mission_area'] ?? []) as $mid) {
        foreach ($catalog[$mid]['icons'] ?? [] as $ic) $pool[] = $ic;
    }
    if ($p['backup_agent'] ?? null) {
        foreach ($catalog[$p['backup_agent']]['icons'] ?? [] as $ic) $pool[] = $ic;
    }
    foreach ($p['hand'] as $cid) {
        if (!isset($catalog[$cid])) continue;
        foreach ($catalog[$cid]['icons'] ?? [] as $ic) $pool[] = $ic;
    }

    $needed = [];

    foreach ($game['ops_grid'] as $slot) {
        $mid = is_array($slot) ? ($slot[0] ?? null) : $slot;
        if (!$mid || !isset($catalog[$mid])) continue;
        $mission = $catalog[$mid];
        $segments = $mission['segments'] ?? [];

        // Use segment 1 requirements for icon-need analysis (minimum investment)
        $seg1_reqs = $segments[0]['requirements'] ?? [];
        $seg1_reward = ($segments[0]['stars'] ?? 0) * 10 + ($segments[0]['gems'] ?? 0) * 2 + 1;

        // Greedily consume pool icons against specific requirements
        $remaining = $pool;
        $missing = [];
        foreach ($seg1_reqs as $req) {
            if ($req === 'any') continue;
            $options = is_array($req) ? $req : [$req];
            $found = false;
            foreach ($options as $opt) {
                $idx = array_search($opt, $remaining);
                if ($idx !== false) {
                    array_splice($remaining, $idx, 1);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                foreach ($options as $opt) $missing[] = $opt;
            }
        }

        foreach ($missing as $icon) {
            $needed[$icon] = ($needed[$icon] ?? 0) + $seg1_reward;
        }
    }

    return $needed;
}

/**
 * Find a valid agent+tech combo from the player's hand that satisfies
 * the given cumulative requirements array.
 * Returns ['agent_ids' => [...], 'tech_ids' => [...]] or null.
 */
function ai_find_mission_combo_for_reqs(array $player, array $requirements, array $catalog): ?array {
    $agents = array_values(array_filter(
        $player['hand'] ?? [],
        fn($id) => isset($catalog[$id]) && $catalog[$id]['type'] === TYPE_AGENT
    ));
    $tech = array_values(array_filter(
        $player['hand'] ?? [],
        fn($id) => isset($catalog[$id]) && $catalog[$id]['type'] === TYPE_TECH
    ));

    $mission_area_ids = array_unique($player['mission_area'] ?? []);
    $backup_id = $player['backup_agent'] ?? null;

    $agent_subsets = ai_all_subsets($agents, 2); // up to two hand agents per op
    foreach ($agent_subsets as $hand_agents) {
        if (empty($hand_agents)) continue;

        $max_tech = 0;
        foreach ($hand_agents as $aid) {
            $max_tech += $catalog[$aid]['max_tech'] ?? 0;
        }

        $tech_subsets = ai_all_subsets($tech, min($max_tech, count($tech)));
        foreach ($tech_subsets as $hand_tech) {
            $all_cards = array_merge($hand_agents, $hand_tech);
            if ($backup_id) {
                $all_cards[] = $backup_id;
            }
            $check_cards = array_merge($all_cards, $mission_area_ids);
            $resolution = auto_resolve_choices($requirements, $check_cards, $catalog);
            if ($resolution !== null) {
                return [
                    'agent_ids' => $hand_agents,
                    'tech_ids' => $hand_tech,
                ];
            }
        }
    }

    return null;
}

/**
 * Find the best segment the AI can reach for a given mission.
 * Returns ['combo' => [...], 'target_segment' => int, 'score' => int] or null.
 */
function ai_find_mission_combo(array $player, array $mission, array $catalog): ?array {
    $segments = $mission['segments'] ?? [];
    if (empty($segments)) return null;

    $best_combo = null;
    $best_segment = 0;
    $best_score = 0;

    for ($seg_num = count($segments); $seg_num >= 1; $seg_num--) {
        $cumulative_reqs = [];
        foreach (array_slice($segments, 0, $seg_num) as $seg) {
            $cumulative_reqs = array_merge($cumulative_reqs, $seg['requirements'] ?? []);
        }
        $combo = ai_find_mission_combo_for_reqs($player, $cumulative_reqs, $catalog);
        if ($combo !== null) {
            // Score rewards for segments 0..seg_num-1
            $score = 0;
            foreach (array_slice($segments, 0, $seg_num) as $seg) {
                $score += ($seg['stars']   ?? 0) * 10;
                $score += ($seg['gems']    ?? 0) * 2;
                $score += ($seg['cards']   ?? 0) * 3;
                $score += ($seg['trashes'] ?? 0) * 2;
                $score += ($seg['money']   ?? 0);
            }
            // Prefer completing all segments (op removed from grid = one less for opponents)
            if ($seg_num === count($segments)) $score += 5;
            $best_combo = $combo;
            $best_segment = $seg_num;
            $best_score = $score;
            break; // highest reachable segment found
        }
    }

    if (!$best_combo) return null;
    return array_merge($best_combo, ['target_segment' => $best_segment, '_score' => $best_score]);
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

    // Check ops grid
    foreach ($game['ops_grid'] as $slot) {
        $mid = is_array($slot) ? ($slot[0] ?? null) : $slot;
        if (!$mid || !isset($catalog[$mid])) continue;
        $mission = $catalog[$mid];
        $result = ai_find_mission_combo($p, $mission, $catalog);
        if (!$result) continue;
        $score = $result['_score'] ?? 0;
        if ($score > $best_score) {
            $best_score = $score;
            $best = array_merge(['mission_id' => $mid], $result);
            unset($best['_score']);
        }
    }

    // Try Heist if no good mission is available
    if ($best_score <= 0 && isset($catalog['heist'])) {
        $result = ai_find_mission_combo($p, $catalog['heist'], $catalog);
        if ($result) {
            $best = array_merge(['mission_id' => 'heist'], $result);
            unset($best['_score']);
        }
    }

    return $best;
}

/**
 * Play all no-target plot cards (Para-drop, Multitasking, Paperwork, Burglary).
 * Returns true if any were played.
 */
function ai_play_simple_plots(array &$game, int $pi, array $catalog): bool {
    $played = false;
    // Snapshot hand — it may change as we play cards
    $snap = $game['players'][$pi]['hand'];
    foreach ($snap as $cid) {
        if (!isset($catalog[$cid]) || $catalog[$cid]['type'] !== TYPE_PLOT) continue;
        $effect = $catalog[$cid]['effect'] ?? '';
        if (!in_array($effect, ['draw2', 'multitask', 'paperwork', 'burglary'])) continue;
        if (!in_array($cid, $game['players'][$pi]['hand'])) continue; // already played
        $result = action_play_plot($game, $pi, ['card_id' => $cid]);
        if ($result['ok'] ?? false) $played = true;
    }
    return $played;
}

/**
 * If the player has Reinforcements in hand and a backup agent would help complete
 * an available mission, play Reinforcements with the best such agent.
 */
function ai_maybe_play_reinforcements(array &$game, int $pi, array $catalog): void {
    $p = &$game['players'][$pi];
    if ($p['backup_agent'] ?? null) return; // already have one

    // Find a Reinforcements card in hand
    $reinf_id = null;
    foreach ($p['hand'] as $cid) {
        if (isset($catalog[$cid]) && ($catalog[$cid]['effect'] ?? '') === 'backup') {
            $reinf_id = $cid;
            break;
        }
    }
    if (!$reinf_id) return;

    // Candidate backup agents (other agents in hand)
    $hand_agents = array_values(array_filter(
        $p['hand'],
        fn($id) => $id !== $reinf_id && isset($catalog[$id]) && $catalog[$id]['type'] === TYPE_AGENT
    ));
    if (empty($hand_agents)) return;

    // Score each agent by icon count (more icons = better backup)
    usort($hand_agents, fn($a, $b) =>
        count($catalog[$b]['icons'] ?? []) - count($catalog[$a]['icons'] ?? [])
    );

    // Try the top candidate — see if it would unlock any mission
    foreach ($hand_agents as $backup_candidate) {
        // Temporarily simulate having the backup
        $p['backup_agent'] = $backup_candidate;
        $mission = ai_pick_best_mission($game, $pi, $catalog);
        $p['backup_agent'] = null;
        if ($mission !== null) {
            // This backup agent helps — play Reinforcements with it
            action_play_plot($game, $pi, [
                'card_id' => $reinf_id,
                'agent_card_id' => $backup_candidate,
            ]);
            return;
        }
    }
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

    // What icons are currently missing from completable grid missions
    $needed_icons = ai_needed_icons($game, $pi, $catalog);

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
        $score = ai_card_score($catalog[$cid], $needed_icons);
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
function ai_play_money_cards(array &$game, int $pi, array $catalog): void {
    $snap = $game['players'][$pi]['hand'];
    foreach ($snap as $cid) {
        if (!isset($catalog[$cid])) continue;
        $type = $catalog[$cid]['type'];
        $is_money = $type === TYPE_MONEY;
        $is_valued_mission = $type === TYPE_MISSION && ($catalog[$cid]['value'] ?? 0) > 0;
        if ($is_money || $is_valued_mission) {
            action_play_money($game, $pi, ['card_id' => $cid]);
        }
    }
}

function run_ai_turn(array &$game, int $pi, array $catalog): void {
    // 1. Play money cards and valued missions
    ai_play_money_cards($game, $pi, $catalog);

    // 2. Play simple plot cards (Para-drop draws more cards; Multitasking grants extra op;
    //    Paperwork/Burglary attack opponents and generate money)
    ai_play_simple_plots($game, $pi, $catalog);

    // Para-drop may have added cards to hand — play any newly drawn money
    ai_play_money_cards($game, $pi, $catalog);

    // 3. Play Reinforcements if it would unlock an otherwise-uncompletable mission
    ai_maybe_play_reinforcements($game, $pi, $catalog);

    // 4. Complete missions (loop in case Multitasking or extra_missions allow more)
    while (true) {
        if ($game['ended'] ?? false) break;
        $mission_params = ai_pick_best_mission($game, $pi, $catalog);
        if (!$mission_params) break;
        $result = action_complete_mission($game, $pi, $mission_params);
        if (!($result['ok'] ?? false)) break;
    }

    // 4b. Use any pending trashes — trash the weakest card from hand/discard
    while (($game['players'][$pi]['pending_trashes'] ?? 0) > 0) {
        $p = $game['players'][$pi];
        $candidates = [];
        foreach (['hand', 'discard'] as $area) {
            foreach ($p[$area] as $cid) {
                if (!isset($catalog[$cid])) continue;
                $candidates[] = ['id' => $cid, 'area' => $area, 'score' => ai_card_score($catalog[$cid])];
            }
        }
        if (empty($candidates)) break;
        usort($candidates, fn($a, $b) => $a['score'] - $b['score']);
        $worst = $candidates[0];
        $result = action_use_trash($game, $pi, ['target_card' => $worst['id'], 'target_area' => $worst['area']]);
        if (!($result['ok'] ?? false)) break;
    }

    // 5. Buy best marketplace card if available (loop for extra buys)
    while (true) {
        if ($game['ended'] ?? false) break;
        $buy_params = ai_pick_best_buy($game, $pi, $catalog);
        if (!$buy_params) break;
        $result = action_buy_card($game, $pi, $buy_params);
        if (!($result['ok'] ?? false)) break;
    }

    // 6. Convert leftover money to gems ($3 each) to save up for expensive cards
    while (!($game['ended'] ?? false) && $game['players'][$pi]['money'] >= 3) {
        $result = action_buy_gem($game, $pi, []);
        if (!($result['ok'] ?? false)) break;
    }

    // 7. End turn
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
        $game['version']++; // AI handled defense — let the client see the cleared attack
        return ['ok' => true];
    }
    if ($game['ended'] ?? false) return ['ok' => true];

    run_ai_turn($game, $pi, $catalog);
    $game['version']++;
    return ['ok' => true];
}
