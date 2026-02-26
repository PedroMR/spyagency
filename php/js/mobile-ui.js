// mobile-ui.js — patches the desktop UI object for the mobile tab-based layout.
// Loaded after ui.js. game.js and actions.js continue to reference `UI` unchanged.

// ── Tab management ─────────────────────────────────────────────────────────────

UI._activeTab = 'hand';
UI._tabScrollPositions = {};

UI.switchTab = function(tab) {
    // Save scroll position of current tab before leaving
    const content = document.getElementById('mob-content');
    if (content) this._tabScrollPositions[this._activeTab] = content.scrollTop;

    // Hide all panels
    document.querySelectorAll('.mob-tab-panel').forEach(el => { el.style.display = 'none'; });
    // Show selected panel
    const panel = document.getElementById('tab-' + tab);
    if (panel) panel.style.display = 'flex';
    // Update tab button states
    document.querySelectorAll('.mob-tab-btn').forEach(btn => {
        const isActive = btn.dataset.tab === tab;
        btn.classList.toggle('active', isActive);
        if (isActive) btn.classList.remove('has-notification');
    });
    this._activeTab = tab;

    // Restore saved scroll position for this tab
    if (content) content.scrollTop = this._tabScrollPositions[tab] ?? 0;
};

UI._notifyTab = function(tab) {
    const btn = document.querySelector(`.mob-tab-btn[data-tab="${tab}"]`);
    if (btn && !btn.classList.contains('active')) {
        btn.classList.add('has-notification');
    }
};

// ── Wrap Actions.endTurn — switch to Log tab on end turn ─────────────────────

const _desktopEndTurn = Actions.endTurn.bind(Actions);
Actions.endTurn = function() {
    _desktopEndTurn();
    UI.switchTab('log');
    // renderLog() will scroll-to-bottom when the updated state arrives
};

// ── Skip FLIP animations (hidden tabs have zero rects) ────────────────────────

UI._applyFLIPFrom = function() { /* no-op on mobile */ };

// ── Override: update() ────────────────────────────────────────────────────────

const _desktopUpdate = UI.update.bind(UI);

UI.update = function(state) {
    _desktopUpdate(state);

    // Notification dots for background tabs that received updates
    if (this._activeTab !== 'market') this._notifyTab('market');
    if (this._activeTab !== 'ops')    this._notifyTab('ops');
};

// ── Override: renderTurnInfo() — add mob-has-banner handling ─────────────────

const _desktopRenderTurnInfo = UI.renderTurnInfo.bind(UI);

UI.renderTurnInfo = function() {
    _desktopRenderTurnInfo();
    const content = document.getElementById('mob-content');
    if (content) {
        content.classList.toggle('mob-has-banner', !!(this.state && this.state.final_round));
    }
};

// ── Override: renderMissionArea() — tighter splay for small screens ───────────

UI.renderMissionArea = function() {
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

    // Tighter splay values for mobile
    const STRIP = 18;
    const CARD_H_TOP = 42;
    const CARD_H_REST = 24;
    const N = missionArea.length;

    const positions = new Array(N);
    positions[N - 1] = 0;
    let prevBottom = CARD_H_TOP;
    for (let i = N - 2; i >= 0; i--) {
        positions[i] = prevBottom + STRIP - CARD_H_REST;
        prevBottom = positions[i] + CARD_H_REST;
    }

    let totalIncome = 0;
    let totalExtraMissions = 0;
    let totalExtraBuys = 0;

    const html = missionArea.map((mid, i) => {
        const card = this.catalog[mid];
        if (!card) return '';
        const income = card.value || 0;
        totalIncome += income;
        if (card.extra_mission) totalExtraMissions++;
        if (card.extra_buy) totalExtraBuys++;
        const isTop = i === N - 1;
        const stars = card.stars ? `${card.stars}⭐ ` : '';
        const rewardParts = [];
        if (income > 0) rewardParts.push(`$${income}/turn`);
        if (card.extra_mission) rewardParts.push('+1 op/turn');
        if (card.extra_buy) rewardParts.push('+1 buy/turn');
        const reward = (stars || rewardParts.length > 0) ? `${stars}${rewardParts.join(' ')}` : '';
        return `<div class="card played-card" style="--card-color:${this.getCardColor(card)};position:absolute;top:${positions[i]}px;z-index:${i + 1}">
            ${isTop ? `<div class="card-name">${esc(card.name)}</div>` : ''}
            ${reward ? `<div class="card-reward">${reward}</div>` : ''}
        </div>`;
    }).join('');

    container.style.position = 'relative';
    container.style.height = prevBottom + 'px';
    container.innerHTML = html;

    const summaryParts = [];
    if (totalIncome > 0) summaryParts.push(`$${totalIncome}/turn`);
    if (totalExtraMissions > 0) summaryParts.push(`+${totalExtraMissions} op/turn`);
    if (totalExtraBuys > 0) summaryParts.push(`+${totalExtraBuys} buy/turn`);
    incomeDisplay.textContent = summaryParts.join(' ');
    incomeDisplay.style.display = summaryParts.length > 0 ? 'inline' : 'none';
};

// ── Override: renderGameOver() — mobile redirect paths ───────────────────────

UI.renderGameOver = function() {
    if (!this.state.ended) return;

    // Rematch: redirect to mobile game page
    if (this.state.rematch_game_id) {
        const token = sessionStorage.getItem('spy_token');
        sessionStorage.setItem('spy_game_id', this.state.rematch_game_id);
        window.location.href = 'game.php?game_id=' + encodeURIComponent(this.state.rematch_game_id)
            + '&token=' + encodeURIComponent(token);
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
    const starsTied = scores.filter(sc => sc.stars === topStars).length > 1;
    const sharedVictory = starsTied && scores.filter(sc => sc.stars === topStars && (sc.missions ?? 0) === topMissions).length > 1;

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
    html += '<button onclick="location.href=\'../index.php\'">Back to Lobby</button>';
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
};

// ── Override: confirmResign() — navigate to parent lobby ──────────────────────

UI.confirmResign = function() {
    if (this.state && this.state.ended) {
        const roomId = this.state.room_id;
        if (roomId) {
            Actions.leaveRoom(roomId).finally(() => { location.href = '../index.php'; });
        } else {
            location.href = '../index.php';
        }
        return;
    }
    if (confirm('Are you sure you want to resign? You will be removed from the game.')) {
        Actions.resign().then(() => {
            location.href = '../index.php';
        });
    }
};

// MobileUI is the patched UI object — aliased here for clarity.
const MobileUI = UI;
