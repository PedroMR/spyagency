// mobile-ui.js — patches the desktop UI object for the mobile tab-based layout.
// Loaded after ui.js. game.js and actions.js continue to reference `UI` unchanged.

// ── Tab management ─────────────────────────────────────────────────────────────

UI._activeTab = 'hand';

UI.switchTab = function(tab, animate = true) {
    const idx = _TAB_ORDER.indexOf(tab);
    if (idx === -1) return;

    // Slide the track to the target panel
    const track = document.getElementById('mob-tabs-track');
    if (track) {
        track.style.transition = animate
            ? 'transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)'
            : 'none';
        track.style.transform = `translateX(-${idx * 25}%)`;
    }

    // Update tab button states
    document.querySelectorAll('.mob-tab-btn').forEach(btn => {
        const isActive = btn.dataset.tab === tab;
        btn.classList.toggle('active', isActive);
        if (isActive) btn.classList.remove('has-notification');
    });

    this._activeTab = tab;
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
    // Scroll to bottom immediately, and again when the new state arrives via renderLog()
    const logPanel = document.getElementById('tab-log');
    if (logPanel) logPanel.scrollTop = logPanel.scrollHeight;
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
    this.showConfirm(
        'Are you sure you want to resign? You will be removed from the game.',
        () => Actions.resign().then(() => { location.href = '../index.php'; }),
        null,
        'Resign', true
    );
};

// ── Swipe left/right to switch tabs (with live drag tracking) ────────────────

const _TAB_ORDER = ['hand', 'market', 'ops', 'log'];

// Override renderLog so the Log panel scrolls to bottom (not the inner #game-log)
const _desktopRenderLog = UI.renderLog.bind(UI);
UI.renderLog = function() {
    _desktopRenderLog();
    const logPanel = document.getElementById('tab-log');
    if (logPanel) logPanel.scrollTop = logPanel.scrollHeight;
};

document.addEventListener('DOMContentLoaded', () => {
    const content = document.getElementById('mob-content');
    const track  = document.getElementById('mob-tabs-track');
    if (!content || !track) return;

    let touchStartX = 0;
    let touchStartY = 0;
    let startOffset = 0;      // track translateX at touch start (px)
    let isDragging  = false;  // true once we've committed to a horizontal drag
    let axisLocked  = false;  // true once we've decided vertical vs horizontal

    function panelWidth() {
        return content.offsetWidth;
    }

    function offsetForTab(tab) {
        return -_TAB_ORDER.indexOf(tab) * panelWidth();
    }

    content.addEventListener('touchstart', e => {
        touchStartX  = e.touches[0].clientX;
        touchStartY  = e.touches[0].clientY;
        startOffset  = offsetForTab(UI._activeTab);
        isDragging   = false;
        axisLocked   = false;
        track.style.transition = 'none';
    }, { passive: true });

    content.addEventListener('touchmove', e => {
        const dx = e.touches[0].clientX - touchStartX;
        const dy = e.touches[0].clientY - touchStartY;

        if (!axisLocked) {
            if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return; // too small to decide
            axisLocked = true;
            isDragging = Math.abs(dx) > Math.abs(dy);
        }

        if (!isDragging) return; // vertical — let the panel scroll naturally

        e.preventDefault();

        const current  = _TAB_ORDER.indexOf(UI._activeTab);
        const minX = -((_TAB_ORDER.length - 1) * panelWidth());
        const maxX = 0;

        // Add slight resistance at the edges
        let newX = startOffset + dx;
        if (newX > maxX) newX = maxX + (newX - maxX) * 0.25;
        if (newX < minX) newX = minX + (newX - minX) * 0.25;

        track.style.transform = `translateX(${newX}px)`;
    }, { passive: false });

    content.addEventListener('touchend', e => {
        if (!isDragging) return;
        isDragging = false;

        const dx = e.changedTouches[0].clientX - touchStartX;
        const current = _TAB_ORDER.indexOf(UI._activeTab);
        let target = current;

        if (dx < -40 && current < _TAB_ORDER.length - 1) target = current + 1;
        else if (dx > 40 && current > 0)                  target = current - 1;

        UI.switchTab(_TAB_ORDER[target]);
    }, { passive: true });
});

// MobileUI is the patched UI object — aliased here for clarity.
const MobileUI = UI;
