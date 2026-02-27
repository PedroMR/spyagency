## Implementation Details

See the card list in the "cards.csv" file.

I should be able to spend gems implicitly, without needing a "Cash Gems" button. This should happen automatically when I try to buy something I don't have $ for. The remaining balance should be paid for with the necessary gems.  

The UI should show an area with my discard pile, showing the number of cards and which card is on top.
The maraketplace UI should highlight which cards you have enough money to purchase.

When starting the game, randomize which player will go first. Also shuffle the position of all players.

Every time the user clicks a button we should hear a discreet "click" sound.

The Shadow and Muscle buttons should look like the cards you get when you buy them. When clicking the Shadow or Muscle button the user should see a confirmation of the purchase. Purchased Muscles or Shadows this way go to the player's Hand Area and so can be used immediately.

At the top of the screen we should see the number of missions available to attempt this turn and the number of buys available this turn.

Mission cards are purple.
Plot cards are cyan.
Money cards are green.
Tech cards are blue.
Hazard cards are red.
Agent cards are yellow.

After we buy a card from the Market there's no point showing the price of that card anymore. We should show the reward for Mission cards we've bought.

Cards in the Market that we can buy (with $ and gems) should be highlighted. Cards that we cannot afford should be lowlighted. All cards available for purchase should be ordered in ascending order of cost. While it's not my turn they should all be lowlighted. 

The UI should show which round we're playing, starting at 1 and going up every time it is the initial player's turn.

The number of stars a player shows in the UI is the number of stars in all the cards they own (hand, deck, discard area, in play). This goes up as they accomplish missions with stars and goes down if they trash one of those cards.

When it's my turn I want all the Money cards in my hand and all the Missions which only reward points and money to be automatically played. This should happen after a small delay (0.8 seconds).

If there is nothing more I can do in my turn (no useful cards to play, no cards to buy, no missions to complete) the End Turn button should glow in an animated way.

The player list should be smaller and to the right of the mission list.

When a game is over we should get two buttons -- one to go Back to Lobby (we already have this) and a checkbox that says "Rematch?". If all the people left in the room have the Rematch? checkbox enabled we should clear the room and start a new game in the same room with those people. If there's only one person left in the room the checkbox should be disabled (greyed out).