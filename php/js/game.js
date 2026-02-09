document.addEventListener('DOMContentLoaded', () => {
    const gameId = sessionStorage.getItem('spy_game_id');
    const token = sessionStorage.getItem('spy_token');

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
