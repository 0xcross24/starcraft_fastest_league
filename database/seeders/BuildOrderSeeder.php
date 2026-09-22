<?php

namespace Database\Seeders;

use App\Models\BuildOrder;
use Illuminate\Database\Seeder;

class BuildOrderSeeder extends Seeder
{
    /**
     * Runs on every deploy, so it has to be safe to repeat.
     *
     * It owns only the rows carrying a seed_key. Builds created by hand in the
     * admin UI have a null seed_key and are never touched.
     *
     * Within the rows it owns:
     *  - a build that is missing is created
     *  - a build still matching the content the seeder last wrote is updated,
     *    so corrections made here reach the site
     *  - a build whose content has diverged was edited in the admin UI, and is
     *    left alone
     *  - a build that was deleted stays deleted, because soft deletes leave a
     *    row behind for this to find
     *  - a build removed from builds() below is deleted
     */
    public function run(): void
    {
        $builds = $this->builds();
        $written = [];

        foreach ($builds as $build) {
            $fields = $this->fieldsOf($build);

            $existing = BuildOrder::withTrashed()
                ->where('seed_key', $build['seed_key'])
                ->first();

            if (! $existing) {
                BuildOrder::create($fields + [
                    'seed_key' => $build['seed_key'],
                    'seed_hash' => self::hashFor($build),
                ]);
                $written[] = $build['seed_key'];
                continue;
            }

            if ($existing->trashed()) {
                continue;
            }

            // A null hash means the row was seeded before hashes were
            // recorded. Adopt it rather than reading it as edited, otherwise
            // those builds could never be corrected again.
            if ($existing->seed_hash !== null && $existing->seed_hash !== $this->hashOf($existing)) {
                continue;
            }

            $existing->update($fields + ['seed_hash' => self::hashFor($build)]);
            $written[] = $build['seed_key'];
        }

        // Parents are linked in a second pass so a build may be listed before
        // the one it continues from. Only rows this run wrote are touched, so
        // a build re-parented in the admin UI keeps that parent.
        $this->linkParents($builds, $written);

        BuildOrder::whereNotNull('seed_key')
            ->whereNotIn('seed_key', array_column($builds, 'seed_key'))
            ->delete();
    }

    private function linkParents(array $builds, array $written): void
    {
        if ($written === []) {
            return;
        }

        $idsByKey = BuildOrder::whereNotNull('seed_key')->pluck('id', 'seed_key');

        foreach ($builds as $build) {
            if (! in_array($build['seed_key'], $written, true)) {
                continue;
            }

            $parentKey = $build['parent_seed_key'] ?? null;

            BuildOrder::where('seed_key', $build['seed_key'])->update([
                'parent_id' => $parentKey === null ? null : ($idsByKey[$parentKey] ?? null),
            ]);
        }
    }

    /**
     * The columns the seeder writes, separated from its own bookkeeping keys.
     */
    private function fieldsOf(array $build): array
    {
        return [
            'title' => $build['title'],
            'race' => $build['race'],
            'matchup' => $build['matchup'],
            'description' => $build['description'] ?? null,
            'steps' => $build['steps'],
            'youtube_url' => $build['youtube_url'] ?? null,
            'phase' => $build['phase'] ?? null,
            'position' => $build['position'] ?? 0,
        ];
    }

    /**
     * Fingerprint of the fields the seeder manages.
     */
    public static function hashFor(array $build): string
    {
        return hash('sha256', json_encode([
            $build['title'],
            $build['race'],
            $build['matchup'],
            $build['description'] ?? null,
            $build['steps'],
            $build['youtube_url'] ?? null,
            $build['phase'] ?? null,
            $build['position'] ?? 0,
            $build['parent_seed_key'] ?? null,
        ]));
    }

    private function hashOf(BuildOrder $build): string
    {
        return self::hashFor([
            'title' => $build->title,
            'race' => $build->race,
            'matchup' => $build->matchup,
            'description' => $build->description,
            'steps' => $build->steps,
            'youtube_url' => $build->youtube_url,
            'phase' => $build->phase,
            'position' => $build->position,
            // Re-parenting in the admin UI changes this, which is what makes
            // such a build read as edited and stops the seeder overwriting it.
            'parent_seed_key' => $build->parent?->seed_key,
        ]);
    }

    protected function builds(): array
    {
        return [
            [
                'seed_key' => 'protoss-storm-drop-pub',
                'title' => 'Protoss Storm Drop Build',
                'race' => 'Protoss',
                'matchup' => ['PUB'],
                'description' => '3 gate forge Storm Drop build',
                'youtube_url' => 'https://youtu.be/uJ_-qEyE3es',
                'steps' => <<<'STEPS'
                    7 Pylon
                    9 Gateway
                    11 Gateway
                    11 Gateway
                    12 Pylon + Zealot
                    15 Forge
                    23 Pylon + Cannon
                    31 Gas
                    31 Gas

                    ** TIP **
                    Every time you make 3 Zealots, make 1 Pylon

                    100% Gas - Cybernetics Core
                    100% Core - Robo + Citadel of Adun + 3rd Gas
                    100% Robo - Robotics Bay + Shuttle
                    100% Adun - Templar Archive

                    Start Shuttle speed and Storm + Stargate + 4th Gas
                    Make 1 Reaver and 3 DTs after

                    Around ~5:00 you want to have your choke blocked with 2-3 Cannons + 4th Gateway
                    STEPS,
            ],
            [
                'seed_key' => 'terran-3rax-15gas-academy',
                'title' => '3 Rax 15 Gas Academy',
                'race' => 'Terran',
                'matchup' => ['PUB'],
                'phase' => 'Opener',
                'description' => 'Three barracks into gas and Academy',
                'steps' => <<<'STEPS'
                    8 Depot
                    9 Barracks
                    10 Barracks
                    12 Barracks
                    14 Depot
                    15 Gas

                    100% Gas - Academy
                    100% Academy - 3 medics
                    STEPS,
            ],
            [
                'seed_key' => 'terran-6rax-mass-bio',
                'parent_seed_key' => 'terran-3rax-15gas-academy',
                'title' => '6 Rax Mass Bio',
                'race' => 'Terran',
                'matchup' => ['PUB'],
                'description' => 'Fight for mid and keep zergs in check.',
                'phase' => 'Mid game',
                'steps' => <<<'STEPS'
                    4 min 3 rax (1 coming out of cc 1 pull from mineral line 1 take from depots)
                    4:10 ebay
                    keep 1 scv building depots
                    5:30 cc
                    5:45 cc
                    6:30 cc + 1 fact
                    2 more gas
                    3 more facts
                    1 more gas

                    Notes:
                    - Stay on 4 fact if you are going to keep making marines, otherwise cut marine production and work up to 8 facts as a natural transition or 6 fact 3 starports in which case you can add the starports before the additional 2 facts so you can build up dropship count. You can always backfill your dropships with marines if you need additional units/anti-air.
                    - 2 making depots again after your extra 3 rax go down.
                    - Anti air is light because tech is slow so make sure to use marines/tanks to defend main. Use your army + scans to figure out the enemy's plan so you can be in position
                    - Watch out for DTs + Lurkers, you have a late CC so scan is very late. So many players will try to defend themselves with these cloaked units, get an allie to bring detection or bring an scv or 2 to turret to confirm your kill if you need to.

                    Goals:
                    - Use your initial bio army to:
                      - Contest middle.
                      - Keep zergs honest, not enough sunks? They die.
                      - Help your allies!
                    - Make alot of cc's as outlined in the build so you can catch up with late game since you traded economy and tech for alot of early units.
                    STEPS,
            ],
            [
                'seed_key' => 'terran-fast-cc-transition-mech',
                'parent_seed_key' => 'terran-3rax-15gas-academy',
                'title' => 'Fast CC Mech Transition',
                'description' => 'Fast transition into mech from bio.',
                'race' => 'Terran',
                'matchup' => ['PUB'],
                'phase' => 'Mid game',
                'position' => 1,
                'steps' => <<<'STEPS'
                    (right before this pause the marine cycle to make sure you have cash for the cc and stim then resume)
                    3:40 cc (scv who was making academy)
                    4:00 ebay
                    factory
                    4:15 2nd gas
                    4:20 range
                    4:40 put in 2nd gas
                    4:50 3rd gas (use scv who built cc)
                    5:00 turret at choke + main
                    5:10 starport
                    5:15 armory
                    5:20 fact/fact
                    6:00 4th gas
                    6:30 3 more factories (or 2 more factories and a starport)

                    When the 3 factories finish either add 2 more factories (for pushing straight through mid) or 2 more starports (for dropship play) and add 1 science facility so you can keep following up on your early tank upgrade.

                    8:00 3rd cc
                    8:00 gas 5-6

                    9 gas 7-8

                    10:00 4th cc

                    Note:
                    - This is a Greedy build, if you want to be even greedier you can skip scouting with this build which speeds it up to a 3:15 cc and it plays even better. However... DON'T DIE!
                    - keep only 2 workers on gas until you make your cc, then add the 3rd as soon as you throw a cc up. For all other gases in this build fill them as soon as they finish.
                    - Halt marine production as soon as you can, it lets you speed up meching asap. Better to make a few bunkers and less marines then to spam marines. If you end up making more marines slow down the factory timings.
                    - 58 supply stop making depots and let your cc take you up to 68
                    - Do not get siege immediately when your factory finishes. You don't have an early push timing/goal with this build so wait until factory 2-3 are building first. You do however want to start making a tank as soon as possible since your goal IS to get a high tank count fast.
                    - We get a starport immediately after our factory finishes for 2 reasons. One is we can tank drop at around 7min if we want. The other is we are building dropship count off this starport for a 9 minute massive drop or cliff tanks to attack an enemy next to us. If you think you want to take middle instead you can delay the starport a bunch instead and get faster factories. But if no team mates help you or you can't take mid you are pretty screwed.
                    - When you add the tripple fact at 6:30 I'm a fan of making one round of goliaths with those 3 factories. They are cheaper than tanks so it gives the build some room to breathe while still producing units. Feel free to ignore my advice and just get your addons right away.
                  
                    Goals: 
                    - Your first goal is just to not die, change the build in anyway necessary if you get targetted. Your cutting alot of corners with this build, so it's great if everyone is sitting in their base doing nothing or your allie is the one being targetted. But once the attention turns on you you'll need bunkers and possibly more marines.
                    - Your second goal is to retain units. Along the same lines as above, your real goal is to switch into mech fast, that means we want to stop making bio as soon as we can. You also stay on 3 rax so you can't replenish units as fast as a 5 or 6 rax. So take extra care not to throw your units away or you'll slow down your build. You will have a pretty normal looking early game army, which is great but replenishing that army is the issue. So choose your fights wisely.
                    - This build works best with drops. So if there is a toss next to you that you want to ledge and then dropship tanks over to, that's one of the best use cases of this build.
                    - 9:30 you have 7 dropships. You can mass drop, you have about 14 tanks, and possibly a bio force + any goliaths you made. You can wait for a bigger doom drop if you want. So that's another way to play this build.
                    - If your team is looking strong on units you can always not make dropships and just focus on moving ground force into the middle and pushing straight through. 
                    STEPS,
            ],
        ];
    }
}
