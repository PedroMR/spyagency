const Poller = {
    gameId: null,
    token: null,
    version: 0,
    interval: null,
    onUpdate: null,
    aiTurnInProgress: false,

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
            if (data.needs_ai_action) {
                setTimeout(() => this.triggerAiTurn(), 900);
            }
        } catch (e) {
            console.error('Poll exception:', e);
        }
    },

    async triggerAiTurn() {
        if (this.aiTurnInProgress) return;
        this.aiTurnInProgress = true;
        try {
            await fetch(API_BASE + 'api/game_ai_turn.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ game_id: this.gameId }),
            });
            this.pollNow();
        } catch (e) {
            console.error('AI turn error:', e);
        } finally {
            this.aiTurnInProgress = false;
        }
    },
};
