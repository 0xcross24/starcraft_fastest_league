<x-app-layout title="About SFL - StarCraft Fastest League"
    description="What the StarCraft Fastest League is, how team Fastest is played, how SFL ratings, grades and seasons work, and who runs the ladder.">
    <x-content-page heading="About the StarCraft Fastest League">
        <p>
            The StarCraft Fastest League (SFL) is a community ladder for team games of
            StarCraft: Brood War played on Fastest maps. Players register their in-game
            names, upload the replays of their 2v2 and 3v3 games, and the site turns those
            replays into season rankings, player profiles and a shared library of games
            anyone can download and learn from.
        </p>

        <h2>What is Fastest?</h2>
        <p>
            "Fastest" maps are a classic Brood War custom map style built for quick,
            action-heavy games. Every base starts with far more minerals and gas than a
            normal melee map, so economies are maxed out within minutes and the game is
            decided by build orders, army control and how well a team coordinates its
            attacks rather than by long expansion races. What works depends heavily on
            your team's races, so the builds on this site are tagged by team composition,
            such as PT or PZ in 2v2 and PPT or PPZ in 3v3.
        </p>
        <p>
            SFL games are played on the league's own maps (names starting with
            <code>OP SFL-</code> or <code>SFLClan</code>), which registered players can
            download from their profile page. The full set of gameplay, league and upload
            rules is on the <a href="{{ url('/rules') }}">Rules</a> page.
        </p>

        <h2>How ratings work</h2>
        <p>
            Every account starts each season at <strong>1000 points</strong> per format
            (2v2 and 3v3 are rated separately). Points for a game follow the iCCup points
            table: what you gain for a win or lose for a loss depends on your own grade and
            the grade of the team you played against. In team games your opponent is the
            average rating of the other team.
        </p>
        <ul>
            <li>Beating players of the same grade is worth +100; losing to them costs between 50 and 100 depending on the grade.</li>
            <li>Underdogs gain more and risk less: a low-graded player can earn up to +200 for beating a much stronger team and lose nothing for losing to it, while a top player gains as little as +10 for beating a much weaker team and can lose up to 200 for losing to it.</li>
            <li>The table is not zero-sum, so the total number of points on the ladder grows as more games are played.</li>
            <li>Ratings never drop below 0.</li>
        </ul>

        <h3>Grades</h3>
        <p>Your rating maps to a letter grade shown next to your name across the site:</p>
        <table>
            <thead><tr><th>Grade</th><th>Rating from</th></tr></thead>
            <tbody>
                <tr><td><x-elo-grade :elo="6500" /></td><td>6500</td></tr>
                <tr><td><x-elo-grade :elo="6000" /> / <x-elo-grade :elo="5500" /> / <x-elo-grade :elo="5000" /></td><td>6000 / 5500 / 5000</td></tr>
                <tr><td><x-elo-grade :elo="4500" /> / <x-elo-grade :elo="4000" /> / <x-elo-grade :elo="3500" /></td><td>4500 / 4000 / 3500</td></tr>
                <tr><td><x-elo-grade :elo="3000" /> / <x-elo-grade :elo="2500" /> / <x-elo-grade :elo="2000" /></td><td>3000 / 2500 / 2000</td></tr>
                <tr><td><x-elo-grade :elo="1500" /> / <x-elo-grade :elo="900" /> / <x-elo-grade :elo="500" /></td><td>1500 / 900 / 500</td></tr>
                <tr><td><x-elo-grade :elo="0" /></td><td>below 500</td></tr>
            </tbody>
        </table>
        <p>
            Rankings list every account that has played at least one game in the season;
            ties are broken in favour of the account that registered first.
        </p>

        <h2>Seasons</h2>
        <p>
            Seasons run for three months. When a season ends, its final standings are
            posted to the SFL Discord and every player starts the next season fresh at 1000.
            Past seasons stay browsable on the rankings, replay and player pages.
        </p>

        <h2>Fair play</h2>
        <p>
            Only games where every player is a registered SFL member count, and each game
            can only be uploaded once. If a game should not count (for example a rule was
            broken), any player in it can request an invalidation from the game's page.
            The other players can agree or dispute it: if everyone agrees it is approved
            automatically, otherwise an admin decides. An invalidated game no longer
            counts towards anyone's rating.
        </p>

        <h2>Who runs SFL</h2>
        <p>
            SFL is run by <strong>Wk`Cross-</strong>, with help from the league's admins.
            It is a community project: the site, the maps and the rankings
            exist to keep team Fastest active and give players a place to compete and
            improve. Games are also posted on
            <a href="https://www.youtube.com/@cross_tv1" target="_blank" rel="noopener">YouTube (@cross_tv1)</a>,
            and the community lives on our
            <a href="https://discord.com/invite/kZZXemJQzj" target="_blank" rel="noopener">Discord server</a>.
        </p>
        <p>
            Questions, bug reports or ideas? See the <a href="{{ route('contact') }}">Contact</a> page.
        </p>
    </x-content-page>
</x-app-layout>
