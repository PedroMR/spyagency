const Actions = {
    gameId: null,
    token: null,

    init(gameId, token) {
        this.gameId = gameId;
        this.token = token;
    },

    async send(action, params = {}) {
        const res = await fetch(API_BASE + 'api/game_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                game_id: this.gameId,
                token: this.token,
                action: action,
                params: params,
            }),
        });
        const data = await res.json();
        if (!data.ok) {
            UI.showError(data.error || 'Action failed');
        } else {
            Poller.pollNow();
        }
        return data;
    },

    playMoney(cardId) {
        return this.send('play_money', {card_id: cardId});
    },

    playAgent(cardId, baseIndex) {
        return this.send('play_agent', {card_id: cardId, base_index: baseIndex});
    },

    equipTech(cardId, baseIndex) {
        return this.send('equip_tech', {card_id: cardId, base_index: baseIndex});
    },

    playPlot(cardId, extraParams = {}) {
        return this.send('play_plot', Object.assign({card_id: cardId}, extraParams));
    },

    buyCard(cardId, slot) {
        return this.send('buy_card', {card_id: cardId, slot: slot});
    },

    buyAlwaysAvailable(cardId) {
        return this.send('buy_card', {card_id: cardId});
    },

    buyBase() {
        return this.send('buy_base', {});
    },

    refreshMarket() {
        return this.send('refresh_market', {});
    },

    completeMission(missionId, baseIndex, iconChoices) {
        return this.send('complete_mission', {
            mission_id: missionId,
            base_index: baseIndex,
            icon_choices: iconChoices || {},
        });
    },

    vacateBase(baseIndex) {
        return this.send('vacate_base', {base_index: baseIndex});
    },

    endTurn() {
        return this.send('end_turn', {});
    },
};
