const Poller = {
    gameId: null,
    token: null,
    version: 0,
    interval: null,
    onUpdate: null,

    init(gameId, token, onUpdate) {
        this.gameId = gameId;
        this.token = token;
        this.onUpdate = onUpdate;
        this.version = 0;
        this.start();
    },

    start() {
        this.poll();
        this.interval = setInterval(() => this.poll(), 1500);
    },

    stop() {
        if (this.interval) clearInterval(this.interval);
    },

    pollNow() {
        this.poll();
    },

    async poll() {
        try {
            const url = API_BASE + `api/game_state.php?game_id=${this.gameId}&token=${this.token}&version=${this.version}&_t=${Date.now()}`;
            const res = await fetch(url);
            const data = await res.json();
            if (!data.ok) {
                console.error('Poll error:', data.error);
                return;
            }
            if (!data.changed) return;
            this.version = data.version;
            if (this.onUpdate) this.onUpdate(data);
        } catch (e) {
            console.error('Poll exception:', e);
        }
    },
};
