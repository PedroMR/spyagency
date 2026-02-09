const UI = {
    state: null,
    catalog: null,
    selectedCard: null,
    pendingMission: null,

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
        base: '#333',
    },

    update(state) {
        this.state = state;
        this.catalog = state.catalog;
        this.renderTurnInfo();
        this.renderMarketplace();
        this.renderMissionGrid();
        this.renderFundraising();
        this.renderOpponents();
        this.renderBases();
        this.renderHand();
        this.renderPlayArea();
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
        document.getElementById('stars-display').textContent = `⭐ ${me.stars}`;
        document.getElementById('btn-end-turn').style.display = s.is_my_turn ? 'inline-block' : 'none';

        const banner = document.getElementById('final-round-banner');
        banner.style.display = s.final_round ? 'block' : 'none';

        // Base cost: $5, $8, $12, ...
        const extra = me.extra_base_count;
        const baseCosts = [5, 8, 12];
        const baseCost = extra < baseCosts.length ? baseCosts[extra] : baseCosts[baseCosts.length-1] + (extra - baseCosts.length + 1) * 4;
        document.getElementById('base-cost').textContent = `$${baseCost}`;
    },

    renderMarketplace() {
        const s = this.state;
        const container = document.getElementById('marketplace');
        document.getElementById('market-deck-count').textContent = `(${s.market_deck_count} left)`;

        container.innerHTML = s.marketplace.map((cardId, i) => {
            if (!cardId) return '<div class="card card-empty">Empty</div>';
            const card = this.catalog[cardId];
            return `<div class="card market-card" style="border-color:${this.typeColors[card.type]}" onclick="UI.onMarketClick('${cardId}', ${i})">
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
        const reward = card.value > 0 ? `$${card.value}` : '';
        const el = document.getElementById('fundraising-mission');
        el.innerHTML = `<div class="card-name">${esc(card.name)}</div>
            <div class="card-req">${reqIcons}</div>
            <div class="card-reward">${reward}</div>
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
                    <span>⭐${p.stars}</span>
                </div>
                <div class="opponent-bases">${this.renderBasesFor(p, false)}</div>
            </div>`;
        }
        container.innerHTML = html;
    },

    renderBasesFor(player, isMe) {
        let html = '';
        player.bases.forEach((base, bi) => {
            const card = this.catalog[base.type];
            const agentCard = base.agent ? this.catalog[base.agent] : null;
            const techHtml = base.tech.map(t => {
                const tc = this.catalog[t];
                return `<span class="tech-badge" title="${esc(tc.description)}">${esc(tc.name)}</span>`;
            }).join('');

            let agentHtml = '';
            if (agentCard) {
                agentHtml = `<div class="base-agent">
                    <span class="agent-name">${esc(agentCard.name)}</span>
                    <span class="agent-icons">${this.getCardIcons(agentCard)}</span>
                    ${techHtml ? `<div class="base-tech">${techHtml}</div>` : ''}
                </div>`;
            } else {
                agentHtml = '<div class="base-empty">Empty</div>';
            }

            const actions = isMe ? `<div class="base-actions">
                ${base.agent ? `<button class="btn-sm" onclick="Actions.vacateBase(${bi})">Vacate</button>` : ''}
            </div>` : '';

            html += `<div class="base-slot" data-base="${bi}">
                <div class="base-header">${esc(card.name)} #${bi + 1}</div>
                ${agentHtml}
                ${actions}
            </div>`;
        });
        return html;
    },

    renderBases() {
        const me = this.state.players[this.state.my_index];
        document.getElementById('my-bases').innerHTML = this.renderBasesFor(me, true);
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
                // Completed mission cards in hand can be played for money
                if (card.value > 0) Actions.playMoney(cardId);
                break;
            case 'agent':
                this.showBasePickerForAgent(cardId);
                break;
            case 'tech':
                this.showBasePickerForTech(cardId);
                break;
            case 'plot':
                this.handlePlotPlay(cardId);
                break;
        }
    },

    onMarketClick(cardId, slot) {
        if (!this.state.is_my_turn) return;
        if (confirm(`Buy ${this.catalog[cardId].name} for $${this.catalog[cardId].cost}?`)) {
            Actions.buyCard(cardId, slot);
        }
    },

    showBasePickerForAgent(cardId) {
        const me = this.state.players[this.state.my_index];
        const emptyBases = me.bases.map((b, i) => ({...b, idx: i})).filter(b => !b.agent);
        if (emptyBases.length === 0) {
            this.showError('No empty bases! Vacate one first.');
            return;
        }
        if (emptyBases.length === 1) {
            Actions.playAgent(cardId, emptyBases[0].idx);
            return;
        }
        let html = `<h3>Deploy ${esc(this.catalog[cardId].name)} to which base?</h3>`;
        emptyBases.forEach(b => {
            html += `<button class="btn-modal" onclick="Actions.playAgent('${cardId}', ${b.idx}); UI.closeModal()">
                ${esc(this.catalog[b.type].name)} #${b.idx + 1}
            </button>`;
        });
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showBasePickerForTech(cardId) {
        const me = this.state.players[this.state.my_index];
        const eligibleBases = me.bases.map((b, i) => ({...b, idx: i})).filter(b => {
            if (!b.agent) return false;
            const agentCard = this.catalog[b.agent];
            return b.tech.length < (agentCard.max_tech || 0);
        });
        if (eligibleBases.length === 0) {
            this.showError('No agents with free tech slots!');
            return;
        }
        if (eligibleBases.length === 1) {
            Actions.equipTech(cardId, eligibleBases[0].idx);
            return;
        }
        let html = `<h3>Equip ${esc(this.catalog[cardId].name)} to which agent?</h3>`;
        eligibleBases.forEach(b => {
            const agent = this.catalog[b.agent];
            html += `<button class="btn-modal" onclick="Actions.equipTech('${cardId}', ${b.idx}); UI.closeModal()">
                ${esc(agent.name)} at ${esc(this.catalog[b.type].name)} #${b.idx + 1}
            </button>`;
        });
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
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

    showMissionDialog(missionId) {
        if (!this.state.is_my_turn) return;
        const mission = this.catalog[missionId];
        const me = this.state.players[this.state.my_index];
        const occupied = me.bases.map((b, i) => ({...b, idx: i})).filter(b => b.agent);

        if (occupied.length === 0) {
            this.showError('No agents deployed to attempt missions!');
            return;
        }

        const reqIcons = (mission.requirements || []).map(r => r === 'any' ? '❓' : (this.iconMap[r] || r)).join('');
        let html = `<h3>Complete: ${esc(mission.name)}</h3><p>Requires: ${reqIcons}</p>`;
        html += '<p>Select a base (agent will be discarded):</p>';

        occupied.forEach(b => {
            const agent = this.catalog[b.agent];
            const techNames = b.tech.map(t => this.catalog[t].name).join(', ');
            const iconsDisplay = this.getCardIcons(agent);
            const techIcons = b.tech.map(t => this.getCardIcons(this.catalog[t])).join(' ');
            html += `<button class="btn-modal" onclick="UI.attemptMission('${missionId}', ${b.idx})">
                ${esc(agent.name)} (${iconsDisplay}${techIcons ? ' + ' + techIcons : ''}) at Base #${b.idx + 1}
            </button>`;
        });
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    attemptMission(missionId, baseIdx) {
        // Server auto-resolves icon choices
        Actions.completeMission(missionId, baseIdx, {});
        this.closeModal();
    },

    showModal(html) {
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-overlay').style.display = 'flex';
    },

    closeModal() {
        document.getElementById('modal-overlay').style.display = 'none';
        this.pendingMission = null;
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
