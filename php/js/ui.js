// Discreet click sound using Web Audio API
function playClick() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.value = 1200;
        gain.gain.value = 0.32;
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.06);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.06);
    } catch (e) {}
}

// Soft bell sound for turn notification
function playBell() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const t = ctx.currentTime;
        // Two harmonics for a bell-like tone
        [880, 1320].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(i === 0 ? 0.15 : 0.07, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.6);
            osc.start(t);
            osc.stop(t + 0.6);
        });
    } catch (e) {}
}

// Attach click sound to all buttons
document.addEventListener('click', function(e) {
    if (e.target.closest('button, .card, .btn-modal')) {
        playClick();
    }
});

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
        agent: '#b8a000',
        tech: '#1a4a8c',
        plot: '#0a8a8a',
        mission: '#6a1b9a',
        hazard: '#8b0000',
    },

    iconSortOrder: { key: 0, drive: 1, disguise: 2, muscle: 3 },

    formatReqIcons(icons_raw) {
        if (!icons_raw || icons_raw.length === 0) return '';
        // Sort by defined order, keeping raw keys for sorting
        const mapped = icons_raw.map(r => {
            const sortKey = Array.isArray(r) ? (this.iconSortOrder[r[0]] ?? 99) : (r === 'any' ? 100 : (this.iconSortOrder[r] ?? 99));
            const display = Array.isArray(r) ? r.map(i => this.iconMap[i] || i).join('/') : (r === 'any' ? '❓' : (this.iconMap[r] || r));
            return { sortKey, display };
        });
        mapped.sort((a, b) => a.sortKey - b.sortKey);
        const icons = mapped.map(m => m.display);
        // Group consecutive same icons
        const groups = [];
        let cur = { icon: icons[0], count: 1 };
        for (let i = 1; i < icons.length; i++) {
            if (icons[i] === cur.icon) {
                cur.count++;
            } else {
                groups.push(cur);
                cur = { icon: icons[i], count: 1 };
            }
        }
        groups.push(cur);
        return groups.map(g => {
            if (g.count === 1) return g.icon;
            return `<span class="icon-group">${g.icon.repeat(g.count)}</span>`;
        }).join(' ');
    },

    getCardColor(card) {
        return this.typeColors[card.type] || '#555';
    },

    _autoPlayTimer: null,
    _autoPlaying: false,
    _lastTurnNumber: null,

    update(state) {
        this.state = state;
        this.catalog = state.catalog;
        this.renderTurnInfo();
        this.renderMarketplace();
        this.renderMissionGrid();
        this.renderHeist();
        this.renderOpponents();
        this.renderHand();
        this.renderPlayArea();
        this.renderDeck();
        this.renderDiscardPile();
        this.renderLog();
        this.renderGameOver();

        // Auto-play money and money-only mission cards with 1s delay
        if (state.is_my_turn && !this._autoPlaying && this._lastTurnNumber !== state.turn_number) {
            this._lastTurnNumber = state.turn_number;
            playBell();
            this._scheduleAutoPlay();
        }
    },

    _getAutoPlayCards() {
        if (!this.state || !this.state.is_my_turn) return [];
        const me = this.state.players[this.state.my_index];
        const cards = [];
        for (const cid of me.hand) {
            const card = this.catalog[cid];
            if (!card) continue;
            if (card.type === 'money') {
                cards.push(cid);
            } else if (card.type === 'mission' && (card.value || 0) > 0 && (card.gems || 0) === 0) {
                cards.push(cid);
            }
        }
        return cards;
    },

    _scheduleAutoPlay() {
        if (this._autoPlayTimer) clearTimeout(this._autoPlayTimer);
        const cards = this._getAutoPlayCards();
        if (cards.length === 0) return;
        this._autoPlayTimer = setTimeout(() => this._doAutoPlay(cards), 800);
    },

    async _doAutoPlay(cards) {
        this._autoPlaying = true;
        for (const cid of cards) {
            if (!this.state.is_my_turn) break;
            await Actions.playMoney(cid);
        }
        this._autoPlaying = false;
    },

    _shouldEndTurn() {
        if (!this.state.is_my_turn || this._autoPlaying) return false;
        const me = this.state.players[this.state.my_index];
        const s = this.state;

        // Any playable cards in hand? (money, plot, agent — not hazards)
        const hasPlayable = me.hand.some(cid => {
            const c = this.catalog[cid];
            if (!c) return false;
            if (c.type === 'hazard') return false;
            if (c.type === 'money' || c.type === 'mission') return true;
            if (c.type === 'plot') return true;
            return false;
        });
        if (hasPlayable) return false;

        // Can complete any mission? (have agents in hand)
        const agents = me.hand.filter(cid => this.catalog[cid] && this.catalog[cid].type === 'agent');
        const missionsAllowed = 1 + (me.extra_missions || 0);
        const missionsDone = me.missions_this_turn || 0;
        if (agents.length > 0 && missionsDone < missionsAllowed) return false;

        // Can buy anything? (have buys left and can afford something)
        const buyLimit = 1 + (me.extra_buys || 0);
        const buysLeft = buyLimit - (me.buys_this_turn || 0);
        if (buysLeft > 0) {
            const totalFunds = me.money + (me.gems || 0);
            const canBuyMarket = s.marketplace.some(cid => {
                if (!cid) return false;
                return (this.catalog[cid].cost || 0) <= totalFunds;
            });
            const canBuyAlways = Object.values(this.catalog).some(c =>
                c.always_available && c.type === 'agent' && (c.cost || 0) <= totalFunds
            );
            if (canBuyMarket || canBuyAlways) return false;
        }

        // Can buy gems? (3 money, no gems)
        if (me.money >= 3)
            return false;

        return true;
    },

    renderTurnInfo() {
        const s = this.state;
        const me = s.players[s.my_index];
        const currentName = s.players[s.current_player].name;
        const turnEl = document.getElementById('turn-indicator');
        turnEl.textContent = s.is_my_turn ? "Your Turn!" : `${currentName}'s turn`;
        turnEl.className = s.is_my_turn ? 'your-turn' : 'other-turn';

        document.getElementById('my-resources').textContent = `$${me.money} 💎 ${me.gems}`;
        document.getElementById('money-display').textContent = `$${me.money}`;
        document.getElementById('gems-display').textContent = `💎 ${me.gems}`;
        //document.getElementById('gems-display').style.display = (me.gems > 0 || s.is_my_turn) ? 'inline' : 'none';
        document.getElementById('stars-display').textContent = `⭐ ${me.stars}`;
        const endTurnBtn = document.getElementById('btn-end-turn');
        endTurnBtn.style.display = 'inline-block';
        endTurnBtn.disabled = !s.is_my_turn;
        const isLocal = ['localhost', '127.0.0.1'].includes(location.hostname);
        document.getElementById('btn-debug-end').style.display = (isLocal && !s.ended) ? 'inline-block' : 'none';
        if (s.is_my_turn) {
            endTurnBtn.classList.toggle('glow', this._shouldEndTurn());
        } else {
            endTurnBtn.classList.remove('glow');
        }

        document.getElementById('round-display').textContent = `Round ${s.round || 1}`;

        const missionsEl = document.getElementById('missions-display');
        const buysEl = document.getElementById('buys-display');
        if (s.is_my_turn) {
            const missionsAllowed = 1 + (me.extra_missions || 0);
            const missionsDone = me.missions_this_turn || 0;
            const missionsRemaining = missionsAllowed - missionsDone;
            missionsEl.textContent = `Missions: ${missionsRemaining}`;
            missionsEl.style.display = 'inline';

            const buyLimit = 1 + (me.extra_buys || 0);
            const buysRemaining = buyLimit - (me.buys_this_turn || 0);
            buysEl.textContent = `Buys: ${buysRemaining}`;
            buysEl.style.display = 'inline';
        } else {
            missionsEl.style.display = 'none';
            buysEl.style.display = 'none';
        }

        const banner = document.getElementById('final-round-banner');
        banner.style.display = s.final_round ? 'block' : 'none';
    },

    renderMarketplace() {
        const s = this.state;
        const me = s.players[s.my_index];
        const container = document.getElementById('marketplace');
        document.getElementById('market-deck-count').textContent = `(${s.market_deck_count} left)`;

        const counts = s.marketplace_counts || [];
        // Build indexed entries, sort by ascending cost
        const entries = s.marketplace.map((cardId, i) => ({ cardId, i, count: counts[i] || 1 }))
            .filter(e => e.cardId)
            .sort((a, b) => (this.catalog[a.cardId].cost || 0) - (this.catalog[b.cardId].cost || 0));
        const empties = s.marketplace.filter(c => !c).length;

        container.innerHTML = entries.map(({ cardId, i, count }) => {
            const card = this.catalog[cardId];
            const canBuy = (me.buys_this_turn || 0) < (1 + (me.extra_buys || 0));
            const canAfford = (me.money + (me.gems || 0)) >= card.cost;
            const affordable = s.is_my_turn && canAfford && canBuy;
            const unaffordable = s.is_my_turn ? (!canAfford || !canBuy) : true;
            const affordClass = affordable ? ' affordable' : (unaffordable ? ' unaffordable' : '');
            const stackBadge = count > 1 ? `<div class="stack-count">x${count}</div>` : '';
            const click = affordable ? `onclick="UI.onMarketClick('${cardId}', ${i})"` : '';
            const detail = (card.type === 'agent' || card.type === 'tech') && card.icons
                ? `<div class="card-icons">${this.getCardIcons(card)}</div>`
                : `<div class="card-desc">${esc(card.description)}</div>`;
            return `<div class="card market-card${affordClass}" style="border-color:${this.getCardColor(card)}" ${click}>
                <div class="card-name">${esc(card.name)}</div>
                <div class="card-cost">$${card.cost}</div>
                <div class="card-type">${card.type}</div>
                ${detail}
                ${stackBadge}
            </div>`;
        }).join('') + Array(empties).fill('<div class="card card-empty">Empty</div>').join('');

        // Render always-available cards (Muscle, Shadow)
        const alwaysContainer = document.getElementById('always-available-cards');
        const canBuyAny = s.is_my_turn && (me.buys_this_turn || 0) < (1 + (me.extra_buys || 0));
        const totalFunds = me.money + (me.gems || 0);
        const alwaysCards = [
            { id: 'muscle', name: 'Muscle', cost: 3, icon: '💪' },
            { id: 'shadow', name: 'Shadow', cost: 4, icon: '🥸' },
        ];
        alwaysContainer.innerHTML = alwaysCards.map(c => {
            const affordable = canBuyAny && totalFunds >= c.cost;
            const dimmed = !affordable ? ' unaffordable' : '';
            const click = affordable ? `onclick="UI.buyAlwaysAvailable('${c.id}', '${c.name}', ${c.cost})"` : '';
            return `<div class="card always-available-card${dimmed}" style="border-color:#b8a000" ${click}>
                <div class="card-name">${c.name}</div>
                <div class="card-cost">$${c.cost}</div>
                <div class="card-icons">${c.icon}</div>
                <div class="card-type">agent</div>
            </div>`;
        }).join('');

        // Buy Gem card (unlimited, costs $3 money only)
        const gemAffordable = s.is_my_turn && me.money >= 3;
        const gemDimmed = !gemAffordable ? ' unaffordable' : '';
        const gemClick = gemAffordable ? 'onclick="UI.buyGem()"' : '';
        alwaysContainer.innerHTML += `<div class="card always-available-card${gemDimmed}" style="border-color:#9c27b0" ${gemClick}>
            <div class="card-name">Buy Gem</div>
            <div class="card-cost">$3</div>
            <div class="card-icons">💎</div>
            <div class="card-type">gem</div>
        </div>`;
    },

    renderMissionGrid() {
        const container = document.getElementById('mission-grid');
        let html = '';
        for (const tier of [1, 2, 3]) {
            const deckCount = this.state.mission_deck_counts[tier];
            html += `<div class="mission-tier"><h4>Tier ${tier} <span class="deck-count">(${deckCount} left)</span></h4><div class="mission-tier-cards">`;
            const missions = this.state.mission_grid[tier] || [];
            const missionCounts = (this.state.mission_grid_counts || {})[tier] || [];
            for (let mi = 0; mi < missions.length; mi++) {
                const mId = missions[mi];
                if (!mId) continue;
                const card = this.catalog[mId];
                const reqIcons = this.formatReqIcons(card.requirements);
                const stars = card.stars ? `${card.stars}⭐ ` : '';
                const money = card.value ? `$${card.value}` : '';
                const count = missionCounts[mi] || 1;
                const countBadge = count > 1 ? `<div class="stack-count">×${count}</div>` : '';
                html += `<div class="card mission-card" onclick="UI.showMissionDialog('${mId}')">
                    <div class="card-name">${esc(card.name)}</div>
                    <div class="card-req">${reqIcons}</div>
                    <div class="card-reward">${stars}${money}</div>
                    ${countBadge}
                </div>`;
            }
            html += '</div></div>';
        }
        container.innerHTML = html;
    },

    renderHeist() {
        const card = this.catalog['heist'];
        const reqIcons = this.formatReqIcons(card.requirements);
        const el = document.getElementById('heist-mission');
        el.innerHTML = `<div class="card-name">${esc(card.name)}</div>
            <div class="card-req">${reqIcons}</div>
            <div class="card-reward">💎 1-3</div>
            <div class="card-desc">1-2 icons: 💎1 · 3-4: 💎2 · 5+: 💎3</div>`;
    },

    renderOpponents() {
        const container = document.getElementById('opponents');
        let html = '';
        for (let i = 0; i < this.state.players.length; i++) {
            const p = this.state.players[i];
            const isMe = i === this.state.my_index;
            const isActive = i === this.state.current_player;
            const label = isMe ? `${esc(p.name)} (You)` : esc(p.name);
            const tag = isActive ? ' ▶' : '';
            html += `<div class="opponent ${isActive ? 'active-player' : ''} ${isMe ? 'is-me' : ''}">
                <h4>${label}${tag}</h4>
                <div class="opponent-stats">
                    <span>⭐${p.stars}</span>
                    <span>$${p.money}</span>
                    ${p.gems > 0 ? `<span>💎${p.gems}</span>` : ''}
                </div>
            </div>`;
        }
        container.innerHTML = html;
    },

    getCardIcons(card) {
        if (!card.icons) return '';
        return this.formatReqIcons(card.icons);
    },

    getOwnedCardInfo(card) {
        // For cards in hand/play/discard, show reward instead of cost
        if (card.type === 'mission') {
            const stars = card.stars ? `${card.stars}⭐ ` : '';
            const money = card.value ? `$${card.value}` : '';
            return `<div class="card-reward">${stars}${money}</div>`;
        }
        if (card.type === 'money') {
            return `<div class="card-reward">$${card.value || 0}</div>`;
        }
        if (card.type === 'agent') {
            const icons = this.getCardIcons(card);
            return icons ? `<div class="card-icons">${icons}</div>` : '';
        }
        if (card.type === 'tech') {
            const icons = this.getCardIcons(card);
            return icons ? `<div class="card-icons">${icons}</div>` : '';
        }
        return `<div class="card-desc">${esc(card.description)}</div>`;
    },

    renderHand() {
        const me = this.state.players[this.state.my_index];
        const container = document.getElementById('my-hand');
        if (!this.state.is_my_turn) {
            container.innerHTML = me.hand.map(cardId => {
                const card = this.catalog[cardId];
                return `<div class="card hand-card" style="border-color:${this.getCardColor(card)}">
                    <div class="card-name">${esc(card.name)}</div>
                    <div class="card-type">${card.type}</div>
                    ${this.getOwnedCardInfo(card)}
                </div>`;
            }).join('');
            return;
        }
        container.innerHTML = me.hand.map(cardId => {
            const card = this.catalog[cardId];
            return `<div class="card hand-card playable" style="border-color:${this.getCardColor(card)}" onclick="UI.onHandClick('${cardId}')">
                <div class="card-name">${esc(card.name)}</div>
                <div class="card-type">${card.type}</div>
                ${this.getOwnedCardInfo(card)}
            </div>`;
        }).join('');
    },

    renderPlayArea() {
        const me = this.state.players[this.state.my_index];
        const container = document.getElementById('play-area');
        container.innerHTML = me.play_area.map(cardId => {
            const card = this.catalog[cardId];
            return `<div class="card played-card" style="border-color:${this.getCardColor(card)}">
                <div class="card-name">${esc(card.name)}</div>
                ${this.getOwnedCardInfo(card)}
            </div>`;
        }).join('');
    },

    renderDeck() {
        const me = this.state.players[this.state.my_index];
        document.getElementById('my-deck-count').textContent = me.deck_count + (me.deck_count == 1 ? " card" : " cards");
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
        container.innerHTML = `<div class="card played-card" style="border-color:${this.getCardColor(card)}">
            <div class="card-name">${esc(card.name)}</div>
            ${this.getOwnedCardInfo(card)}
        </div>`;
    },

    renderLog() {
        const container = document.getElementById('game-log');
        container.innerHTML = this.state.log.map(l => `<div class="log-entry">${esc(l)}</div>`).join('');
        container.scrollTop = container.scrollHeight;
    },

    renderGameOver() {
        if (!this.state.ended) return;

        // If rematch triggered, redirect to new game
        if (this.state.rematch_game_id) {
            const token = sessionStorage.getItem('spy_token');
            sessionStorage.setItem('spy_game_id', this.state.rematch_game_id);
            window.location.href = 'game.php?game_id=' + encodeURIComponent(this.state.rematch_game_id) + '&token=' + encodeURIComponent(token);
            return;
        }

        const scores = this.state.scores;
        if (!scores) return;
        const s = this.state;
        const totalPlayers = s.players.length;
        const voteCount = s.rematch_vote_count || 0;
        const myVote = s.rematch_my_vote || false;
        const onlyOne = totalPlayers < 2;

        let html = '<h2>Game Over!</h2><div class="scores">';
        scores.forEach((sc, i) => {
            html += `<div class="score-row ${i === 0 ? 'winner' : ''}">
                <span>${i + 1}. ${esc(sc.name)}</span>
                <span>${sc.stars} ⭐</span>
            </div>`;
        });
        html += '</div>';
        html += '<div class="gameover-actions">';
        html += '<button onclick="location.href=\'index.php\'">Back to Lobby</button>';
        const checked = myVote ? 'checked' : '';
        const disabled = onlyOne ? 'disabled' : '';
        html += `<label class="rematch-label ${onlyOne ? 'disabled' : ''}">
            <input type="checkbox" id="rematch-checkbox" ${checked} ${disabled}
                onchange="UI.onRematchVote(this.checked)">
            Rematch? <span class="rematch-count">(${voteCount}/${totalPlayers})</span>
        </label>`;
        html += '</div>';
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-overlay').style.display = 'flex';
    },

    onRematchVote(vote) {
        Actions.voteRematch(vote);
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

    buyGem() {
        if (!this.state.is_my_turn) return;
        const me = this.state.players[this.state.my_index];
        if (me.money < 3) {
            this.showError('Not enough money (need $3).');
            return;
        }
        Actions.buyGem();
    },

    buyAlwaysAvailable(cardId, name, cost) {
        if (!this.state.is_my_turn) return;
        const me = this.state.players[this.state.my_index];
        if ((me.buys_this_turn || 0) >= (1 + (me.extra_buys || 0))) {
            this.showError('Already bought maximum cards this turn.');
            return;
        }
        const gemsNeeded = Math.max(0, cost - me.money);
        let msg = `Buy ${name} for $${cost}?`;
        if (gemsNeeded > 0) {
            msg += `\n(Will spend ${gemsNeeded} gem${gemsNeeded > 1 ? 's' : ''} to cover the difference)`;
        }
        if (confirm(msg)) {
            Actions.buyAlwaysAvailable(cardId);
        }
    },

    onMarketClick(cardId, slot) {
        if (!this.state.is_my_turn) return;
        const me = this.state.players[this.state.my_index];
        if ((me.buys_this_turn || 0) >= (1 + (me.extra_buys || 0))) {
            this.showError('Already bought maximum cards this turn.');
            return;
        }
        const card = this.catalog[cardId];
        const cost = card.cost;
        const gemsNeeded = Math.max(0, cost - me.money);
        let msg = `Buy ${card.name} for $${cost}?`;
        if (gemsNeeded > 0) {
            msg += `\n(Will spend ${gemsNeeded} gem${gemsNeeded > 1 ? 's' : ''} to cover the difference)`;
        }
        if (confirm(msg)) {
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
        const areas = [
            { key: 'hand', label: 'Hand', cards: me.hand.filter(cid => cid !== plotCardId) },
            { key: 'play_area', label: 'Play Area', cards: me.play_area },
            { key: 'discard', label: 'Discard', cards: me.discard },
        ];
        let hasAny = false;
        let html = '<h3>Training Procedure: Select agent to trash</h3>';
        for (const area of areas) {
            const agents = area.cards.filter(cid => this.catalog[cid] && this.catalog[cid].type === 'agent');
            const unique = [...new Set(agents)];
            if (unique.length === 0) continue;
            hasAny = true;
            html += `<p style="color:#888;margin:8px 0 4px;font-size:12px">${area.label}</p>`;
            unique.forEach(cid => {
                const c = this.catalog[cid];
                const maxCost = (c.cost || 0) + 3;
                html += `<button class="btn-modal" onclick="UI.showTrainingGainDialog('${plotCardId}', '${cid}', ${maxCost}, '${area.key}')">
                    Trash ${esc(c.name)} ($${c.cost}) — gain up to $${maxCost}
                </button>`;
            });
        }
        if (!hasAny) {
            this.showError('No agents to trash!');
            return;
        }
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);
    },

    showTrainingGainDialog(plotCardId, trashAgent, maxCost, trashFrom) {
        const catalog = this.catalog;
        let html = `<h3>Training: Gain an agent (up to $${maxCost})</h3>`;

        // Always-available agents
        const alwaysAvail = Object.values(catalog).filter(c => c.type === 'agent' && c.always_available && c.cost <= maxCost);
        for (const c of alwaysAvail) {
            html += `<button class="btn-modal" onclick="Actions.playPlot('${plotCardId}', {trash_agent:'${trashAgent}', gain_agent:'${c.id}', gain_slot:-1, trash_from:'${trashFrom}'}); UI.closeModal()">
                ${esc(c.name)} ($${c.cost}) — Always available
            </button>`;
        }

        // Marketplace agents
        this.state.marketplace.forEach((cardId, slot) => {
            if (!cardId) return;
            const c = catalog[cardId];
            if (c.type === 'agent' && c.cost <= maxCost) {
                html += `<button class="btn-modal" onclick="Actions.playPlot('${plotCardId}', {trash_agent:'${trashAgent}', gain_agent:'${cardId}', gain_slot:${slot}, trash_from:'${trashFrom}'}); UI.closeModal()">
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

        const reqIcons = this.formatReqIcons(mission.requirements);
        const isHeist = (mission.gems || 0) > 0;

        let html = `<h3>Run: ${esc(mission.name)}</h3>`;
        html += `<p>Requires: ${reqIcons}</p>`;
        if (isHeist) {
            html += `<table class="heist-table">
                <tr><th>Icons</th><th>Gems</th></tr>
                <tr><td>1-2</td><td>💎 1</td></tr>
                <tr><td>3-4</td><td>💎 2</td></tr>
                <tr><td>5+</td><td>💎 3</td></tr>
            </table>`;
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
            Run Mission
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

    showModal(html) {
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-overlay').style.display = 'flex';
    },

    closeModal() {
        if (this.state && this.state.ended) return;
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
