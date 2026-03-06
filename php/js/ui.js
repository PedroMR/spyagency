// Discreet click sound using Web Audio API
function playClick() {
    try {
        console.log("playClick");
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

// Low chime to nudge player to end turn
function playChime() {
    try {
        console.log("playChime");
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const t = ctx.currentTime;
        [330, 262].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'triangle';
            osc.frequency.value = freq;
            const start = t + i * 0.15;
            gain.gain.setValueAtTime(0.12, start);
            gain.gain.exponentialRampToValueAtTime(0.001, start + 0.4);
            osc.start(start);
            osc.stop(start + 0.4);
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

    _applyFLIPFrom(firstRect, el) {
        const lastRect = el.getBoundingClientRect();
        const dx = firstRect.left - lastRect.left;
        const dy = firstRect.top - lastRect.top;
        if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return;
        el.style.transition = 'none';
        el.style.transform = `translate(${dx}px, ${dy}px)`;
        el.getBoundingClientRect(); // force reflow
        requestAnimationFrame(() => {
            el.style.transition = 'transform 0.35s ease';
            el.style.transform = '';
        });
    },

    iconMap: {
        drive: '🚘',
        muscle: '💪',
        disguise: '🥸',
        key: '🔑',
    },

    typeColors: {
        money: '#00e676',
        agent: '#ffd740',
        tech: '#40c4ff',
        plot: '#18ffff',
        mission: '#e040fb',
        hazard: '#ff1744',
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
        if (!card) return '#555';
        return this.typeColors[card.type] || '#555';
    },

    _autoPlayTimer: null,
    _autoPlaying: false,
    _lastTurnNumber: null,

    update(state) {
        // Capture hand card positions and old play area BEFORE re-rendering
        const oldPlayArea = this.state?.players?.[this.state?.my_index]?.play_area ?? [];
        const handRects = {};
        document.querySelectorAll('#my-hand [data-card-id]').forEach(el => {
            const cid = el.dataset.cardId;
            if (!handRects[cid]) handRects[cid] = el.getBoundingClientRect();
        });

        this.state = state;
        this.catalog = state.catalog;
        this.renderTurnInfo();
        this.renderMarketplace();
        this.renderMissionGrid();
        this.renderHeist();
        this.renderOpponents();
        this.renderHand();
        this.renderPlayArea();

        // Animate newly added play area cards flying from their hand positions
        const newPlayArea = state.players[state.my_index].play_area;
        if (newPlayArea.length > oldPlayArea.length) {
            const allPlayEls = document.querySelectorAll('#play-area [data-card-id]');
            for (let i = oldPlayArea.length; i < newPlayArea.length; i++) {
                const rect = handRects[newPlayArea[i]];
                const el = allPlayEls[i];
                if (rect && el) this._applyFLIPFrom(rect, el);
            }
        }
        this.renderMissionArea();
        this.renderDeck();
        this.renderDiscardPile();
        this.renderLog();
        this.renderGameOver();

        // Show attack defense dialog or waiting indicator
        if (state.attack_pending) {
            if (state.attack_must_defend) {
                this.showDefenseDialog();
            } else {
                this.showAttackWaiting();
            }
        } else {
            // Close modal if attack just resolved (was previously pending)
            if (this._attackWasPending) {
                document.getElementById('modal-overlay').style.display = 'none';
                this._defenseDialogShown = false;
            }
        }
        this._attackWasPending = state.attack_pending;

        // Auto-play money and money-only mission cards with 1s delay
        if (state.is_my_turn && !state.attack_pending && !this._autoPlaying && this._lastTurnNumber !== state.turn_number) {
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
        // Re-evaluate glow after a short delay to avoid overlapping with click sounds
        setTimeout(() => {
            if (this.state && this.state.is_my_turn) {
                const endTurnBtn = document.getElementById('btn-end-turn');
                const shouldGlow = this._shouldEndTurn();
                const wasGlowing = endTurnBtn.classList.contains('glow');
                endTurnBtn.classList.toggle('glow', shouldGlow);
                if (shouldGlow && !wasGlowing) playChime();
            }
        }, 500);
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
            if (c.type === 'money') return true;
            if (c.type === 'mission' && (c.value || 0) > 0) return true;
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

        let myResources = `$${me.money} 💎 ${me.gems}`;
        document.getElementById('money-display').textContent = `$${me.money}`;
        document.getElementById('gems-display').textContent = `💎 ${me.gems}`;
        //document.getElementById('gems-display').style.display = (me.gems > 0 || s.is_my_turn) ? 'inline' : 'none';
        document.getElementById('stars-display').textContent = `⭐ ${me.stars}`;
        const endTurnBtn = document.getElementById('btn-end-turn');
        const trashBtn = document.getElementById('btn-use-trash');
        const pending = s.is_my_turn ? (me.pending_trashes || 0) : 0;
        if (trashBtn) {
            trashBtn.style.display = pending > 0 ? 'inline-block' : 'none';
            trashBtn.textContent = pending > 1 ? `✖️ Trash (×${pending})` : '✖️ Trash';
        }
        endTurnBtn.style.display = pending > 0 ? 'none' : 'inline-block';
        endTurnBtn.disabled = !s.is_my_turn || s.attack_pending;
        const isLocal = ['localhost', '127.0.0.1'].includes(location.hostname);
        document.getElementById('debug-buttons').style.display = (isLocal && !s.ended) ? 'flex' : 'none';
        if (s.is_my_turn) {
            const shouldGlow = this._shouldEndTurn();
            const wasGlowing = endTurnBtn.classList.contains('glow');
            endTurnBtn.classList.toggle('glow', shouldGlow);
            if (shouldGlow && !wasGlowing) playChime();
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
            missionsEl.textContent = `Ops: ${missionsRemaining}`;
            missionsEl.style.display = 'inline';
            
            const buyLimit = 1 + (me.extra_buys || 0);
            const buysRemaining = buyLimit - (me.buys_this_turn || 0);
            buysEl.textContent = `Buys: ${buysRemaining}`;
            buysEl.style.display = 'inline';

            myResources += ` Ops: ${missionsRemaining} Buys: ${buysRemaining}`;
        } else {
            missionsEl.style.display = 'none';
            buysEl.style.display = 'none';
        }
        document.getElementById('my-resources').textContent = myResources;
        
        const banner = document.getElementById('final-round-banner');
        banner.style.display = s.final_round ? 'block' : 'none';

        if (!window._rulesOpened) {
            document.querySelectorAll('.rules-btn').forEach(btn =>
                btn.classList.toggle('rules-btn-glow', s.round === 1)
            );
        }

        const resignBtn = document.getElementById('btn-resign');
        if (resignBtn) {
            resignBtn.textContent = s.ended ? 'Lobby' : 'Resign';
        }
    },

    renderMarketplace() {
        const s = this.state;
        const me = s.players[s.my_index];
        const container = document.getElementById('marketplace');
        document.getElementById('market-deck-count').textContent = `(${s.market_deck_count} left)`;
        const restockBtn = document.getElementById('btn-restock');
        if (restockBtn) restockBtn.disabled = !s.is_my_turn || (me.money + (me.gems || 0)) < 2 || s.market_deck_count === 0;

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
            const affordClass = unaffordable ? ' unaffordable' : '';
            const stackBadge = count > 1 ? `<div class="stack-count">x${count}</div>` : '';
            const click = affordable ? `onclick="UI.onMarketClick('${cardId}', ${i})"` : '';
            const defenderBadge = card.defend ? `<div class="card-defender">DEFENDER</div>` : '';
            const primedBadge = card.primed ? `<div class="card-primed">PRIMED</div>` : '';
            const detail = (card.type === 'agent' || card.type === 'tech') && card.icons
                ? `<div class="card-icons">${this.getCardIcons(card)}</div>${defenderBadge}${primedBadge}`
                : `<div class="card-desc">${esc(card.description)}</div>${defenderBadge}${primedBadge}`;
            return `<div class="card market-card${affordClass}" style="--card-color:${this.getCardColor(card)}" ${click}>
                <div class="card-row-top">
                    <span class="card-name">${esc(card.name)}</span>
                    <span class="card-cost-badge">$${card.cost}</span>
                </div>
                ${detail}
                ${stackBadge}
                <div class="card-type">${card.type}</div>
            </div>`;
        }).join('') + Array(empties).fill('<div class="card card-empty">Empty</div>').join('');

        // Render always-available cards (Muscle, Shadow)
        const alwaysContainer = document.getElementById('always-available-cards');
        const canBuyAny = s.is_my_turn && (me.buys_this_turn || 0) < (1 + (me.extra_buys || 0));
        const totalFunds = me.money + (me.gems || 0);
        const alwaysCards = Object.values(this.catalog)
            .filter(c => c.type === 'agent' && c.always_available)
            .sort((a, b) => a.cost - b.cost);
        alwaysContainer.innerHTML = alwaysCards.map(c => {
            const affordable = canBuyAny && totalFunds >= c.cost;
            const dimmed = !affordable ? ' unaffordable' : '';
            const click = affordable ? `onclick="UI.buyAlwaysAvailable('${c.id}', '${c.name}', ${c.cost})"` : '';
            const primedBadge = c.primed ? `<div class="card-primed">PRIMED</div>` : '';
            return `<div class="card always-available-card${dimmed}" style="--card-color:#ffd740" ${click}>
                <div class="card-row-top">
                    <span class="card-name">${c.name}</span>
                    <span class="card-cost-badge">$${c.cost}</span>
                </div>
                <div class="card-icons">${this.getCardIcons(c)}</div>
                ${primedBadge}
                <div class="card-type">agent</div>
            </div>`;
        }).join('');

        // Buy Gem card (unlimited, costs $3 money only)
        const gemAffordable = s.is_my_turn && me.money >= 3;
        const gemDimmed = !gemAffordable ? ' unaffordable' : '';
        const gemClick = gemAffordable ? 'onclick="UI.buyGem()"' : '';
        alwaysContainer.innerHTML += `<div class="card always-available-card${gemDimmed}" style="--card-color:#e040fb" ${gemClick}>
            <div class="card-row-top">
                <span class="card-name">Buy Gem</span>
                <span class="card-cost-badge">$3</span>
            </div>
            <div class="card-icons">💎</div>
            <div class="card-type">gem</div>
        </div>`;
    },

    formatSegReward(seg) {
        const parts = [];
        if (seg.stars)   parts.push(`<span class="r-star">${seg.stars}⭐</span>`);
        if (seg.gems)    parts.push(`<span class="r-gem">${seg.gems}💎</span>`);
        if (seg.money)   parts.push(`<span class="r-money">$${seg.money}</span>`);
        if (seg.cards)   parts.push(`<span class="r-card">${seg.cards}🎴</span>`);
        if (seg.trashes) parts.push(`<span class="r-trash">${seg.trashes}✖️</span>`);
        return parts.join(' ');
    },

    renderSegments(card) {
        const segs = card.segments || [];
        // const segNums = ['①', '②', '③'];
        const segNums = ['1)', '2)', '3)'];
        return segs.map((seg, i) => {
            const req = this.formatReqIcons(seg.requirements || []);
            const rew = this.formatSegReward(seg);
            return `<div class="seg-divider"></div>
                    <div class="segment">
                      <span class="seg-num">${segNums[i]}</span>
                      <span class="seg-req">${req}</span>
                      <span class="seg-arrow">→</span>
                      <span class="seg-reward">${rew}</span>
                    </div>`;
        }).join('');
    },

    renderMissionGrid() {
        const container = document.getElementById('mission-grid');
        const ops = this.state.ops_grid || [];
        const opsCounts = this.state.ops_grid_counts || [];
        const deckCount = this.state.ops_deck_count ?? 0;
        let html = `<div class="ops-deck-count">Ops deck: ${deckCount} remaining</div><div class="mission-tier-cards">`;
        for (let mi = 0; mi < ops.length; mi++) {
            const mId = ops[mi];
            if (!mId) continue;
            const card = this.catalog[mId];
            if (!card) continue;
            const count = opsCounts[mi] || 1;
            const countBadge = count > 1 ? `<div class="stack-count">×${count}</div>` : '';
            const segHtml = this.renderSegments(card);
            html += `<div class="card mission-card card-v" style="--card-color:${this.getCardColor(card)}" onclick="UI.showMissionDialog('${mId}')">
                <div class="card-name">${esc(card.name)}</div>
                ${segHtml}
                ${countBadge}
            </div>`;
        }
        html += '</div>';
        container.innerHTML = html;
    },

    renderMissionArea() {
        const me = this.state.players[this.state.my_index];
        const missionArea = me.mission_area || [];
        const section = document.getElementById('mission-area-section');
        const container = document.getElementById('my-mission-area');
        const incomeDisplay = document.getElementById('mission-income-display');

        if (missionArea.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = '';

        // Unique op names splay vertically (each shows its reward strip).
        // Duplicate copies of the same op splay horizontally, covering the
        // previous copy's reward — icons are only rewarded once per unique op.
        const STRIP = 22;       // visible px for each non-top group (reward line)
        const CARD_H_TOP = 47;  // estimated top group height (name + reward + padding)
        const CARD_H_REST = 28; // estimated non-top group height (reward only)
        const H_OFFSET = 12;    // horizontal px offset per duplicate copy

        // Group by card id, preserving first-occurrence order
        const groupOrder = [];
        const groupCounts = {};
        for (const mid of missionArea) {
            if (!groupCounts[mid]) { groupOrder.push(mid); groupCounts[mid] = 0; }
            groupCounts[mid]++;
        }
        const G = groupOrder.length;

        // Vertical positions per group (G-1 = topmost/front)
        const positions = new Array(G);
        positions[G - 1] = 0;
        let prevBottom = CARD_H_TOP;
        for (let i = G - 2; i >= 0; i--) {
            positions[i] = prevBottom + STRIP - CARD_H_REST;
            prevBottom = positions[i] + CARD_H_REST;
        }

        let totalIncome = 0;
        let totalExtraMissions = 0;
        let totalExtraBuys = 0;
        const htmlParts = [];

        groupOrder.forEach((mid, gi) => {
            const card = this.catalog[mid];
            if (!card) return;
            const count = groupCounts[mid];
            const income = card.value || 0;
            totalIncome += income * count;
            if (card.extra_mission) totalExtraMissions += count;
            if (card.extra_buy) totalExtraBuys += count;

            const isTopGroup = gi === G - 1;
            const stars = card.stars ? `${card.stars}⭐ ` : '';
            const rewardParts = [];
            if (income > 0) rewardParts.push(`$${income}/turn`);
            if (card.extra_mission) rewardParts.push('+1 op/turn');
            if (card.extra_buy) rewardParts.push('+1 buy/turn');
            if (card.icons && card.icons.length) rewardParts.push(this.formatReqIcons(card.icons));
            const reward = (stars || rewardParts.length > 0) ? `${stars}${rewardParts.join(' ')}` : '';
            const cardHeight = isTopGroup ? CARD_H_TOP : CARD_H_REST;
            for (let ci = 0; ci < count; ci++) {
                const isTopCard = ci === count - 1;
                const z = (gi + 1) * 10 + ci;
                htmlParts.push(`<div class="card played-card" title="${esc(card.name)}" style="--card-color:${this.getCardColor(card)};position:absolute;top:${positions[gi]}px;left:${ci * H_OFFSET}px;z-index:${z};min-height:${cardHeight}px">`);
                if (isTopCard) {
                    if (isTopGroup) htmlParts.push(`<div class="card-name">${esc(card.name)}</div>`);
                    if (reward) htmlParts.push(`<div class="card-reward">${reward}</div>`);
                }
                htmlParts.push('</div>');
            }
        });

        container.style.position = 'relative';
        container.style.height = prevBottom + 'px';
        container.innerHTML = htmlParts.join('');

        const summaryParts = [];
        if (totalIncome > 0) summaryParts.push(`$${totalIncome}/turn`);
        if (totalExtraMissions > 0) summaryParts.push(`+${totalExtraMissions} op/turn`);
        if (totalExtraBuys > 0) summaryParts.push(`+${totalExtraBuys} buy/turn`);
        incomeDisplay.textContent = summaryParts.join(' ');
        incomeDisplay.style.display = summaryParts.length > 0 ? 'inline' : 'none';
    },

    renderHeist() {
        const card = this.catalog['heist'];
        const el = document.getElementById('heist-mission');
        el.style.setProperty('--card-color', '#e040fb');
        el.classList.add('card-v');
        el.innerHTML = `<div class="card-name">${esc(card.name)}</div>${this.renderSegments(card)}`;
    },

    renderOpponents() {
        const container = document.getElementById('opponents');
        let html = '';
        for (let i = 0; i < this.state.players.length; i++) {
            const p = this.state.players[i];
            const isMe = i === this.state.my_index;
            const isActive = i === this.state.current_player;
            const aiTag = (!isMe && p.is_ai) ? ' 🤖' : '';
            const label = isMe ? `${esc(p.name)} (You)` : esc(p.name);
            const tag = isActive ? ' ▶' : '';
            const missionArea = p.mission_area || [];
            const missionCount = missionArea.length;
            const missionBadge = missionCount > 0 ? `<span>🎯${missionCount}</span>` : '';
            html += `<div class="opponent ${isActive ? 'active-player' : ''} ${isMe ? 'is-me' : ''}">
                <h4>${label}${aiTag}${tag}</h4>
                <div class="opponent-stats">
                    <span>⭐${p.stars}</span>
                    <span>$${p.money}</span>
                    ${p.gems > 0 ? `<span>💎${p.gems}</span>` : ''}
                    ${missionBadge}
                </div>
            </div>`;
        }
        container.innerHTML = html;
    },

    getCardIcons(card) {
        if (!card || !card.icons) return '';
        return this.formatReqIcons(card.icons);
    },

    getOwnedCardInfo(card) {
        if (!card) return '';
        // For cards in hand/play/discard, show reward instead of cost
        if (card.type === 'mission') {
            const stars = card.stars ? `${card.stars}⭐ ` : '';
            const rewardParts = [];
            if (card.value) rewardParts.push(`$${card.value}/turn`);
            if (card.extra_mission) rewardParts.push('+1 op/turn');
            if (card.extra_buy) rewardParts.push('+1 buy/turn');
            return `<div class="card-reward">${stars}${rewardParts.join(' ')}</div>`;
        }
        if (card.type === 'money') {
            return `<div class="card-reward">$${card.value || 0}</div>`;
        }
        if (card.type === 'agent') {
            const icons = this.getCardIcons(card);
            const defenderBadge = card.defend ? `<div class="card-defender">DEFENDER</div>` : '';
            const primedBadge = card.primed ? `<div class="card-primed">PRIMED</div>` : '';
            return (icons ? `<div class="card-icons">${icons}</div>` : '') + defenderBadge + primedBadge;
        }
        if (card.type === 'tech') {
            const icons = this.getCardIcons(card);
            const primedBadge = card.primed ? `<div class="card-primed">PRIMED</div>` : '';
            return (icons ? `<div class="card-icons">${icons}</div>` : '') + primedBadge;
        }
        return `<div class="card-desc">${esc(card.description)}</div>`;
    },

    renderHand() {
        const me = this.state.players[this.state.my_index];
        const container = document.getElementById('my-hand');
        if (!this.state.is_my_turn) {
            container.innerHTML = me.hand.map(cardId => {
                const card = this.catalog[cardId];
                if (!card) return '';
                return `<div class="card hand-card" data-card-id="${cardId}" style="--card-color:${this.getCardColor(card)}">
                    <div class="card-name">${esc(card.name)}</div>
                    <div class="card-type">${card.type}</div>
                    ${this.getOwnedCardInfo(card)}
                </div>`;
            }).join('');
            return;
        }
        container.innerHTML = me.hand.map(cardId => {
            const card = this.catalog[cardId];
            if (!card) return '';
            return `<div class="card hand-card playable" data-card-id="${cardId}" style="--card-color:${this.getCardColor(card)}" onclick="UI.onHandClick('${cardId}')">
                <div class="card-name">${esc(card.name)}</div>
                <div class="card-type">${card.type}</div>
                ${this.getOwnedCardInfo(card)}
            </div>`;
        }).join('');
    },

    renderPlayArea() {
        const me = this.state.players[this.state.my_index];
        const container = document.getElementById('play-area');
        const existing = Array.from(container.children);

        // If cards were only added (not removed/reordered), append new ones to
        // preserve ongoing CSS transitions on already-animated elements.
        const canAppend = existing.length <= me.play_area.length &&
            existing.every((el, i) => el.dataset.cardId === me.play_area[i]);

        if (canAppend) {
            for (let i = existing.length; i < me.play_area.length; i++) {
                const cardId = me.play_area[i];
                const card = this.catalog[cardId];
                const div = document.createElement('div');
                div.className = 'card played-card';
                div.dataset.cardId = cardId;
                div.style.setProperty('--card-color', this.getCardColor(card));
                div.innerHTML = `<div class="card-name">${esc(card.name)}</div>
                    ${this.getOwnedCardInfo(card)}
                    <div class="card-type">${card.type}</div>`;
                container.appendChild(div);
            }
        } else {
            container.innerHTML = me.play_area.map(cardId => {
                const card = this.catalog[cardId];
                return `<div class="card played-card" data-card-id="${cardId}" style="--card-color:${this.getCardColor(card)}">
                    <div class="card-name">${esc(card.name)}</div>
                    ${this.getOwnedCardInfo(card)}
                    <div class="card-type">${card.type}</div>
                </div>`;
            }).join('');
        }
    },

    renderDeck() {
        const me = this.state.players[this.state.my_index];
        const el = document.getElementById('my-deck-count');
        const label = me.deck_count + (me.deck_count == 1 ? ' card' : ' cards');
        if (me.deck_count > 0) {
            el.innerHTML = `<span class="discard-label" onclick="UI.showDeckDialog()">${label}</span>`;
        } else {
            el.textContent = label;
        }
    },

    showDeckDialog() {
        const me = this.state.players[this.state.my_index];
        const deck = [...(me.deck || [])];
        // Shuffle client-side so draw order isn't revealed
        for (let i = deck.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [deck[i], deck[j]] = [deck[j], deck[i]];
        }
        let html = `<h3>Your Deck (${deck.length} card${deck.length !== 1 ? 's' : ''})</h3>`;
        html += '<div class="card-row" style="margin-top:10px">';
        for (const cid of deck) {
            const card = this.catalog[cid];
            if (!card) continue;
            html += `<div class="card played-card" style="--card-color:${this.getCardColor(card)}">
                <div class="card-name">${esc(card.name)}</div>
                ${this.getOwnedCardInfo(card)}
                <div class="card-type">${card.type}</div>
            </div>`;
        }
        html += '</div>';
        html += '<button class="btn-modal btn-cancel" style="margin-top:12px" onclick="UI.closeModal()">Close</button>';
        this.showModal(html);
    },

    renderDiscardPile() {
        const me = this.state.players[this.state.my_index];
        const discard = me.discard || [];
        const countEl = document.getElementById('discard-count');
        const container = document.getElementById('discard-pile');
        countEl.textContent = '';

        if (discard.length === 0) {
            container.innerHTML = '<span class="muted">Empty</span>';
            return;
        }
        container.innerHTML = `<span class="discard-label" onclick="UI.showDiscardDialog()">${discard.length} card${discard.length !== 1 ? 's' : ''}</span>`;
    },

    showDiscardDialog() {
        const me = this.state.players[this.state.my_index];
        const discard = me.discard || [];
        let html = `<h3>Discard Pile (${discard.length} cards)</h3>`;
        html += '<div class="card-row" style="margin-top:10px">';
        for (let i = discard.length - 1; i >= 0; i--) {
            const card = this.catalog[discard[i]];
            if (!card) continue;
            html += `<div class="card played-card" style="--card-color:${this.getCardColor(card)}">
                <div class="card-name">${esc(card.name)}</div>
                ${this.getOwnedCardInfo(card)}
                <div class="card-type">${card.type}</div>
            </div>`;
        }
        html += '</div>';
        html += '<button class="btn-modal btn-cancel" style="margin-top:12px" onclick="UI.closeModal()">Close</button>';
        this.showModal(html);
    },

    renderLog() {
        const container = document.getElementById('game-log');
        const scroller = container.parentElement;
        const prevCount = this._logCount || 0;
        const newCount = this.state.log.length;
        container.innerHTML = this.state.log.map(l => `<div class="log-entry">${esc(l)}</div>`).join('');
        if (newCount > prevCount) {
            scroller.scrollTop = scroller.scrollHeight;
        }
        this._logCount = newCount;
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
        const humanTotal = s.rematch_human_total ?? s.players.length;
        const voteCount = s.rematch_vote_count || 0;
        const myVote = s.rematch_my_vote || false;
        const onlyOne = humanTotal < 2;

        const topStars = scores[0].stars;
        const topMissions = scores[0].missions ?? 0;
        const starsTied = scores.filter(s => s.stars === topStars).length > 1;
        const sharedVictory = starsTied && scores.filter(s => s.stars === topStars && (s.missions ?? 0) === topMissions).length > 1;

        let html = `<h2>${sharedVictory ? 'Shared Victory!' : 'Game Over!'}</h2>`;
        if (starsTied) {
            html += `<p style="color:#aaa;font-size:13px;margin-bottom:6px">Tiebreaker: fewest ops</p>`;
        }
        html += '<div class="scores">';
        scores.forEach((sc, i) => {
            const isWinner = sc.stars === topStars && (sc.missions ?? 0) === topMissions;
            const missionNote = starsTied ? `<span style="color:#888;font-size:13px">${sc.missions ?? 0} ops</span>` : '';
            html += `<div class="score-row ${isWinner ? 'winner' : ''}">
                <span>${i + 1}. ${esc(sc.name)}</span>
                ${missionNote}
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
            Rematch? <span class="rematch-count">(${voteCount}/${humanTotal})</span>
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
        if (this.state.attack_pending) return;
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
                this.showError('Agents and tech are played when completing ops.');
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
        this.showConfirm(msg, () => Actions.buyAlwaysAvailable(cardId), null, `Buy ${name}`);
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
        this.showConfirm(msg, () => Actions.buyCard(cardId, slot), null, `Buy ${card.name}`);
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

    showOpTrashDialog() {
        const me = this.state.players[this.state.my_index];
        if ((me.pending_trashes || 0) <= 0) return;
        const areas = [
            {key: 'hand',      label: 'Hand',       cards: me.hand},
            {key: 'play_area', label: 'Play Area',   cards: me.play_area},
            {key: 'discard',   label: 'Discard',     cards: me.discard || []},
        ];
        let html = '<h3>✖️ Trash a card (op reward)</h3>';
        let hasAny = false;
        for (const area of areas) {
            if (area.cards.length === 0) continue;
            html += `<h4>${area.label}</h4>`;
            const seen = new Set();
            for (const cid of area.cards) {
                if (seen.has(cid)) continue;
                seen.add(cid);
                const c = this.catalog[cid];
                if (!c) continue;
                hasAny = true;
                html += `<button class="btn-modal" onclick="Actions.useTrash('${cid}', '${area.key}'); UI.closeModal()">
                    ${esc(c.name)}
                </button>`;
            }
        }
        if (!hasAny) {
            this.showError('No cards to trash!');
            return;
        }
        html += '<button class="btn-modal" style="background:#b03000;border-color:#ff7043" onclick="Actions.skipTrash();UI.closeModal()">Skip (forfeit)</button>';
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
        const plotCard = this.catalog[plotCardId];
        let html = `<h3>${esc(plotCard?.name || 'Select backup agent')}:</h3>`;
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

        const missionsAllowed = 1 + (me.extra_missions || 0);
        const missionsDone = me.missions_this_turn || 0;
        if (missionsDone >= missionsAllowed) {
            this.showError('No ops remaining this turn.');
            return;
        }

        // Find agents and tech in hand
        const handAgents = me.hand.filter(cid => this.catalog[cid].type === 'agent');
        const handTech = me.hand.filter(cid => this.catalog[cid].type === 'tech');

        if (handAgents.length === 0) {
            this.showError('No agents available to attempt ops!');
            return;
        }

        const segs = mission.segments || [];
        let html = `<h3>Run: ${esc(mission.name)}</h3>`;

        // Segment selector
        html += '<p>Run up to segment:</p>';
        const segNums = ['①', '②', '③'];
        segs.forEach((seg, i) => {
            const cumReqs = segs.slice(0, i + 1).flatMap(s => s.requirements || []);
            let totStars = 0, totGems = 0, totMoney = 0, totCards = 0, totTrashes = 0;
            for (let j = 0; j <= i; j++) {
                const s = segs[j];
                totStars   += s.stars   || 0;
                totGems    += s.gems    || 0;
                totMoney   += s.money   || 0;
                totCards   += s.cards   || 0;
                totTrashes += s.trashes || 0;
            }
            const cumRewParts = [];
            if (totStars)   cumRewParts.push(`${totStars}⭐`);
            if (totGems)    cumRewParts.push(`${totGems}💎`);
            if (totMoney)   cumRewParts.push(`$${totMoney}`);
            if (totCards)   cumRewParts.push(`${totCards}🎴`);
            if (totTrashes) cumRewParts.push(`${totTrashes}✖️`);
            const cumRew = cumRewParts.join(' ');
            html += `<label class="mission-select-item seg-option" data-seg="${i + 1}">
                <input type="radio" name="mission-seg" value="${i + 1}" ${i === 0 ? 'checked' : ''}>
                ${segNums[i]} ${this.formatReqIcons(cumReqs)} → ${cumRew}
            </label>`;
        });

        // Show icons from mission area (backward compat)
        const missionAreaIcons = [...new Set(me.mission_area || [])].flatMap(mid => this.catalog[mid]?.icons || []);
        if (missionAreaIcons.length > 0) {
            html += `<p>From completed ops: ${this.formatReqIcons(missionAreaIcons)}</p>`;
        }

        html += '<p>Select agents (up to 2):</p>';

        const uniqueAgents = [...new Set(handAgents)];
        uniqueAgents.forEach(cid => {
            const c = this.catalog[cid];
            const count = handAgents.filter(x => x === cid).length;
            const icons = this.getCardIcons(c);
            for (let i = 0; i < count; i++) {
                html += `<label class="mission-select-item">
                    <input type="checkbox" name="mission-agent" value="${cid}" data-max-tech="${c.max_tech || 0}" onchange="UI._onAgentChange()">
                    ${esc(c.name)} (${icons}) [tech: ${c.max_tech || 0}]
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

        html += `<button id="btn-run-op" class="btn-modal" style="margin-top:12px;background:#1a8a2d;border-color:#2dbc45" onclick="UI.submitMission('${missionId}')" disabled>
            Run Op
        </button>`;
        html += '<button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>';
        this.showModal(html);

        // Wire up live requirement checking
        const onChange = () => UI._updateRunOpButton(missionId);
        document.querySelectorAll('input[name="mission-agent"], input[name="mission-tech"], input[name="mission-seg"]')
            .forEach(el => el.addEventListener('change', onChange));
    },

    _onAgentChange() {
        const handChecked = Array.from(document.querySelectorAll('input[name="mission-agent"]:checked'));
        // Enforce max 2 agents: disable unchecked when 2 already selected
        if (handChecked.length >= 2) {
            document.querySelectorAll('input[name="mission-agent"]:not(:checked)').forEach(cb => {
                cb.disabled = true;
            });
        } else {
            document.querySelectorAll('input[name="mission-agent"]').forEach(cb => {
                if (!cb.checked) cb.disabled = false;
            });
        }
    },

    _updateRunOpButton(missionId) {
        const btn = document.getElementById('btn-run-op');
        if (!btn) return;
        const mission = this.catalog[missionId];
        const me = this.state.players[this.state.my_index];
        const segs = mission.segments || [];

        // Get selected segment
        const targetSeg = parseInt(document.querySelector('input[name="mission-seg"]:checked')?.value || segs.length, 10);

        // Collect selected card IDs
        const agentIds = Array.from(document.querySelectorAll('input[name="mission-agent"]:checked')).map(cb => cb.value);
        const techIds = Array.from(document.querySelectorAll('input[name="mission-tech"]:checked')).map(cb => cb.value);

        if (agentIds.length === 0) { btn.disabled = true; return; }

        // Build cumulative requirements for chosen segment
        const cumReqs = segs.slice(0, targetSeg).flatMap(s => s.requirements || []);

        // Build icon pool
        const pool = [];
        agentIds.forEach(aid => (this.catalog[aid]?.icons || []).forEach(ic => pool.push(ic)));
        techIds.forEach(tid => (this.catalog[tid]?.icons || []).forEach(ic => pool.push(ic)));
        if (me.backup_agent) {
            (this.catalog[me.backup_agent]?.icons || []).forEach(ic => pool.push(ic));
        }
        // Icons from completed ops (mission area)
        [...new Set(me.mission_area || [])].forEach(mid => (this.catalog[mid]?.icons || []).forEach(ic => pool.push(ic)));

        // Check requirements: satisfy specific icons first, then 'any' with remainder
        const remaining = [...pool];
        let ok = true;
        for (const req of cumReqs) {
            if (req === 'any') continue;
            const idx = remaining.indexOf(req);
            if (idx === -1) { ok = false; break; }
            remaining.splice(idx, 1);
        }
        const anyNeeded = cumReqs.filter(r => r === 'any').length;
        if (ok && remaining.length < anyNeeded) ok = false;

        btn.disabled = !ok;
    },

    submitMission(missionId) {
        const agentIds = Array.from(document.querySelectorAll('input[name="mission-agent"]:checked')).map(cb => cb.value);
        const techIds = Array.from(document.querySelectorAll('input[name="mission-tech"]:checked')).map(cb => cb.value);

        if (agentIds.length === 0) {
            this.showError('Select at least one agent.');
            return;
        }

        // Validate tech count against available slots
        let totalMaxTech = 0;
        agentIds.forEach(aid => {
            totalMaxTech += (this.catalog[aid].max_tech || 0);
        });
        const me = this.state.players[this.state.my_index];
        if (me.backup_agent) {
            totalMaxTech += (this.catalog[me.backup_agent].max_tech || 0);
        }
        if (techIds.length > totalMaxTech) {
            this.showError(`Too many tech cards (max ${totalMaxTech} for selected agents).`);
            return;
        }

        const targetSegment = parseInt(document.querySelector('input[name="mission-seg"]:checked')?.value || '3', 10);
        Actions.completeMission(missionId, agentIds, techIds, targetSegment);
        this.closeModal();
    },

    showDefenseDialog() {
        const s = this.state;
        const me = s.players[s.my_index];
        const attackerName = s.attack_attacker_name;
        const attackCard = s.attack_card;
        const isBurglary = attackCard === 'burglary';

        if (!this._defenseDialogShown) {
            playBell();
            this._defenseDialogShown = true;
        }

        // If burglary suffer mode is active, show card selection
        if (this._burglarySufferMode) {
            this._showBurglaryDiscardSelection();
            return;
        }

        const cardName = isBurglary ? 'Burglary' : 'Paperwork';
        let html = `<h3>${esc(attackerName)} played ${cardName}!</h3>`;

        if (isBurglary) {
            html += `<p>Defend or discard down to 3 cards.</p>`;
        } else {
            html += `<p>Defend or suffer a Red Tape.</p>`;
        }

        // Sentinel options (reveal, stays in hand)
        const sentinels = me.hand.filter(cid => this.catalog[cid] && (this.catalog[cid].defend));
        const uniqueSentinels = [...new Set(sentinels)];
        for (const cid of uniqueSentinels) {
            const c = this.catalog[cid];
            html += `<button class="btn-modal" style="background:#1a6a1a;border-color:#2d9a2d" onclick="Actions.defendAttack('sentinel', '${cid}')">
                Reveal ${esc(c.name)} (stays in hand)
            </button>`;
        }

        // Discard agent options (from hand)
        const agents = me.hand.filter(cid => this.catalog[cid] && this.catalog[cid].type === 'agent' && !(this.catalog[cid].defend));
        const eligibleAgents = isBurglary
            ? agents.filter(cid => {
                const icons = this.catalog[cid].icons || [];
                return icons.includes('muscle') || icons.includes('drive');
            })
            : agents;
        const uniqueAgents = [...new Set(eligibleAgents)];
        for (const cid of uniqueAgents) {
            const c = this.catalog[cid];
            html += `<button class="btn-modal" onclick="Actions.defendAttack('discard', '${cid}')">
                Discard ${esc(c.name)} (${this.getCardIcons(c)})
            </button>`;
        }

        // Suffer option
        if (isBurglary) {
            const mustDiscard = me.hand.length - 3;
            if (mustDiscard <= 0) {
                html += `<button class="btn-modal btn-cancel" onclick="Actions.defendAttack('suffer')">
                    Suffer (no effect — ${me.hand.length} cards in hand)
                </button>`;
            } else {
                html += `<button class="btn-modal btn-cancel" onclick="UI._startBurglarySuffer()">
                    Suffer (discard ${mustDiscard} card${mustDiscard !== 1 ? 's' : ''})
                </button>`;
            }
        } else {
            html += `<button class="btn-modal btn-cancel" onclick="Actions.defendAttack('suffer')">
                Suffer (gain Red Tape)
            </button>`;
        }

        this.showModal(html);
    },

    _startBurglarySuffer() {
        this._burglarySufferMode = true;
        this._burglarySelected = [];
        this._showBurglaryDiscardSelection();
    },

    _showBurglaryDiscardSelection() {
        const s = this.state;
        const me = s.players[s.my_index];
        const mustDiscard = me.hand.length - 3;
        const selected = this._burglarySelected || [];

        let html = `<h3>Burglary — Choose ${mustDiscard} card${mustDiscard !== 1 ? 's' : ''} to discard</h3>`;
        html += `<p>Selected: ${selected.length} / ${mustDiscard}</p>`;
        html += `<div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin:8px 0">`;

        for (let i = 0; i < me.hand.length; i++) {
            const cid = me.hand[i];
            const c = this.catalog[cid];
            if (!c) continue;
            const isSelected = selected.includes(i);
            const style = isSelected ? 'background:#8b0000;border-color:#ff4444' : '';
            html += `<button class="btn-modal" style="min-width:80px;${style}" onclick="UI._toggleBurglaryCard(${i})">
                ${esc(c.name)}${c.icons ? ' ' + this.getCardIcons(c) : ''}${c.value ? ' $' + c.value : ''}
            </button>`;
        }
        html += `</div>`;

        if (selected.length === mustDiscard) {
            html += `<button class="btn-modal" style="background:#8b0000;border-color:#ff4444;margin-top:8px" onclick="UI._confirmBurglarySuffer()">
                Confirm Discard
            </button>`;
        }

        html += `<button class="btn-modal" style="margin-top:4px" onclick="UI._cancelBurglarySuffer()">
            Back
        </button>`;

        this.showModal(html);
    },

    _toggleBurglaryCard(index) {
        const selected = this._burglarySelected || [];
        const pos = selected.indexOf(index);
        const me = this.state.players[this.state.my_index];
        const mustDiscard = me.hand.length - 3;
        if (pos >= 0) {
            selected.splice(pos, 1);
        } else if (selected.length < mustDiscard) {
            selected.push(index);
        }
        this._burglarySelected = selected;
        this._showBurglaryDiscardSelection();
    },

    _confirmBurglarySuffer() {
        const me = this.state.players[this.state.my_index];
        const discardCards = this._burglarySelected.map(i => me.hand[i]);
        this._burglarySufferMode = false;
        this._burglarySelected = [];
        Actions.defendAttack('suffer', '', discardCards);
    },

    _cancelBurglarySuffer() {
        this._burglarySufferMode = false;
        this._burglarySelected = [];
        this.showDefenseDialog();
    },

    showAttackWaiting() {
        const s = this.state;
        const remaining = s.attack_defenders_remaining;
        const cardName = s.attack_card === 'burglary' ? 'Burglary' : 'Paperwork';
        let html = `<h3>${cardName} Attack</h3>`;
        html += `<p>Waiting for ${remaining} player${remaining !== 1 ? 's' : ''} to defend...</p>`;
        this.showModal(html);
    },

    showModal(html) {
        document.getElementById('modal-content').innerHTML = html;
        document.getElementById('modal-overlay').style.display = 'flex';
    },

    closeModal() {
        if (this._preventModalClose) return;
        if (this.state && this.state.ended) return;
        if (this.state && this.state.attack_pending) return;
        document.getElementById('modal-overlay').style.display = 'none';
    },

    onRestockClick() {
        const s = this.state;
        if (!s || !s.is_my_turn) return;
        const me = s.players[s.my_index];
        if ((me.money + (me.gems || 0)) < 2 || s.market_deck_count === 0) return;
        const btn = document.getElementById('btn-restock');
        if (btn) btn.disabled = true;
        Actions.refreshMarket();
    },

    onEndTurnClick() {
        const btn = document.getElementById('btn-end-turn');
        if (btn && !btn.classList.contains('glow')) {
            const html = `<h3>End Turn?</h3>
                <p>You still have actions available. Are you sure you want to end your turn?</p>
                <div style="display:flex;gap:8px;margin-top:12px">
                    <button class="btn-modal btn-cancel" onclick="UI.closeModal()">Cancel</button>
                    <button class="btn-modal" onclick="UI.closeModal();Actions.endTurn()">End Turn</button>
                </div>`;
            this.showModal(html);
        } else {
            Actions.endTurn();
        }
    },

    confirmResign() {
        if (this.state && this.state.ended) {
            const roomId = this.state.room_id;
            if (roomId) {
                Actions.leaveRoom(roomId).finally(() => { location.href = 'index.php'; });
            } else {
                location.href = 'index.php';
            }
            return;
        }
        this.showConfirm(
            'Are you sure you want to resign? You will be removed from the game.',
            () => Actions.resign().then(() => { location.href = 'index.php'; }),
            null,
            'Resign', true
        );
    },

    showError(msg) {
        let toast = document.getElementById('ui-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'ui-toast';
            toast.className = 'ui-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.classList.add('visible');
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => toast.classList.remove('visible'), 3000);
    },

    showConfirm(msg, onConfirm, onCancel, confirmLabel = 'Confirm', destructive = false) {
        this._preventModalClose = true;
        this._onConfirm = onConfirm;
        this._onConfirmCancel = onCancel;
        const btnStyle = destructive
            ? 'background:#8b0000;border-color:#c00'
            : 'background:#1a8a2d;border-color:#2dbc45';
        let html = `<p style="font-size:15px;margin-bottom:16px">${esc(msg)}</p>`;
        html += `<button class="btn-modal" style="${btnStyle}" onclick="UI._doConfirm()">${esc(confirmLabel)}</button>`;
        html += `<button class="btn-modal btn-cancel" onclick="UI._doConfirmCancel()">Cancel</button>`;
        this.showModal(html);
    },

    _doConfirm() {
        this._preventModalClose = false;
        const cb = this._onConfirm;
        this._onConfirm = null;
        this._onConfirmCancel = null;
        this.closeModal();
        if (cb) cb();
    },

    _doConfirmCancel() {
        this._preventModalClose = false;
        const cb = this._onConfirmCancel;
        this._onConfirm = null;
        this._onConfirmCancel = null;
        if (cb) cb();
        else this.closeModal();
    },
};

function esc(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
