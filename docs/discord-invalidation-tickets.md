# Replay invalidation tickets on Discord

Players in a game can click **Request invalidation** under it (on /replays and
on player pages), pick a reason and write a justification. The request is
saved on the site and posted to Discord as a ticket: one **forum post per
game** in a forum channel. If another player in the same game also asks, their
request is added as a reply in the same post. When an admin approves or
rejects it in **/admin/requests**, the decision (and optional note) is posted
in the post too.

The site is the record. If Discord is down or not configured, the request is
still saved and shows in /admin/requests marked "not posted to Discord".

## One-time Discord setup (server admin, ~5 minutes)

1. **Category** – use (or create) the `starcraftfastest.us` category.
2. **Forum channel** – in that category: *Create Channel → Forum*, name it
   `replay-tickets`.
   - Permissions: deny `@everyone` *View Channel*; allow your admin/moderator
     role *View Channel*, *Send Messages in Posts*, *Manage Posts*. Players'
     justifications can be about other players, so keep it staff-only.
   - Optional: add tags `Approved` / `Rejected` (Forum settings → Tags) and
     apply them by hand when closing a post. Webhooks cannot change tags.
3. **Webhook** – channel *Edit Channel → Integrations → Webhooks → New
   Webhook*, name it `SFL Tickets`, *Copy Webhook URL*.
4. **Role ping (optional)** – *Server Settings → Roles*, right-click your
   admin role → *Copy Role ID* (needs Developer Mode in User Settings →
   Advanced).

## Server configuration

Add to the prod `.env` (keep the URL out of chat and git; it lets anyone post
to the channel):

```
DISCORD_TICKETS_WEBHOOK_URL="https://discord.com/api/webhooks/…"
DISCORD_TICKETS_ROLE_ID=123456789012345678   # optional
```

Then `docker compose -f docker-compose.prod.yml exec app php artisan config:clear`
(or redeploy). Test by requesting invalidation of one of your own games and
rejecting it in /admin/requests.

## Closing a ticket

Approve/Reject in /admin/requests (not on Discord): that invalidates the game
(reversing ELO and W/L like the Replays tab) and closes every open request for
it. Then close or lock the Discord post if you like.

## Later: private per-player ticket channels

A webhook can only post. If players should see and reply in their own ticket,
that needs a Discord bot (Manage Channels) that creates a private channel per
ticket, plus a way to link site accounts to Discord user IDs. The request
model and admin flow here stay the same; only `App\Services\DiscordTickets`
would change.
