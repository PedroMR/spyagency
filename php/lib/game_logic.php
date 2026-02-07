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
    if (!isset($catalog[$card_id]) || $catalog[$card_id]['type'] !== TYPE_MONEY) {
        return ['ok' => false, 'error' => 'Not a money card'];
    }
    if (!remove_from_array($game['players'][$pi]['hand'], $card_id)) {
        return ['ok' => false, 'error' => 'Card not in hand'];
    }
    $game['players'][$pi]['play_area'][] = $card_id;
    $game['players'][$pi]['money'] += $catalog[$card_id]['value'];
    $game['log'][] = $game['players'][$pi]['name'] . " played {$catalog[$card_id]['name']}";
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

        case 'recall':
            // Emergency Recall - return an agent from a base to hand
            $base_idx = $params['base_index'] ?? -1;
            if (!isset($game['players'][$pi]['bases'][$base_idx]) || $game['players'][$pi]['bases'][$base_idx]['agent'] === null) {
                $game['players'][$pi]['hand'][] = $card_id;
                return ['ok' => false, 'error' => 'No agent at that base'];
            }
            $agent_id = $game['players'][$pi]['bases'][$base_idx]['agent'];
            $game['players'][$pi]['hand'][] = $agent_id;
            // Tech goes to discard
            foreach ($game['players'][$pi]['bases'][$base_idx]['tech'] as $t) {
                $game['players'][$pi]['discard'][] = $t;
            }
            $game['players'][$pi]['bases'][$base_idx]['agent'] = null;
            $game['players'][$pi]['bases'][$base_idx]['tech'] = [];
            $game['players'][$pi]['play_area'][] = $card_id;
            $game['log'][] = $game['players'][$pi]['name'] . " played Emergency Recall, returning {$catalog[$agent_id]['name']} to hand";
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

    // Always-available cards (Barnstormer / Shadow)
    if ($card_id === 'barnstormer' || $card_id === 'shadow') {
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

function action_buy_base(array &$game, int $pi, array $params): array {
    $extra = $game['players'][$pi]['extra_base_count'];
    $cost = 6 + ($extra * 3);
    if ($game['players'][$pi]['money'] < $cost) {
        return ['ok' => false, 'error' => "Not enough money (need \${$cost})"];
    }
    $game['players'][$pi]['money'] -= $cost;
    $game['players'][$pi]['extra_base_count']++;
    // Alternate base types
    $base_type = ($extra % 2 === 0) ? 'safehouse' : 'hideaway';
    $game['players'][$pi]['bases'][] = [
        'type' => $base_type,
        'agent' => null,
        'tech' => [],
    ];
    $game['log'][] = $game['players'][$pi]['name'] . " bought a new base for \${$cost}";
    return ['ok' => true];
}

function action_refresh_market(array &$game, int $pi, array $params): array {
    if ($game['players'][$pi]['money'] < 2) {
        return ['ok' => false, 'error' => 'Not enough money (need $2)'];
    }
    $game['players'][$pi]['money'] -= 2;
    // Discard current marketplace cards
    foreach ($game['marketplace'] as $card) {
        if ($card !== null) {
            // Marketplace discards go to... a general discard? They leave the game essentially
            // Actually they go to bottom of market deck or are removed. Let's just remove them.
        }
    }
    // Refill marketplace
    $game['marketplace'] = [];
    for ($i = 0; $i < 5 && count($game['market_deck']) > 0; $i++) {
        $game['marketplace'][] = array_shift($game['market_deck']);
    }
    // Pad with nulls if deck ran out
    while (count($game['marketplace']) < 5) {
        $game['marketplace'][] = null;
    }
    $game['log'][] = $game['players'][$pi]['name'] . " refreshed the marketplace for \$2";
    return ['ok' => true];
}

function resolve_icons(string $card_id, array $choices, array $catalog): array {
    $card = $catalog[$card_id];
    $icons = [];
    $choice_idx = 0;
    foreach ($card['icons'] as $icon) {
        if (is_array($icon)) {
            // This is a choice - pick from choices array
            $chosen = $choices[$choice_idx] ?? $icon[0];
            if (!in_array($chosen, $icon)) {
                $chosen = $icon[0]; // fallback to first option
            }
            $icons[] = $chosen;
            $choice_idx++;
        } else {
            $icons[] = $icon;
        }
    }
    return $icons;
}

function action_complete_mission(array &$game, int $pi, array $params): array {
    $mission_id = $params['mission_id'] ?? '';
    $base_idx = $params['base_index'] ?? -1;
    $icon_choices = $params['icon_choices'] ?? []; // choices for agent + tech "or" icons
    $catalog = get_card_catalog();

    if (!isset($catalog[$mission_id]) || $catalog[$mission_id]['type'] !== TYPE_MISSION) {
        return ['ok' => false, 'error' => 'Not a valid mission'];
    }

    $mission = $catalog[$mission_id];

    // Find mission in grid or check if it's always available
    $found_tier = null;
    $found_idx = null;
    $is_fundraising = ($mission_id === 'fundraising');

    if (!$is_fundraising) {
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
    $agent_id = $base['agent'];

    // Collect icons from agent + tech + backup
    $all_icons = [];
    $agent_choices = $icon_choices['agent'] ?? [];
    $all_icons = array_merge($all_icons, resolve_icons($agent_id, $agent_choices, $catalog));

    foreach ($base['tech'] as $ti => $tech_id) {
        $tech_choices = $icon_choices['tech'][$ti] ?? [];
        $all_icons = array_merge($all_icons, resolve_icons($tech_id, $tech_choices, $catalog));
    }

    // Add backup agent icons if present
    $backup = $game['players'][$pi]['backup_agent'] ?? null;
    if ($backup) {
        $backup_choices = $icon_choices['backup'] ?? [];
        $all_icons = array_merge($all_icons, resolve_icons($backup, $backup_choices, $catalog));
    }

    // Check requirements
    $requirements = $mission['requirements'];
    $available = $all_icons;
    foreach ($requirements as $req) {
        if ($req === 'any') {
            // Any icon will do
            if (empty($available)) {
                return ['ok' => false, 'error' => 'Not enough icons to complete mission'];
            }
            array_shift($available);
        } else {
            $idx = array_search($req, $available);
            if ($idx === false) {
                return ['ok' => false, 'error' => "Missing required icon: {$req}"];
            }
            array_splice($available, $idx, 1);
        }
    }

    // Success! Complete the mission
    // Discard agent and tech from base
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
    if (!$is_fundraising && $found_tier !== null) {
        array_splice($game['mission_grid'][$found_tier], $found_idx, 1);
    }

    // Add mission card to discard (it has stars)
    $game['players'][$pi]['discard'][] = $mission_id;

    // Add reward money cards
    foreach ($mission['reward_money'] as $val) {
        $game['players'][$pi]['discard'][] = 'money_' . $val;
    }

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

    // Refill marketplace
    foreach ($game['marketplace'] as $i => $card) {
        if ($card === null && count($game['market_deck']) > 0) {
            $game['marketplace'][$i] = array_shift($game['market_deck']);
        }
    }
    // If marketplace has fewer than 5 slots (some were null and deck empty), keep them
    while (count($game['marketplace']) < 5) {
        $game['marketplace'][] = null;
    }

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
