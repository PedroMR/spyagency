document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const gameId = params.get('game_id') || sessionStorage.getItem('spy_game_id');
    const token = params.get('token') || sessionStorage.getItem('spy_token');

    if (!gameId || !token) {
        alert('No active game. Returning to lobby.');
        location.href = 'index.php';
        return;
    }

    // Persist for any in-page use
    sessionStorage.setItem('spy_game_id', gameId);
    sessionStorage.setItem('spy_token', token);

    Actions.init(gameId, token);
    Poller.init(gameId, token, (state) => {
        UI.update(state);
    });
});
