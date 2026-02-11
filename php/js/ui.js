const UI = {
    state: null,
    catalog: null,

    iconMap: {
        drive: '🚘',
        muscle: '💪',
        disguise: '🥸',
        key: '🔑',
    },

    typeColors: {
        money: '#2d5a1e',
        agent: '#1a3a5c',
        tech: '#5c3a1a',
        plot: '#4a1a4a',
        mission: '#8b0000',
    },

    update(state) {
        this.state = state;
        this.catalog = state.catalog;
        this.renderTurnInfo();
        this.renderMarketplace();
        this.renderMissionGrid();
        this.renderFundraising();
        this.renderOpponents();
        this.renderHand();
        this.renderPlayArea();
        this.renderDiscardPile();
        this.renderLog();
        this.renderGameOver();
    },

    renderTurnInfo() {
        const s = this.state;
        const me = s.players[s.my_index];
        const currentName = s.players[s.current_player].name;
        const turnEl = document.getElementById('turn-indicator');
        turnEl.textContent = s.is_my_turn ? "Your Turn!" : `${currentName}'s turn`;
        turnEl.className = s.is_my_turn ? 'your-turn' : 'other-turn';

        document.getElementById('money-display').textContent = `$${me.money}`;
        document.getElementById('gems-display').textContent = `💎 ${me.gems}`;
        document.getElementById('gems-display').style.display = (me.gems > 0 || s.is_my_turn) ? 'inline' : 'none';
        document.getElementById('stars-display').textContent = `⭐ ${me.stars}`;
        document.getElementById('btn-end-turn').style.display = s.is_my_turn ? 'inline-block' : 'none';
        document.getElementById('btn-cash-gems').style.display = (s.is_my_turn && me.gems > 0) ? 'inline-block' : 'none';

        const banner = document.getElementById('final-round-banner');
        banner.style.display = s.final_round ? 'block' : 'none';
    },

    renderMarketplace() {
        const s = this.state;
        const me = s.players[s.my_index];
        const container = document.getElementById('marketplace');
        document.getElementById('market-deck-count').textContent = `(${s.market_deck_count} left)`;

        container.innerHTML = s.marketplace.map((cardId, i) => {
            if (!cardId) return '<div class="card card-empty">Empty</div>';
            const card = this.catalog[cardId];
            const affordable = s.is_my_turn && me.money >= card.cost && (me.buys_this_turn || 0) < 1;
            const affordClass = affordable ? ' affordable' : '';
            return `<div class="card market-card${affordClass}" style="border-color:${this.typeColors[card.type]}" onclick="UI.onMarketClick('${cardId}', ${i})">
                <div class="card-name">${esc(card.name)}</div>
                <div class="card-cost">$${card.cost}</div>
                <div class="card-type">${card.type}</div>
                <div class="card-desc">${esc(card.description)}</div>
            </div>`;
        }).join('');
    },

    renderMissionGrid() {
        const container = document.getElementById('mission-grid');
        let html = '';
        for (const tier of [1, 2, 3]) {
            const deckCount = this.state.mission_deck_counts[tier];
            html += `<div class="mission-tier"><h4>Tier ${tier} <span class="deck-count">(${deckCount} left)</span></h4><div class="mission-tier-cards">`;
            const missions = this.state.mission_grid[tier] || [];
            for (const mId of missions) {
                const card = this.catalog[mId];
                const reqIcons = (card.requirements || []).map(r => r === 'any' ? '❓' : (this.iconMap[r] || r)).join('');
                const stars = card.stars ? `${card.stars}⭐ ` : '';
                const money = card.value ? `$${card.value}` : '';
                html += `<div class="card mission-card" onclick="UI.showMissionDialog('${mId}')">
                    <div class="card-name">${esc(card.name)}</div>
                    <div class="card-req">${reqIcons}</div>
                    <div class="card-reward">${stars}${money}</div>
                </div>`;
            }
            html += '</div></div>';
        }
        container.innerHTML = html;
    },

    renderFundraising() {
        const card = this.catalog['fundraising'];
        const reqIcons = (card.requirements || []).map(r => r === 'any' ? '❓' : (this.iconMap[r] || r)).join('');
        const el = document.getElementById('fundraising-mission');
        el.innerHTML = `<div class="card-name">${esc(card.name)}</div>
            <div class="card-req">${reqIcons}</div>
            <div class="card-reward">💎 1-3</div>
            <div class="card-desc">Always available</div>`;
    },

    renderOpponents() {
        const container = document.getElementById('opponents');
        let html = '';
        for (let i = 0; i < this.state.players.length; i++) {
            if (i === this.state.my_index) continue;
            const p = this.state.players[i];
            const isActive = i === this.state.current_player;
            html += `<div class="opponent ${isActive ? 'active-player' : ''}">
                <h4>${esc(p.name)} ${isActive ? '(playing)' : ''}</h4>
                <div class="opponent-stats">
                    <span>Hand: ${p.hand_count}</span>
                    <span>Deck: ${p.deck_count}</span>
                    <span>$${p.money}</span>
                    ${p.gems > 0 ? `<span>💎${p.gems}</span>` : ''}
                    <span>⭐${p.stars}</span>
                </div>
            </div>`;
        }
        container.innerHTML = html;
    },

    getCardIcons(card) {
        if (!card.icons) return '';
        return card.icons.map(icon => {
            if (Array.isArray(icon)) {
                return icon.map(i => this.iconMap[i] || i).join('/');
            }
            return this.iconMap[icon] || icon;
        }).join(' ');
    },

    renderHand() {
        const me = this.state.players[this.state.my_index];
        const container = document.getElementById('my-hand');
        if (!this.state.is_my_turn) {
            container.innerHTML = me.hand.map(cardId => {
                const card = this.catalog[cardId];
                return `<div class="card hand-card" style="border-color:${this.typeColors[card.type]}">
                    <div class="card-name">${esc(card.name)}</div>
                    <div class="card-type">${card.type}</div>
                    <div class="card-desc">${esc(card.description)}</div>
                </div>`;
            }).join('');
            return;
        }
        container.innerHTML = me.hand.map(cardId => {
            const card = this.catalog[cardId];
            return `<div class="card hand-card playable" style="border-color:${this.typeColors[card.type]}" onclick="UI.onHandClick('${cardId}')">
                <div class="card-name">${esc(card.name)}</div>
                <div class="card-type">${card.type}</div>
                <div class="card-desc">${esc(card.description)}</div>
            </div>`;
        }).join('');
    },

    renderPlayArea() {
        const me = this.state.players[this.state.my_index];
        const container = document.getElementById('play-area');
        container.innerHTML = me.play_area.map(cardId => {
            const card = this.catalog[cardId];
            return `<div class="card played-card" style="border-color:${this.typeColors[card.type]}">
                <div class="card-name">${esc(card.name)}</div>
            </div>`;
        }).join('');
    },

    renderDiscardPile() {
        const me = this.state.players[this.state.my_index];
        const discard = me.discard || [];
        const countEl = document.getElementById('discard-count');
        const container = document.getElementById('discard-pile');
        countEl.textContent = `(${discard.length})`;

        if (discard.length === 0) {
            container.innerHTML = '<span class="muted">Empty</span>';
            return;
        }
        // Show top card
        const topId = discard[discard.length - 1];
        const card = this.catalog[topId];
        container.innerHTML = `<div class="card played-card" style="border-color:${this.typeColors[card.type]}">
            <div class="card-name">${esc(card.name)}</div>
            <div class="card-type">${card.type}</div>
        </div>`;
    },

    renderLog() {
        const container = document.getElementById('game-log');
        container.innerHTML = this.state.log.map(l => `<div class="log-entry">${esc(l)}</div>`).join('');
        container.scrollTop = container.scrollHeight;
    },

    renderGameOver() {
        if (!this.state.ended) return;
        const scores = this.state.scores;
        if (!scores) return;
        let html = '<h2>Game Over!</h2><div class="scores">';
        scores.forEach((s, i) => {
            html += `<div class="score-row ${i === 0 ? 'winner' : ''}">
                <span>${i + 1}. ${esc(s.name)}</span>
                <span>${s.stars} ⭐</span>
            </div>`;
        });
        html += '</div><button onclick="location.href=\'index.php\'">Back to Lobby</button>';
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-overlay').style.display = 'flex';
    },

    onHandClick(cardId) {
        if (!this.state.is_my_turn) return;
        const card = this.catalog[cardId];

        switch (card.type) {
            case 'money':
                Actions.playMoney(cardId);
                break;
            case 'mission':
                if (card.value > 0) Actions.playMoney(cardId);
                break;
            case 'agent':
            case 'tech':
                // Agents and tech are played as part of missions, not individually
                this.showError('Agents and tech are played when completing missions.');
                break;
            case 'plot':
                this.handlePlotPlay(cardId);
                break;
        }
    },

    onMarketClick(cardId, slot) {
        if (!this.state.is_my_turn) return;
        const me = this.state.players[this.state.my_index];
        if ((me.buys_this_turn || 0) >= 1) {
            this.showError('Already bought a card this turn.');
            return;
        }
        if (confirm(`Buy ${this.catalog[cardId].name} for $${this.catalog[cardId].cost}?`)) {
            Actions.buyCard(cardId, slot);
        }
    },

    handlePlotPlay(cardId) {
        const card = this.catalog[cardId];
        const effect = card.effect;

        if (effect === 'none' || effect === 'draw2' || effect === 'paperwork' || effect === 'multitask') {
            Actions.playPlot(cardId);
            return;
        }

        if (effect === 'trash') {
            this.showTrashDialog(cardId);
            return;
        }

        if (effect === 'backup') {
            this.showBackupDialog(cardId);
            return;
        }

        if (effect === 'training') {
            this.showTrainingDialog(cardId);
            return;
        }

        Actions.playPlot(cardId);
    },

    showTrashDialog(plotCardId) {
        const me = this.state.players[this.state.my_index];
        let html = '<h3>Burn Notice: Trash a card</h3>';
        const areas = [
            {key: 'hand', label: 'Hand', cards: me.hand},
            {key: 'play_area', label: 'Play Area', cards: me.play_area},
            {key: 'discard', label: 'Discard', cards: me.discard || []},
        ];
        for (const area of areas) {
            if (area.cards.length === 0) continue;
            html += `<h4>${area.label}</h4>`;
            const uniqueCards = [...new Set(area.cards)];
            for (const cid of uniqueCards) {
                if (cid === plotCardId && area.key === 'hand') continue;
                const c = this.catalog[cid];
                html += `<button class="btn-modal" onclick="Actions.playPlot('${plotCardId}', {target_card:'${cid}', target_area:'${area.key}'}); UI.closeModal()">
                    ${esc(c.name)}
                </button>`;
            }
        }
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showBackupDialog(plotCardId) {
        const me = this.state.players[this.state.my_index];
        const agents = me.hand.filter(cid => cid !== plotCardId && this.catalog[cid].type === 'agent');
        if (agents.length === 0) {
            this.showError('No agents in hand to use as backup!');
            return;
        }
        let html = '<h3>Got Your Back! Select backup agent:</h3>';
        const unique = [...new Set(agents)];
        unique.forEach(cid => {
            const c = this.catalog[cid];
            html += `<button class="btn-modal" onclick="Actions.playPlot('${plotCardId}', {agent_card_id:'${cid}'}); UI.closeModal()">
                ${esc(c.name)} (${this.getCardIcons(c)})
            </button>`;
        });
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showTrainingDialog(plotCardId) {
        const me = this.state.players[this.state.my_index];
        const agents = me.hand.filter(cid => cid !== plotCardId && this.catalog[cid].type === 'agent');
        if (agents.length === 0) {
            this.showError('No agents in hand to trash!');
            return;
        }
        let html = '<h3>Training Procedure: Select agent to trash</h3>';
        const unique = [...new Set(agents)];
        unique.forEach(cid => {
            const c = this.catalog[cid];
            const maxCost = (c.cost || 0) + 3;
            html += `<button class="btn-modal" onclick="UI.showTrainingGainDialog('${plotCardId}', '${cid}', ${maxCost})">
                Trash ${esc(c.name)} ($${c.cost}) — gain up to $${maxCost}
            </button>`;
        });
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showTrainingGainDialog(plotCardId, trashAgent, maxCost) {
        const catalog = this.catalog;
        let html = `<h3>Training: Gain an agent (up to $${maxCost})</h3>`;

        // Always-available agents
        const alwaysAvail = Object.values(catalog).filter(c => c.type === 'agent' && c.always_available && c.cost <= maxCost);
        for (const c of alwaysAvail) {
            html += `<button class="btn-modal" onclick="Actions.playPlot('${plotCardId}', {trash_agent:'${trashAgent}', gain_agent:'${c.id}', gain_slot:-1}); UI.closeModal()">
                ${esc(c.name)} ($${c.cost}) — Always available
            </button>`;
        }

        // Marketplace agents
        this.state.marketplace.forEach((cardId, slot) => {
            if (!cardId) return;
            const c = catalog[cardId];
            if (c.type === 'agent' && c.cost <= maxCost) {
                html += `<button class="btn-modal" onclick="Actions.playPlot('${plotCardId}', {trash_agent:'${trashAgent}', gain_agent:'${cardId}', gain_slot:${slot}}); UI.closeModal()">
                    ${esc(c.name)} ($${c.cost}) — Marketplace
                </button>`;
            }
        });

        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showMissionDialog(missionId) {
        if (!this.state.is_my_turn) return;
        const mission = this.catalog[missionId];
        const me = this.state.players[this.state.my_index];

        // Find agents and tech in hand
        const handAgents = me.hand.filter(cid => this.catalog[cid].type === 'agent');
        const handTech = me.hand.filter(cid => this.catalog[cid].type === 'tech');

        if (handAgents.length === 0) {
            this.showError('No agents in hand to attempt missions!');
            return;
        }

        const reqIcons = (mission.requirements || []).map(r => r === 'any' ? '❓' : (this.iconMap[r] || r)).join('');
        const isFundraising = (mission.gems || 0) > 0;

        let html = `<h3>Complete: ${esc(mission.name)}</h3>`;
        html += `<p>Requires: ${reqIcons}</p>`;
        if (isFundraising) {
            html += '<p>Reward: 💎 1-3 gems (based on icons committed)</p>';
        }
        html += '<p>Select agents and tech from your hand:</p>';

        // Checkboxes for agents
        html += '<h4>Agents</h4>';
        const uniqueAgents = [...new Set(handAgents)];
        uniqueAgents.forEach(cid => {
            const c = this.catalog[cid];
            const count = handAgents.filter(x => x === cid).length;
            const icons = this.getCardIcons(c);
            for (let i = 0; i < count; i++) {
                html += `<label class="mission-select-item">
                    <input type="checkbox" name="mission-agent" value="${cid}" data-max-tech="${c.max_tech || 0}">
                    ${esc(c.name)} (${icons}) [tech slots: ${c.max_tech || 0}]
                </label>`;
            }
        });

        // Checkboxes for tech
        if (handTech.length > 0) {
            html += '<h4>Tech</h4>';
            const uniqueTech = [...new Set(handTech)];
            uniqueTech.forEach(cid => {
                const c = this.catalog[cid];
                const count = handTech.filter(x => x === cid).length;
                const icons = this.getCardIcons(c);
                for (let i = 0; i < count; i++) {
                    html += `<label class="mission-select-item">
                        <input type="checkbox" name="mission-tech" value="${cid}">
                        ${esc(c.name)} (${icons})
                    </label>`;
                }
            });
        }

        html += `<button class="btn-modal" style="margin-top:12px;background:#1a8a2d;border-color:#2dbc45" onclick="UI.submitMission('${missionId}')">
            Attempt Mission
        </button>`;
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    submitMission(missionId) {
        const agentBoxes = document.querySelectorAll('input[name="mission-agent"]:checked');
        const techBoxes = document.querySelectorAll('input[name="mission-tech"]:checked');
        const agentIds = Array.from(agentBoxes).map(cb => cb.value);
        const techIds = Array.from(techBoxes).map(cb => cb.value);

        if (agentIds.length === 0) {
            this.showError('Select at least one agent.');
            return;
        }

        // Validate tech count against agent max_tech
        let totalMaxTech = 0;
        agentIds.forEach(aid => {
            totalMaxTech += (this.catalog[aid].max_tech || 0);
        });
        // Add backup agent tech slots if present
        const me = this.state.players[this.state.my_index];
        if (me.backup_agent) {
            totalMaxTech += (this.catalog[me.backup_agent].max_tech || 0);
        }
        if (techIds.length > totalMaxTech) {
            this.showError(`Too many tech cards (max ${totalMaxTech} for selected agents).`);
            return;
        }

        Actions.completeMission(missionId, agentIds, techIds);
        this.closeModal();
    },

    showCashGemsDialog() {
        const me = this.state.players[this.state.my_index];
        const gems = me.gems || 0;
        if (gems <= 0) {
            this.showError('No gems to cash!');
            return;
        }
        let html = `<h3>Cash Gems (💎 ${gems} available)</h3>`;
        html += '<p>Convert gems to money at 1:1 rate:</p>';
        for (let i = 1; i <= gems; i++) {
            html += `<button class="btn-modal" onclick="Actions.cashGems(${i}); UI.closeModal()">
                Cash ${i} gem${i > 1 ? 's' : ''} for $${i}
            </button>`;
        }
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showModal(html) {
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-overlay').style.display = 'flex';
    },

    closeModal() {
        document.getElementById('modal-overlay').style.display = 'none';
    },

    showError(msg) {
        alert(msg);
    },
};

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
