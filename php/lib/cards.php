<?php

// Icon constants
define('ICON_DRIVE', 'drive');
define('ICON_MUSCLE', 'muscle');
define('ICON_DISGUISE', 'disguise');
define('ICON_KEY', 'key');

// Card type constants
define('TYPE_MONEY', 'money');
define('TYPE_AGENT', 'agent');
define('TYPE_TECH', 'tech');
define('TYPE_PLOT', 'plot');
define('TYPE_BASE', 'base');
define('TYPE_MISSION', 'mission');

function get_icon_label(string $icon): string {
    $map = [
        ICON_DRIVE => '🚘',
        ICON_MUSCLE => '💪',
        ICON_DISGUISE => '🥸',
        ICON_KEY => '🔑',
    ];
    return $map[$icon] ?? $icon;
}

function get_card_catalog(): array {
    return [
        // Money cards (starter)
        'money_1' => [
            'id' => 'money_1', 'name' => '$1', 'type' => TYPE_MONEY,
            'tier' => 0, 'cost' => 0, 'value' => 1, 'stars' => 0,
            'description' => '$1',
        ],
        'money_2' => [
            'id' => 'money_2', 'name' => '$2', 'type' => TYPE_MONEY,
            'tier' => 0, 'cost' => 0, 'value' => 2, 'stars' => 0,
            'description' => '$2',
        ],
        'money_3' => [
            'id' => 'money_3', 'name' => '$3', 'type' => TYPE_MONEY,
            'tier' => 0, 'cost' => 0, 'value' => 3, 'stars' => 0,
            'description' => '$3',
        ],

        // Bases
        'safehouse' => [
            'id' => 'safehouse', 'name' => 'Safehouse', 'type' => TYPE_BASE,
            'tier' => 0, 'cost' => 0, 'stars' => 0,
            'indestructible' => true,
            'description' => 'Can host an Agent. Indestructible.',
        ],
        'hideaway' => [
            'id' => 'hideaway', 'name' => 'Hideaway', 'type' => TYPE_BASE,
            'tier' => 0, 'cost' => 0, 'stars' => 0,
            'indestructible' => false,
            'description' => 'Can host an Agent.',
        ],

        // Agents - Tier 1 (always available)
        'barnstormer' => [
            'id' => 'barnstormer', 'name' => 'Barnstormer', 'type' => TYPE_AGENT,
            'tier' => 1, 'cost' => 3, 'stars' => 0, 'max_tech' => 1,
            'icons' => [[ICON_DRIVE, ICON_MUSCLE]], // choice
            'always_available' => true,
            'description' => '🚘/💪 — Always available ($3)',
        ],
        'shadow' => [
            'id' => 'shadow', 'name' => 'Shadow', 'type' => TYPE_AGENT,
            'tier' => 1, 'cost' => 4, 'stars' => 0, 'max_tech' => 1,
            'icons' => [[ICON_KEY, ICON_DISGUISE]], // choice
            'always_available' => true,
            'description' => '🔑/🥸 — Always available ($4)',
        ],

        // Agents - Tier 2
        'foot_in_the_door' => [
            'id' => 'foot_in_the_door', 'name' => 'Foot in the Door', 'type' => TYPE_AGENT,
            'tier' => 2, 'cost' => 5, 'stars' => 0, 'max_tech' => 1,
            'icons' => [ICON_MUSCLE, ICON_KEY],
            'description' => '💪🔑 ($5)',
        ],
        'reputable_driver' => [
            'id' => 'reputable_driver', 'name' => 'Reputable Driver', 'type' => TYPE_AGENT,
            'tier' => 2, 'cost' => 5, 'stars' => 0, 'max_tech' => 1,
            'icons' => [ICON_DISGUISE, ICON_DRIVE],
            'description' => '🥸🚘 ($5)',
        ],

        // Agents - Tier 3
        'speedboat_mechanic' => [
            'id' => 'speedboat_mechanic', 'name' => 'Speedboat Mechanic', 'type' => TYPE_AGENT,
            'tier' => 3, 'cost' => 7, 'stars' => 0, 'max_tech' => 2,
            'icons' => [ICON_MUSCLE, ICON_MUSCLE, ICON_DRIVE],
            'description' => '💪💪🚘 ($7)',
        ],
        'hoodied_hacker' => [
            'id' => 'hoodied_hacker', 'name' => 'Hoodied Hacker', 'type' => TYPE_AGENT,
            'tier' => 3, 'cost' => 8, 'stars' => 0, 'max_tech' => 2,
            'icons' => [ICON_KEY, ICON_KEY, ICON_DISGUISE],
            'description' => '🔑🔑🥸 ($8)',
        ],

        // Tech - Tier 1
        'sword_cane' => [
            'id' => 'sword_cane', 'name' => 'Sword-cane', 'type' => TYPE_TECH,
            'tier' => 1, 'cost' => 4, 'stars' => 0,
            'icons' => [[ICON_MUSCLE, ICON_DISGUISE]], // choice
            'description' => '💪/🥸 ($4)',
        ],
        'ram' => [
            'id' => 'ram', 'name' => 'Ram', 'type' => TYPE_TECH,
            'tier' => 1, 'cost' => 4, 'stars' => 0,
            'icons' => [[ICON_DRIVE, ICON_KEY]], // choice
            'description' => '🚘/🔑 ($4)',
        ],

        // Tech - Tier 2
        'bazooka' => [
            'id' => 'bazooka', 'name' => 'Bazooka', 'type' => TYPE_TECH,
            'tier' => 2, 'cost' => 7, 'stars' => 0,
            'icons' => [ICON_MUSCLE, ICON_MUSCLE],
            'description' => '💪💪 ($7)',
        ],
        'holo_projector' => [
            'id' => 'holo_projector', 'name' => 'Holo-projector', 'type' => TYPE_TECH,
            'tier' => 2, 'cost' => 8, 'stars' => 0,
            'icons' => [ICON_DISGUISE, ICON_DISGUISE],
            'description' => '🥸🥸 ($8)',
        ],

        // Plots
        'red_tape' => [
            'id' => 'red_tape', 'name' => 'Red Tape', 'type' => TYPE_PLOT,
            'tier' => 0, 'cost' => 0, 'stars' => 0,
            'effect' => 'none',
            'description' => 'Useless bureaucracy. Does nothing.',
        ],
        'burn_notice' => [
            'id' => 'burn_notice', 'name' => 'Burn Notice', 'type' => TYPE_PLOT,
            'tier' => 2, 'cost' => 5, 'stars' => 0,
            'effect' => 'trash',
            'description' => 'Trash a card from your play area, discard pile, or hand. ($5)',
        ],
        'emergency_recall' => [
            'id' => 'emergency_recall', 'name' => 'Emergency Recall', 'type' => TYPE_PLOT,
            'tier' => 2, 'cost' => 5, 'stars' => 0,
            'effect' => 'recall',
            'description' => 'Return an agent from a base to your hand. ($5)',
        ],
        'para_drop' => [
            'id' => 'para_drop', 'name' => 'Para-drop', 'type' => TYPE_PLOT,
            'tier' => 2, 'cost' => 5, 'stars' => 0,
            'effect' => 'draw2',
            'description' => 'Draw two cards. ($5)',
        ],
        'got_your_back' => [
            'id' => 'got_your_back', 'name' => 'Got Your Back!', 'type' => TYPE_PLOT,
            'tier' => 3, 'cost' => 7, 'stars' => 0,
            'effect' => 'backup',
            'description' => 'Play an agent from your hand as backup for your next mission this turn. ($7)',
        ],

        // Missions - Tier 1
        'alley_getaway' => [
            'id' => 'alley_getaway', 'name' => 'Alley Getaway', 'type' => TYPE_MISSION,
            'tier' => 1, 'cost' => 0, 'stars' => 0,
            'requirements' => [ICON_DRIVE, ICON_MUSCLE],
            'reward_money' => [3],
            'description' => 'Requires: 🚘💪 — Reward: $3',
        ],
        'arm_wrestling' => [
            'id' => 'arm_wrestling', 'name' => 'Arm Wrestling', 'type' => TYPE_MISSION,
            'tier' => 1, 'cost' => 0, 'stars' => 0,
            'requirements' => [ICON_MUSCLE, ICON_DISGUISE],
            'reward_money' => [2, 2],
            'description' => 'Requires: 💪🥸 — Reward: $2 $2',
        ],
        'convent_secrets' => [
            'id' => 'convent_secrets', 'name' => 'Convent Secrets', 'type' => TYPE_MISSION,
            'tier' => 1, 'cost' => 0, 'stars' => 0,
            'requirements' => [ICON_DISGUISE, ICON_KEY],
            'reward_money' => [3, 2],
            'description' => 'Requires: 🥸🔑 — Reward: $3 $2',
        ],
        'gate_hacking' => [
            'id' => 'gate_hacking', 'name' => 'Gate Hacking', 'type' => TYPE_MISSION,
            'tier' => 1, 'cost' => 0, 'stars' => 0,
            'requirements' => [ICON_KEY, ICON_DRIVE],
            'reward_money' => [2, 2],
            'description' => 'Requires: 🔑🚘 — Reward: $2 $2',
        ],

        // Missions - Tier 2
        'train_hijinx' => [
            'id' => 'train_hijinx', 'name' => 'Train Hijinx', 'type' => TYPE_MISSION,
            'tier' => 2, 'cost' => 0, 'stars' => 1,
            'requirements' => [ICON_DRIVE, ICON_DRIVE, ICON_DRIVE, ICON_DISGUISE],
            'reward_money' => [2, 2],
            'description' => 'Requires: 🚘🚘🚘🥸 — Reward: 1⭐ $2 $2',
        ],
        'fencing_duel' => [
            'id' => 'fencing_duel', 'name' => 'Fencing Duel', 'type' => TYPE_MISSION,
            'tier' => 2, 'cost' => 0, 'stars' => 1,
            'requirements' => [ICON_MUSCLE, ICON_MUSCLE, ICON_MUSCLE, ICON_KEY],
            'reward_money' => [2, 2],
            'description' => 'Requires: 💪💪💪🔑 — Reward: 1⭐ $2 $2',
        ],
        'traveling_troupe' => [
            'id' => 'traveling_troupe', 'name' => 'Traveling Troupe', 'type' => TYPE_MISSION,
            'tier' => 2, 'cost' => 0, 'stars' => 1,
            'requirements' => [ICON_DISGUISE, ICON_DISGUISE, ICON_DISGUISE, ICON_DRIVE],
            'reward_money' => [2, 2],
            'description' => 'Requires: 🥸🥸🥸🚘 — Reward: 1⭐ $2 $2',
        ],
        'retinal_scan' => [
            'id' => 'retinal_scan', 'name' => 'Retinal Scan', 'type' => TYPE_MISSION,
            'tier' => 2, 'cost' => 0, 'stars' => 1,
            'requirements' => [ICON_KEY, ICON_KEY, ICON_KEY, ICON_MUSCLE],
            'reward_money' => [2, 2],
            'description' => 'Requires: 🔑🔑🔑💪 — Reward: 1⭐ $2 $2',
        ],

        // Missions - Tier 3
        'halo_drop' => [
            'id' => 'halo_drop', 'name' => 'HALO Drop', 'type' => TYPE_MISSION,
            'tier' => 3, 'cost' => 0, 'stars' => 4,
            'requirements' => [ICON_DRIVE, ICON_DRIVE, ICON_DRIVE, ICON_DRIVE, ICON_DRIVE, ICON_KEY, ICON_KEY],
            'reward_money' => [],
            'description' => 'Requires: 🚘🚘🚘🚘🚘🔑🔑 — Reward: 4⭐',
        ],
        'rooftop_showdown' => [
            'id' => 'rooftop_showdown', 'name' => 'Rooftop Showdown', 'type' => TYPE_MISSION,
            'tier' => 3, 'cost' => 0, 'stars' => 4,
            'requirements' => [ICON_MUSCLE, ICON_MUSCLE, ICON_MUSCLE, ICON_MUSCLE, ICON_MUSCLE, ICON_DRIVE, ICON_DRIVE],
            'reward_money' => [],
            'description' => 'Requires: 💪💪💪💪💪🚘🚘 — Reward: 4⭐',
        ],
        'megastar_machinations' => [
            'id' => 'megastar_machinations', 'name' => 'Megastar Machinations', 'type' => TYPE_MISSION,
            'tier' => 3, 'cost' => 0, 'stars' => 4,
            'requirements' => [ICON_DISGUISE, ICON_DISGUISE, ICON_DISGUISE, ICON_DISGUISE, ICON_DISGUISE, ICON_MUSCLE, ICON_MUSCLE],
            'reward_money' => [],
            'description' => 'Requires: 🥸🥸🥸🥸🥸💪💪 — Reward: 4⭐',
        ],
        'embassy_safe' => [
            'id' => 'embassy_safe', 'name' => 'Embassy Safe', 'type' => TYPE_MISSION,
            'tier' => 3, 'cost' => 0, 'stars' => 4,
            'requirements' => [ICON_KEY, ICON_KEY, ICON_KEY, ICON_KEY, ICON_KEY, ICON_DISGUISE, ICON_DISGUISE],
            'reward_money' => [],
            'description' => 'Requires: 🔑🔑🔑🔑🔑🥸🥸 — Reward: 4⭐',
        ],

        // Fundraising (always available mission)
        'fundraising' => [
            'id' => 'fundraising', 'name' => 'Fundraising', 'type' => TYPE_MISSION,
            'tier' => 0, 'cost' => 0, 'stars' => 0,
            'requirements' => ['any', 'any'],
            'reward_money' => [2],
            'always_available' => true,
            'description' => 'Requires: any 2 icons — Reward: $2. Always available.',
        ],
    ];
}

/**
 * Build the market deck (all non-always-available agents, tech, plots)
 */
function build_market_deck(): array {
    $catalog = get_card_catalog();
    $deck = [];
    $market_cards = [
        // Agents (not always-available ones go in market)
        'foot_in_the_door' => 4,
        'reputable_driver' => 4,
        'speedboat_mechanic' => 4,
        'hoodied_hacker' => 4,
        // Tech
        'sword_cane' => 2,
        'ram' => 2,
        'bazooka' => 1,
        'holo_projector' => 1,
        // Plots
        'burn_notice' => 4,
        'emergency_recall' => 4,
        'para_drop' => 4,
        'got_your_back' => 4,
    ];
    foreach ($market_cards as $id => $count) {
        for ($i = 0; $i < $count; $i++) {
            $deck[] = $id;
        }
    }
    shuffle($deck);
    return $deck;
}

/**
 * Build mission decks by tier
 */
function build_mission_decks(): array {
    $missions = [
        1 => [
            'alley_getaway' => 2,
            'arm_wrestling' => 2,
            'convent_secrets' => 2,
            'gate_hacking' => 2,
        ],
        2 => [
            'train_hijinx' => 2,
            'fencing_duel' => 2,
            'traveling_troupe' => 2,
            'retinal_scan' => 2,
        ],
        3 => [
            'halo_drop' => 1,
            'rooftop_showdown' => 1,
            'megastar_machinations' => 1,
            'embassy_safe' => 1,
        ],
    ];
    $decks = [];
    foreach ($missions as $tier => $cards) {
        $deck = [];
        foreach ($cards as $id => $count) {
            for ($i = 0; $i < $count; $i++) {
                $deck[] = $id;
            }
        }
        shuffle($deck);
        $decks[$tier] = $deck;
    }
    return $decks;
}

function get_starter_deck(): array {
    $deck = [];
    for ($i = 0; $i < 5; $i++) $deck[] = 'money_1';
    $deck[] = 'barnstormer';
    $deck[] = 'shadow';
    $deck[] = 'red_tape';
    shuffle($deck);
    return $deck;
}
