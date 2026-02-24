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
            headers: {'Content-Type': 'application/json', 'Cache-Control': 'no-cache'},
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

    playPlot(cardId, extraParams = {}) {
        return this.send('play_plot', Object.assign({card_id: cardId}, extraParams));
    },

    buyCard(cardId, slot) {
        return this.send('buy_card', {card_id: cardId, slot: slot});
    },

    buyAlwaysAvailable(cardId) {
        return this.send('buy_card', {card_id: cardId});
    },

    refreshMarket() {
        return this.send('refresh_market', {});
    },

    buyGem() {
        return this.send('buy_gem', {});
    },

    cashGems(amount) {
        return this.send('cash_gems', {amount: amount});
    },

    completeMission(missionId, agentIds, techIds, useBaseAgent) {
        return this.send('complete_mission', {
            mission_id: missionId,
            agent_ids: agentIds || [],
            tech_ids: techIds || [],
            use_base_agent: useBaseAgent || false,
        });
    },

    putAgentInBase(agentId, techIds) {
        return this.send('put_agent_in_base', {agent_id: agentId, tech_ids: techIds || []});
    },

    addTechToBase(techIds) {
        return this.send('add_tech_to_base', {tech_ids: techIds || []});
    },

    discardBaseAgent() {
        return this.send('discard_base_agent', {});
    },

    endTurn() {
        return this.send('end_turn', {});
    },

    voteRematch(vote) {
        return this.send('vote_rematch', { vote: vote });
    },

    debugEndGame() {
        return this.send('debug_end_game', {});
    },

    resign() {
        return this.send('resign', {});
    },

    leaveRoom(roomId) {
        return fetch(API_BASE + 'api/room_leave.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ room_id: roomId, token: this.token }),
        }).catch(() => {});
    },

    defendAttack(choice, cardId, discardCards, discardFrom) {
        const params = {choice: choice, card_id: cardId || ''};
        if (discardCards) params.discard_cards = discardCards;
        if (discardFrom) params.discard_from = discardFrom;
        return this.send('defend_attack', params);
    }
};
