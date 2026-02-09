<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spy Agency - Lobby</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="lobby-container">
        <h1>🕵️ Spy Agency</h1>

        <div class="player-setup" id="player-setup">
            <label for="player-name">Your Name:</label>
            <input type="text" id="player-name" placeholder="Agent codename..." maxlength="20">
            <button id="btn-save-name" onclick="saveName()">Save</button>
        </div>

        <div class="lobby-actions" id="lobby-actions" style="display:none">
            <p>Welcome, <strong id="display-name"></strong>!</p>

            <div class="create-room">
                <h3>Create a Room</h3>
                <input type="text" id="room-name" placeholder="Room name..." maxlength="30">
                <button onclick="createRoom()">Create</button>
            </div>

            <div class="room-list">
                <h3>Available Rooms</h3>
                <div id="rooms"></div>
            </div>
        </div>

        <div id="waiting-room" style="display:none">
            <h3>Waiting Room: <span id="waiting-room-name"></span></h3>
            <div id="waiting-players"></div>
            <button id="btn-start" style="display:none" onclick="startGame()">Start Game</button>
            <p id="waiting-msg">Waiting for host to start...</p>
        </div>
    </div>

    <div id="lobby-log" style="max-width:600px;margin:20px auto;padding:10px;background:#111128;border-radius:8px;max-height:150px;overflow-y:auto;font-family:monospace;font-size:11px;color:#aaa;"></div>

    <script src="js/config.js"></script>
    <script src="js/lobby.js"></script>
</body>
</html>
