document.addEventListener('DOMContentLoaded', () => {
    const gameId = localStorage.getItem('spy_game_id');
    const token = localStorage.getItem('spy_token');

    if (!gameId || !token) {
        alert('No active game. Returning to lobby.');
        location.href = 'index.php';
        return;
    }

    Actions.init(gameId, token);
    Poller.init(gameId, token, (state) => {
        UI.update(state);
    });
});
