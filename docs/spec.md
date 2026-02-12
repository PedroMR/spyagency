# Spy Agency

Spy Agency is a competitive card game for 2-4 players where players each have their own deck.

There are different card types: Bases, Agents, Missions, Plots and Tech.

The Market deck is formed of all the Agents, Plots and Tech cards.

The Mission decks are composed of the Mission cards, sorted by tier (1, 2 and 3).

Bases are not shuffled.

There is a marketplace for the Market Deck. 7 cards are offered openly for players. At the end of a player turn the marketplace is restocked by filling empty slots with cards from the deck. The active player may restock the marketplace by paying $2, which moves all the cards in offer to the bottom of the Market Deck and refills the marketplace immediately.

There is a Mission Grid on offer for players to complete. 3 from each Mission Deck.

<!--Each player starts with 2 Bases (Safehouses) in front of them. They can acquire more bases (Hideaways) with money. The first extra Hideaway costs $5, the next $8, the next $12.-->

Each player starts with a deck consisting of 6 $1 cards, 2 Muscles, 1 Shadow, and 1 Red Tape cards.

Red Tape does nothing.

Each player starts by drawing 5 cards.

## A player's turn

When it's their turn, a player may play all the cards in their hand in the order of their choosing.

During a player's turn they may complete 1 mission. After that the player may buy 1 card from the marketplace. Some cards may increase these limits.

<!--
Agent cards can only be played over a free Base card. Each Base card may only hold one Agent. A player may vacate a Base at any time by discarding the Agent that is there and any cards equipped by that Agent.

Tech cards may be equipped to active Agents (in a base). There is a limit to how many Tech cards an Agent may hold at one time. This limit changes according to the Agent in question.
-->
Agents are played from hand in order to complete a mission. Tech cards can be played with an Agent, but only up to the limit of how many that Agent can hold. This limit changes according to the Agent in question.

Plot cards may be played from hand at any time and discarded as soon as their effects have been resolved.

To complete a Mission the player plays one or more Agents with Tech. The number of icons in the Agent and all of their Tech needs to match or exceed the requirement of the Mission. The Mission card itself is added to the player's hand -- it is worth the effect indicated. Any cards (Tech or Agent) that offer a choice should be chosen automatically in order to complete the mission, if that's possible.

Some Missions and Plots will reward the Player with gems (💎). Gems are kept between turns, and may be cashed in for the same amount in money ($). 1 gem = 1 money. This should be done "just in time", at moment of purchase. The game can ask the player if they're willing to spend X gems to purchase the thing, and allow them to cancel.

"Heist" is a special mission. It immediately rewards the player with gems, but is not added to their deck. Heist is always available. If the agent(s) committed to it feature 1 or 2 icons, Heist rewards 1 gem. If 3 or 4 icons, two gems, and if 5 or more icons, three gems are rewarded. Any more icons bear no extra reward. The gem rewards for Heist should be visible in a table when that mission is selected.

To buy a card the player spends the required amount of money ($) and takes the card, adding it to their discard pile. The space left by the purchased card is only refilled at the end of the turn.


### End of player turn

After the player is done playing their cards the Mission Grid and the Market are refilled and the turn of the player to their left begins. When refilling the Market, if a card drawn to refill it matches a card already in it, place the new card on top of the existing stack and draw again. The same procedure applies to the Mission grid, stacking cards if they are the same. <!--Note that Agents in Bases who were not deployed to complete a mission are not discarded; they remain in the player's play area, as well as any Tech equipped onto them. -->

The player then discards their whole hand and draws 5 cards. If there are no cards left in the deck the player shuffles their discard pile to make a new deck and resumes drawing.

## Game End

The game is over at the end of the round where either of two things have happened:
1) Any of the Mission Decks are empty;
2) The Market Deck is empty.

At the end of the game, players gather the cards in their deck, discard, play and hand areas. The player with the most stars appearing in their cards wins the game.

## Implementation Details

See the card list in the "cards.csv" file.

I should be able to spend gems implicitly, without needing a "Cash Gems" button. This should happen automatically when I try to buy something I don't have $ for. The remaining balance should be paid for with the necessary gems.  

The UI should show an area with my discard pile, showing the number of cards and which card is on top.
The maraketplace UI should highlight which cards you have enough money to purchase.

When starting the game, randomize which player will go first.

Every time the user clicks a button we should hear a discreet "click" sound.

The Shadow and Muscle buttons should look like the cards you get when you buy them. When clicking the Shadow or Muscle button the user should see a confirmation of the purchase.

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