<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spy Agency - Game</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🕵️</text></svg>">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__.'/css/style.css') ?>">
    <script>
        if (/Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
                && !new URLSearchParams(location.search).get('desktop')) {
            window.location.replace('mobile/game.php' + location.search);
        }
    </script>
</head>
<body>
    <div class="game-container">
        <div class="game-header">
            <h2>Spy Agency</h2>
            <div class="turn-info">
                <span id="round-display"></span>
                <span id="turn-indicator"></span>
                <span id="money-display"></span>
                <span id="gems-display"></span>
                <span id="stars-display"></span>
                <span id="missions-display"></span>
                <span id="buys-display"></span>
            </div>
            <div class="game-header-actions">
                <button class="rules-btn" onclick="rulesOpen()">📖 Rules</button>
                <button id="btn-resign" onclick="UI.confirmResign()">Resign</button>
            </div>
        </div>

        <div id="final-round-banner" class="final-round-banner" style="display:none">
            Final Round!
        </div>

        <div class="game-board">
            <div class="board-top">
                <div class="section marketplace-section">
                    <h3>Marketplace <span id="market-deck-count" class="deck-count"></span></h3>
                    <div id="marketplace" class="card-row"></div>
                    <div class="market-actions">
                        <div id="always-available-cards" class="card-row"></div>
                        <button onclick="Actions.refreshMarket()">Restock ($2)</button>
                    </div>
                </div>

                <div class="section missions-section">
                    <h3>Op Grid</h3>
                    <div class="heist-row">
                        <div id="heist-mission" class="card mission-card" onclick="UI.showMissionDialog('heist')">
                        </div>
                    </div>
                    <div id="mission-grid" class="mission-grid"></div>
                </div>

                <div class="section opponents-section">
                    <h3>Players</h3>
                    <div id="opponents"></div>
                </div>
            </div>

            <div class="board-bottom">
                <div class="deck-discard-column">
                    <div class="section deck-section">
                        <h3>Deck</h3>
                        <h3 id="my-deck-count" class="deck-count-display"></h3>
                    </div>
                    <div class="section discard-section">
                        <h3>Discard <span id="discard-count" class="deck-count"></span></h3>
                        <div id="discard-pile"></div>
                    </div>
                </div>

                <div class="section hand-section">
                    <div class="my-resources" id="my-resources">$6 💎7</div>
                    <h3>Your Hand</h3>
                    <div id="my-hand" class="card-row"></div>
                </div>

                <div class="section play-area-section">
                    <h3>Play Area</h3>
                    <div id="play-area" class="card-row"></div>
                    <div id="my-base"></div>
                </div>

                <div class="section mission-area-section" id="mission-area-section" style="display:none">
                    <h3>Op Area <span id="mission-income-display" class="mission-income"></span></h3>
                    <div id="my-mission-area" class="card-row"></div>
                </div>

                <div class="end-turn-column">
                    <button id="btn-end-turn" onclick="Actions.endTurn()" style="height:100%" disabled>End Turn</button>
                </div>
            </div>
        </div>

        <div class="game-log">
            <h4>Game Log</h4>
            <div id="game-log"></div>
        </div>
        <div id="debug-buttons" style="display:none;gap:6px;margin-top:6px">
            <button onclick="Actions.debugEndGame()">Debug: End Game</button>
            <button onclick="Actions.debugAddGems()">+10 💎</button>
            <button onclick="Actions.debugAddBuys()">+5 Buys</button>
            <button onclick="Actions.debugAddMissions()">+5 Ops</button>
        </div>
    </div>

    <!-- Modal for mission completion -->
    <div id="modal-overlay" class="modal-overlay" style="display:none" onclick="UI.closeModal()">
        <div class="modal" onclick="event.stopPropagation()">
            <div id="modal-content"></div>
        </div>
    </div>

    <?php include __DIR__.'/lib/rules_widget.php'; ?>
    <script src="js/config.js?v=<?= filemtime(__DIR__.'/js/config.js') ?>"></script>
    <script src="js/actions.js?v=<?= filemtime(__DIR__.'/js/actions.js') ?>"></script>
    <script src="js/ui.js?v=<?= filemtime(__DIR__.'/js/ui.js') ?>"></script>
    <script src="js/poller.js?v=<?= filemtime(__DIR__.'/js/poller.js') ?>"></script>
    <script src="js/game.js?v=<?= filemtime(__DIR__.'/js/game.js') ?>"></script>
</body>
</html>
