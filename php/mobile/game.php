<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Spy Agency - Game</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🕵️</text></svg>">
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__.'/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/mobile.css?v=<?= filemtime(__DIR__.'/../css/mobile.css') ?>">
</head>
<body class="mobile">

    <!-- Fixed header: title + resources + resign -->
    <div class="mob-header">
        <span class="mob-title">🕵️ Spy Agency</span>
        <span id="money-display" class="mob-stat"></span>
        <span id="gems-display" class="mob-stat"></span>
        <span id="stars-display" class="mob-stat"></span>
        <span id="my-resources" style="display:none"></span>
        <button class="rules-btn" onclick="rulesOpen()" style="font-size:13px;padding:4px 8px">📖</button>
        <button id="btn-resign" class="mob-resign-btn" onclick="UI.confirmResign()">Resign</button>
    </div>

    <!-- Fixed subheader: round, turn, counters -->
    <div class="mob-subheader">
        <span id="round-display"></span>
        <span id="turn-indicator"></span>
        <span id="missions-display"></span>
        <span id="buys-display"></span>
    </div>

    <!-- Final round banner (fixed, shown when relevant) -->
    <div id="final-round-banner" class="final-round-banner" style="display:none">Final Round!</div>

    <!-- Scrollable content area -->
    <div class="mob-content" id="mob-content">
    <div class="mob-tabs-track" id="mob-tabs-track">

        <!-- Hand tab -->
        <div id="tab-hand" class="mob-tab-panel">
            <div class="mob-deck-discard-row">
                <div class="section deck-section">
                    <h3>Deck</h3>
                    <div id="my-deck-count" class="deck-count-display"></div>
                </div>
                <div class="section discard-section">
                    <h3>Discard <span id="discard-count" class="deck-count"></span></h3>
                    <div id="discard-pile"></div>
                </div>
            </div>
            <div class="section mission-area-section" id="mission-area-section" style="display:none">
                <h3>Op Area <span id="mission-income-display" class="mission-income"></span></h3>
                <div id="my-mission-area" class="card-row"></div>
            </div>
            <div class="section play-area-section">
                <h3>Play Area</h3>
                <div id="play-area" class="card-row"></div>
                <div id="my-base"></div>
            </div>
            <div class="section hand-section">
                <h3>Your Hand</h3>
                <div id="my-hand" class="card-row"></div>
            </div>
        </div>

        <!-- Market tab -->
        <div id="tab-market" class="mob-tab-panel">
            <div class="section marketplace-section">
                <h3>Marketplace <span id="market-deck-count" class="deck-count"></span></h3>
                <div id="marketplace" class="card-row"></div>
                <div class="market-actions">
                    <div id="always-available-cards" class="card-row"></div>
                    <button id="btn-restock" onclick="Actions.refreshMarket()">Restock ($2)</button>
                </div>
            </div>
        </div>

        <!-- Ops tab -->
        <div id="tab-ops" class="mob-tab-panel">
            <div class="section missions-section">
                <h3>Op Grid</h3>
                <div class="heist-row">
                    <div id="heist-mission" class="card mission-card" onclick="UI.showMissionDialog('heist')"></div>
                </div>
                <div id="mission-grid" class="mission-grid"></div>
            </div>
        </div>

        <!-- Log tab -->
        <div id="tab-log" class="mob-tab-panel">
            <div class="section opponents-section">
                <h3>Players</h3>
                <div id="opponents"></div>
            </div>
            <div class="section mob-log-section">
                <h4>Game Log</h4>
                <div id="game-log"></div>
            </div>
        </div>

    </div><!-- /.mob-tabs-track -->
    </div><!-- /.mob-content -->

    <!-- Debug buttons (only shown on localhost) -->
    <div id="debug-buttons" style="display:none;gap:6px;margin:6px 10px">
        <button onclick="Actions.debugEndGame()">Debug: End Game</button>
        <button onclick="Actions.debugAddGems()">+10 💎</button>
        <button onclick="Actions.debugAddBuys()">+5 Buys</button>
        <button onclick="Actions.debugAddMissions()">+5 Ops</button>
    </div>

    <!-- Fixed tab bar -->
    <div class="mob-tab-bar">
        <button class="mob-tab-btn active" data-tab="hand" onclick="UI.switchTab('hand')">✋<br>Hand</button>
        <button class="mob-tab-btn" data-tab="market" onclick="UI.switchTab('market')">🛒<br>Market</button>
        <button class="mob-tab-btn" data-tab="ops" onclick="UI.switchTab('ops')">🎯<br>Ops</button>
        <button class="mob-tab-btn" data-tab="log" onclick="UI.switchTab('log')">📋<br>Log</button>
        <button id="btn-end-turn" class="mob-end-turn-btn" onclick="Actions.endTurn()" disabled>⏹<br>End Turn</button>
    </div>

    <!-- Modal overlay (same structure as desktop) -->
    <div id="modal-overlay" class="modal-overlay" style="display:none" onclick="UI.closeModal()">
        <div class="modal" onclick="event.stopPropagation()">
            <div id="modal-content"></div>
        </div>
    </div>

    <?php include __DIR__.'/../lib/rules_widget.php'; ?>
    <script>window._API_BASE = '../';</script>
    <script src="../js/config.js?v=<?= filemtime(__DIR__.'/../js/config.js') ?>"></script>
    <script src="../js/actions.js?v=<?= filemtime(__DIR__.'/../js/actions.js') ?>"></script>
    <script src="../js/ui.js?v=<?= filemtime(__DIR__.'/../js/ui.js') ?>"></script>
    <script src="../js/mobile-ui.js?v=<?= filemtime(__DIR__.'/../js/mobile-ui.js') ?>"></script>
    <script src="../js/poller.js?v=<?= filemtime(__DIR__.'/../js/poller.js') ?>"></script>
    <script src="../js/game.js?v=<?= filemtime(__DIR__.'/../js/game.js') ?>"></script>
</body>
</html>
