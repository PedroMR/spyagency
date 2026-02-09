// Player token management
function getToken() {
    let token = localStorage.getItem('spy_token');
    if (!token) {
        token = Array.from(crypto.getRandomValues(new Uint8Array(16)))
            .map(b => b.toString(16).padStart(2, '0')).join('');
        localStorage.setItem('spy_token', token);
    }
    return token;
}

function getPlayerName() {
    return localStorage.getItem('spy_name') || '';
}

function saveName() {
    const name = document.getElementById('player-name').value.trim();
    if (!name) return alert('Enter a name!');
    localStorage.setItem('spy_name', name);
    showLobby();
}

function showLobby() {
    const name = getPlayerName();
    if (!name) {
        document.getElementById('player-setup').style.display = 'block';
        document.getElementById('lobby-actions').style.display = 'none';
        return;
    }
    document.getElementById('player-setup').style.display = 'none';
    document.getElementById('lobby-actions').style.display = 'block';
    document.getElementById('display-name').textContent = name;
    document.getElementById('player-name').value = name;
    refreshRooms();
}

let currentRoomId = null;
let roomPollInterval = null;

async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data),
    });
    return res.json();
}

async function createRoom() {
    const roomName = document.getElementById('room-name').value.trim();    
    if (!roomName) return alert('Enter a room name');
    if (roomName.length > 30) return alert('Room name too big (>30)');
    const res = await apiPost('api/room_create.php', {
        room_name: roomName,
        player_name: getPlayerName(),
        token: getToken(),
    });
    if (!res.ok) return alert(res.error);
    document.getElementById('room-name').value = '';
    currentRoomId = res.room_id;
    showWaitingRoom(roomName, true);
}

async function joinRoom(roomId, roomName) {
    const res = await apiPost('api/room_join.php', {
        room_id: roomId,
        player_name: getPlayerName(),
        token: getToken(),
    });
    if (!res.ok) return alert(res.error);
    currentRoomId = roomId;
    showWaitingRoom(roomName, false);
}

function showWaitingRoom(roomName, isHost) {
    document.getElementById('lobby-actions').style.display = 'none';
    document.getElementById('waiting-room').style.display = 'block';
    document.getElementById('waiting-room-name').textContent = roomName;
    document.getElementById('btn-start').style.display = isHost ? 'inline-block' : 'none';
    document.getElementById('waiting-msg').style.display = isHost ? 'none' : 'block';
    pollWaitingRoom();
}

function pollWaitingRoom() {
    if (roomPollInterval) clearInterval(roomPollInterval);
    roomPollInterval = setInterval(async () => {
        const res = await fetch('api/room_list.php');
        const data = await res.json();
        if (!data.ok) return;
        const room = data.rooms.find(r => r.id === currentRoomId);
        if (!room) return;
        if (room.status === 'started') {
            clearInterval(roomPollInterval);
            localStorage.setItem('spy_game_id', room.game_id);
            localStorage.setItem('spy_room_id', room.id);
            window.location.href = 'game.php';
            return;
        }
        const el = document.getElementById('waiting-players');
        el.innerHTML = '<ul>' + room.players.map(n => `<li>${escHtml(n)}</li>`).join('') + '</ul>';
    }, 1500);
}

async function startGame() {
    const res = await apiPost('api/room_start.php', {
        room_id: currentRoomId,
        token: getToken(),
    });
    if (!res.ok) return alert(res.error);
    localStorage.setItem('spy_game_id', res.game_id);
    localStorage.setItem('spy_room_id', currentRoomId);
    window.location.href = 'game.php';
}

async function refreshRooms() {
    const res = await fetch('api/room_list.php');
    const data = await res.json();
    if (!data.ok) return;
    const container = document.getElementById('rooms');
    const waiting = data.rooms.filter(r => r.status === 'waiting');

    // Check if we're already in a started game
    const myToken = getToken();
    for (const room of data.rooms) {
        if (room.status === 'started' && room.game_id) {
            // Check by fetching game state
        }
    }

    if (waiting.length === 0) {
        container.innerHTML = '<p class="muted">No rooms available. Create one!</p>';
        return;
    }
    container.innerHTML = waiting.map(r => `
        <div class="room-item">
            <span class="room-name">${escHtml(r.name)}</span>
            <span class="room-players">${r.player_count}/4 players</span>
            <button onclick="joinRoom('${r.id}', '${escHtml(r.name)}')">Join</button>
        </div>
    `).join('');
}

function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    showLobby();
    setInterval(refreshRooms, 3000);
});
