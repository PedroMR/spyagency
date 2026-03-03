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

const ROOM_NAME_WORDS = [
    'ace','axe','bay','bee','box','cap','cod','cup','dew','eel','elm','fan',
    'fox','gem','gnu','hat','hen','ivy','jar','jay','jug','koi','lid','log',
    'map','mug','oak','owl','pen','pig','pit','pod','ram','rat','rod','rug',
    'rye','saw','sky','sun','tin','urn','van','yak',
    'arch','bear','bell','bird','boar','bolt','book','boot','bowl','buck',
    'bull','cage','cake','calf','carp','cart','cave','clam','club','coat',
    'colt','crab','crop','crow','cube','dart','dawn','deck','deer','dice',
    'dome','dove','drum','duck','dune','dust','fawn','fish','flag','foam',
    'fork','fort','frog','gate','gnat','goat','gold','gust','halo','harp',
    'hawk','herb','hill','hook','horn','ibis','isle','jade','kite','knot',
    'lace','lake','lamp','land','lark','lava','lawn','leaf','lens','lime',
    'lion','loft','lynx','mace','mast','maze','mesh','mint','mist','mole',
    'moon','moor','moss','moth','mule','nail','nest','newt','node','nook',
    'opal','orca','pace','pack','pane','park','path','peak','pier','pike',
    'pine','plum','pond','pool','port','post','puma','pump','rack','ramp',
    'reef','rice','ring','rock','roof','rope','rose','ruin','rush','rust',
    'sage','sail','salt','sand','seed','shed','ship','silk','slab','slug',
    'snap','snow','soap','soil','song','soot','spar','spot','star','stem',
    'stew','surf','swan','tank','teak','tent','tide','tile','toad','toll',
    'tomb','tome','tram','trap','tray','tube','tusk','vale','vane','vase',
    'veil','vein','vent','vine','void','volt','vole','wake','wall','wand',
    'wasp','wolf','wood','wool','wren','yard','yarn','yoke','zinc','zone',
    'abbey','acorn','amber','anvil','arbor','arena','armor','aroma','attic',
    'azure','badge','basil','basin','bench','berry','birch','blade','blaze',
    'bloom','board','braid','bream','briar','brine','brook','broom','broth',
    'brush','butte','cabin','cairn','canal','cargo','cedar','chalk','charm',
    'chest','chime','chord','cider','cinch','claim','clamp','cleft','cliff',
    'cloak','cloth','cloud','clove','coral','couch','craft','crane','crest',
    'crisp','croak','crook','crowd','crush','curly','curve','cycle','delta',
    'depot','eagle','easel','elbow','elder','emery','fable','feast','fetch',
    'fiber','finch','flair','flake','flame','flask','flint','flock','flood',
    'flora','flute','forge','forum','frail','frond','frost','fungi','gauze',
    'gavel','gecko','ghost','girth','glare','glass','gleam','glide','globe',
    'gloom','glory','gloss','glove','gnome','goose','gourd','grace','grain',
    'grove','guile','guise','gulch','gusto','halve','haven','hazel','heist',
    'heron','holly','horse','horde','humus','husky','hydra','idiom','igloo',
    'image','ingot','inlet','ivory','jaunt','jelly','joust','kayak','knave',
    'knoll','larch','latch','lemon','liner','lodge','lunar','maize','manor',
    'maple','marsh','maxim','mayor','moose','motif','mound','mount','mouse',
    'mural','myrrh','naive','nerve','noble','notch','nymph','oaken','optic',
    'orbit','otter','panda','pasta','patch','peach','perch','phase','pivot',
    'place','plank','plaza','plume','pouch','prawn','prism','probe','prowl',
    'quail','quartz','queen','quest','quota','raven','realm','resin','ridge',
    'rivet','robin','roost','rouge','rowdy','ruddy','rusty','sabre','scone',
    'scorn','scowl','scout','scythe','shard','shark','sheep','shelf','shell',
    'shine','shire','shrub','siege','sigma','skiff','skimp','skunk','slate',
    'sleek','sleet','sloth','snail','snake','snipe','spire','spool','spore',
    'spout','spray','sprig','spunk','squid','stack','stag','stalk','steed',
    'steel','steep','stern','stoat','stone','stork','storm','stout','straw',
    'stray','strip','strut','stump','surge','swamp','swarm','swath','swirl',
    'talon','tapir','thorn','thyme','tiger','torch','totem','trout','truce',
    'truss','tulip','tuner','tunic','tuple','tweed','twill','twine','twist',
    'umbra','uncle','unity','untie','unwed','usher','viper','visor','vixen',
    'vortex','wader','waltz','wafer','waist','walrus','whelk','whirl','whisk',
    'wight','witch','wraith','wreck','wrist','yodel','zebra','zilch','zippy',
    'badger','beacon','beaver','belfry','boggle','bonnet','bonfire','breach',
    'bridle','buster','canary','canopy','castle','cavern','chorus','cipher',
    'citrus','cobalt','cobweb','coffin','coffer','column','condor','copper',
    'cornet','corsair','cougar','coyote','crater','crevice','crimson','cuckoo',
    'dagger','damsel','donkey','dragon','draper','falcon','fallow','famine',
    'fathom','ferret','fissure','fizzle','flagon','fletch','flicker','florin',
    'flurry','fonder','fossil','frenzy','fridge','frolic','fungal','furrow',
    'gambit','garnet','garret','gibbet','gibbon','gopher','goblet','goblin',
    'gravel','grotto','grovel','gunnel','harbor','harper','haystack','hermit',
    'hornet','iguana','jabber','jaguar','jester','juniper','kestrel','lantern',
    'limpet','lizard','magpie','mallet','marmot','martin','merlin','minnow',
    'monkey','mortar','muffin','musket','muster','muzzle','nettle','noodle',
    'nutmeg','obsidian','osprey','parrot','pebble','pellet','pigeon','pillar',
    'pincer','piranha','pistol','plover','potion','puffin','pulsar','python',
    'quiver','rabbit','radish','raptor','rascal','ravine','reaper','riddle',
    'robber','rocket','rodent','rookie','rosary','rudder','runner','saddle',
    'salmon','sandal','satchel','savage','scythe','seraph','serval','shaman',
    'shrike','sienna','siren','skimmer','sliver','smudge','sniper','socket',
    'sorrel','sorrow','spider','splint','sponge','sprout','spurge','squall',
    'stable','staple','stealth','steppe','sticle','stilts','stoker','stucco',
    'sundog','sunken','sunder','sunset','symbol','tallow','tamper','tangle',
    'tartan','tawny','tassel','tattler','thatch','thorax','thrush','tinder',
    'tinker','tipple','toadstool','toggle','toucan','trowel','tundra','tunnel',
    'turban','turtle','tussle','tycoon','urchin','victor','vinery','violet',
    'virago','virtue','visage','walnut','warden','weasel','webbing','wicket',
    'widget','wigeon','wiggle','willow','winkle','winnow','winter','wisdom',
    'wombat','wonder','wortle','wyvern',
];

function randomRoomName() {
    const pick = () => ROOM_NAME_WORDS[Math.floor(Math.random() * ROOM_NAME_WORDS.length)];
    let a, b;
    do { a = pick(); b = pick(); } while (a === b);
    return a + '-' + b;
}

async function createRoom() {
    const input = document.getElementById('room-name');
    let roomName = input.value.trim();
    if (!roomName) {
        roomName = randomRoomName();
        input.value = roomName;
    }
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
