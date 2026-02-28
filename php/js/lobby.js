// Fallback if config.js didn't load
try { API_BASE; } catch(e) { window.API_BASE = ''; }

let _lobbyToastTimer = null;
function showToast(msg) {
    let toast = document.getElementById('ui-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'ui-toast';
        toast.className = 'ui-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.classList.add('visible');
    clearTimeout(_lobbyToastTimer);
    _lobbyToastTimer = setTimeout(() => toast.classList.remove('visible'), 3000);
}

// Catch all unhandled errors and show them visually
window.addEventListener('error', function(e) {
    var el = document.getElementById('lobby-log');
    if (el) {
        var line = document.createElement('div');
        line.style.color = '#ff6666';
        line.textContent = '[UNCAUGHT] ' + e.message + ' at ' + e.filename + ':' + e.lineno;
        el.appendChild(line);
    }
    console.error('[Lobby UNCAUGHT]', e.message, e.filename, e.lineno);
});
window.addEventListener('unhandledrejection', function(e) {
    var el = document.getElementById('lobby-log');
    if (el) {
        var line = document.createElement('div');
        line.style.color = '#ff6666';
        line.textContent = '[UNHANDLED PROMISE] ' + (e.reason && e.reason.message || e.reason || 'unknown');
        el.appendChild(line);
    }
    console.error('[Lobby UNHANDLED PROMISE]', e.reason);
});

// Player token management
function getToken() {
    let token = sessionStorage.getItem('spy_token');
    if (!token) {
        token = Array.from(crypto.getRandomValues(new Uint8Array(16)))
            .map(b => b.toString(16).padStart(2, '0')).join('');
        sessionStorage.setItem('spy_token', token);
    }
    return token;
}

function getPlayerName() {
    return sessionStorage.getItem('spy_name') || '';
}

function saveName() {
    const name = document.getElementById('player-name').value.trim();
    if (!name) return showToast('Enter a name!');
    sessionStorage.setItem('spy_name', name);
    showLobby();
}

function logout() {
    sessionStorage.removeItem('spy_name');
    sessionStorage.removeItem('spy_token');
    sessionStorage.removeItem('spy_game_id');
    sessionStorage.removeItem('spy_room_id');
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
let currentPlayerCount = 1;
let roomPollInterval = null;

function lobbyLog(msg, ...args) {
    const ts = new Date().toLocaleTimeString();
    console.log(`[Lobby ${ts}] ${msg}`, ...args);
    const el = document.getElementById('lobby-log');
    if (el) {
        const line = document.createElement('div');
        line.textContent = `[${ts}] ${msg}` + (args.length ? ' ' + args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' ') : '');
        el.appendChild(line);
        el.scrollTop = el.scrollHeight;
    }
}

function lobbyError(msg, ...args) {
    const ts = new Date().toLocaleTimeString();
    console.error(`[Lobby ${ts}] ${msg}`, ...args);
    const el = document.getElementById('lobby-log');
    if (el) {
        const line = document.createElement('div');
        line.style.color = '#ff6666';
        line.textContent = `[${ts}] ERROR: ${msg}` + (args.length ? ' ' + args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' ') : '');
        el.appendChild(line);
        el.scrollTop = el.scrollHeight;
    }
}

async function apiPost(url, data) {
    lobbyLog(`POST ${url}`, data);
    let res;
    try {
        res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data),
        });
    } catch (e) {
        lobbyError(`Fetch failed for POST ${url}: ${e.message}`);
        return {ok: false, error: `Network error: ${e.message}`};
    }
    lobbyLog(`POST ${url} -> ${res.status} ${res.statusText}`);
    if (!res.ok) {
        const body = await res.text();
        lobbyError(`HTTP ${res.status} from POST ${url}:`, body.substring(0, 500));
        return {ok: false, error: `HTTP ${res.status}: ${body.substring(0, 200)}`};
    }
    let json;
    const text = await res.text();
    try {
        json = JSON.parse(text);
    } catch (e) {
        lobbyError(`Invalid JSON from POST ${url}:`, text.substring(0, 500));
        return {ok: false, error: `Invalid JSON response: ${text.substring(0, 200)}`};
    }
    lobbyLog(`POST ${url} response:`, json);
    return json;
}

async function apiGet(url) {
    if (url.indexOf('?') < 0)
        url += "?";
    url += "&r="+Math.random();
    // lobbyLog(`GET ${url}`);
    let res;
    try {
        res = await fetch(url);
    } catch (e) {
        lobbyError(`Fetch failed for GET ${url}: ${e.message}`);
        return {ok: false, error: `Network error: ${e.message}`};
    }
    // lobbyLog(`GET ${url} -> ${res.status} ${res.statusText}`);
    if (!res.ok) {
        const body = await res.text();
        lobbyError(`HTTP ${res.status} from GET ${url}:`, body.substring(0, 500));
        return {ok: false, error: `HTTP ${res.status}: ${body.substring(0, 200)}`};
    }
    let json;
    const text = await res.text();
    try {
        json = JSON.parse(text);
    } catch (e) {
        lobbyError(`Invalid JSON from GET ${url}:`, text.substring(0, 500));
        return {ok: false, error: `Invalid JSON response: ${text.substring(0, 200)}`};
    }
    return json;
}

async function createRoom() {
    const roomName = document.getElementById('room-name').value.trim();
    if (!roomName) return showToast('Enter a room name');
    if (roomName.length > 30) return showToast('Room name too big (>30 chars)');
    const res = await apiPost(API_BASE + 'api/room_create.php', {
        room_name: roomName,
        player_name: getPlayerName(),
        token: getToken(),
    });
    if (!res.ok) return showToast(res.error);
    document.getElementById('room-name').value = '';
    currentRoomId = res.room_id;
    showWaitingRoom(roomName, true);
}

async function joinRoom(roomId, roomName) {
    const res = await apiPost(API_BASE + 'api/room_join.php', {
        room_id: roomId,
        player_name: getPlayerName(),
        token: getToken(),
    });
    if (!res.ok) return showToast(res.error);
    currentRoomId = roomId;
    showWaitingRoom(roomName, false);
}

function showWaitingRoom(roomName, isHost) {
    document.getElementById('lobby-actions').style.display = 'none';
    document.getElementById('waiting-room').style.display = 'block';
    document.getElementById('waiting-room-name').textContent = roomName;
    document.getElementById('btn-start').style.display = isHost ? 'inline-block' : 'none';
    document.getElementById('btn-add-ai').style.display = isHost ? 'inline-block' : 'none';
    document.getElementById('waiting-msg').style.display = isHost ? 'none' : 'block';
    pollWaitingRoom();
}

async function addAI() {
    const res = await apiPost(API_BASE + 'api/room_add_ai.php', {
        room_id: currentRoomId,
        token: getToken(),
    });
    if (!res.ok) return showToast(res.error);
    lobbyLog('Added AI: ' + res.name);
}

function pollWaitingRoom() {
    if (roomPollInterval) clearInterval(roomPollInterval);
    roomPollInterval = setInterval(async () => {
        const data = await apiGet(API_BASE + 'api/room_list.php');
        if (!data.ok) return;
        const room = data.rooms.find(r => r.id === currentRoomId);
        if (!room) {
            // Room was deleted (everyone left)
            clearInterval(roomPollInterval);
            currentRoomId = null;
            document.getElementById('waiting-room').style.display = 'none';
            document.getElementById('lobby-actions').style.display = 'block';
            refreshRooms();
            return;
        }
        if (room.status === 'started') {
            clearInterval(roomPollInterval);
            sessionStorage.setItem('spy_game_id', room.game_id);
            sessionStorage.setItem('spy_room_id', room.id);
            window.location.href = 'game.php?game_id=' + encodeURIComponent(room.game_id) + '&token=' + encodeURIComponent(getToken());
            return;
        }
        // Update host status (in case host left and we got promoted)
        const isHost = room.host === getToken();
        document.getElementById('btn-start').style.display = isHost ? 'inline-block' : 'none';
        document.getElementById('btn-add-ai').style.display = isHost ? 'inline-block' : 'none';
        document.getElementById('btn-add-ai').disabled = room.player_count >= 4;
        document.getElementById('waiting-msg').style.display = isHost ? 'none' : 'block';
        currentPlayerCount = room.player_count;
        const el = document.getElementById('waiting-players');
        el.innerHTML = '<ul>' + room.players.map(p => `<li>${escHtml(p.name)}${p.is_ai ? ' 🤖' : ''}</li>`).join('') + '</ul>';
    }, 1500);
}

async function leaveRoom() {
    if (!currentRoomId) return;
    await apiPost(API_BASE + 'api/room_leave.php', {
        room_id: currentRoomId,
        token: getToken(),
    });
    if (roomPollInterval) clearInterval(roomPollInterval);
    currentRoomId = null;
    document.getElementById('waiting-room').style.display = 'none';
    document.getElementById('lobby-actions').style.display = 'block';
    refreshRooms();
}

async function startGame() {
    if (currentPlayerCount < 2) {
        const aiRes = await apiPost(API_BASE + 'api/room_add_ai.php', {
            room_id: currentRoomId,
            token: getToken(),
        });
        if (!aiRes.ok) return showToast(aiRes.error);
    }
    const res = await apiPost(API_BASE + 'api/room_start.php', {
        room_id: currentRoomId,
        token: getToken(),
    });
    if (!res.ok) return showToast(res.error);
    sessionStorage.setItem('spy_game_id', res.game_id);
    sessionStorage.setItem('spy_room_id', currentRoomId);
    window.location.href = 'game.php?game_id=' + encodeURIComponent(res.game_id) + '&token=' + encodeURIComponent(getToken());
}

async function refreshRooms() {
    const data = await apiGet(API_BASE + 'api/room_list.php');
    if (!data.ok) {
        const container = document.getElementById('rooms');
        container.innerHTML = `<p class="muted" style="color:#ff6666">Failed to load rooms: ${escHtml(data.error)}</p>`;
        return;
    }
    const container = document.getElementById('rooms');
    const waiting = data.rooms.filter(r => r.status === 'waiting');

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
    lobbyLog('Lobby init, API_BASE=' + JSON.stringify(API_BASE));
    showLobby();
    setInterval(refreshRooms, 3000);
});
