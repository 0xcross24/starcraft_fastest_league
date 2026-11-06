# Live rankings message in Discord

One message in the rankings channel shows the active season's top 5 per
format. The site edits that message; it never posts a new one per change.
The code is in `App\Services\DiscordRankings`.

- **When it updates:** right after an upload, an invalidation, a re-rate or a
  main-name change, and every 5 minutes from the scheduler as a backup. It only
  sends an edit when the standings changed.
- **If someone deletes the message:** the next update posts a new one.
- **Commands:** `php artisan rankings:discord` (sync now), `--force` (edit even
  if nothing changed), `--preview` (print the message, send nothing).

## Step 1: webhook only (2v2 and 3v3 shown together)

Prod `.env`:

```
DISCORD_RANKINGS_WEBHOOK_URL=https://discord.com/api/webhooks/...
```

A channel webhook can't have clickable buttons, so the message shows both
formats, plus link buttons to the full rankings.

## Step 2: SFL Rankings app (one format, 2v2 / 3v3 buttons)

Buttons need a Discord application. Laravel calls the app's REST API with its
bot token, and Discord sends button clicks to the site. No bot process runs.

1. Go to https://discord.com/developers/applications → **New Application**,
   name it `SFL Rankings`.
2. **Bot** tab: click **Reset Token** and copy the token. Turn **Public Bot**
   off.
3. **OAuth2 → URL Generator**: pick scope `bot` and the permissions View
   Channel, Send Messages, Embed Links and Read Message History. Open the URL
   and add the app to the server. Make sure it can post in the rankings channel.
4. Prod `.env`:

   ```
   DISCORD_RANKINGS_CHANNEL_ID=1568338215314661488
   DISCORD_RANKINGS_BOT_TOKEN=<token from step 2>
   DISCORD_RANKINGS_PUBLIC_KEY=<General Information → Public Key>
   ```

5. **General Information → Interactions Endpoint URL**:
   `https://starcraftfastest.us/api/discord/interactions`. Discord tests the URL
   when you save, so the `.env` values must already be set.

At the next update, the site deletes the webhook's message and the app posts
the one with buttons. A click flips the message for everyone, and later
updates keep the format that's showing.

## Ladder badges

Each player is shown as `#1 [badge] Name — points · W L`, with the grade as an
iCCup-style badge emoji. The images are in `docs/discord-badges/`
(`sfl_S.png` … `sfl_D_minus.png`, `sfl_E.png`).

1. Server Settings → Emoji → upload all 14 images. Keep the file names as the
   emoji names.
2. In any channel, send
   `\:sfl_S: \:sfl_A_plus: \:sfl_A: \:sfl_A_minus: \:sfl_B_plus: \:sfl_B: \:sfl_B_minus: \:sfl_C_plus: \:sfl_C: \:sfl_C_minus: \:sfl_D_plus: \:sfl_D: \:sfl_D_minus: \:sfl_E:`.
   Discord shows the emojis' codes (`<:sfl_S:123…>`) instead of the images.
3. Prod `.env`: `DISCORD_RANKINGS_EMOJIS="<paste that message>"`. The next
   update (within 5 minutes) switches to the badges. Until then, grades are
   shown as text.
