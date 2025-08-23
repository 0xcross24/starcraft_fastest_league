<x-app-layout>
  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 text-gray-900 dark:text-gray-100">
          <!-- Tabs -->
          <div class="mb-4">
            <div class="flex border-b">
              <button id="gameplay-tab" class="tab-button w-1/3 py-4 px-4 text-center font-medium text-gray-700 hover:text-gray-900 focus:outline-none dark:text-gray-300 dark:hover:text-white">
                Gameplay Rules
              </button>
              <button id="league-tab" class="tab-button w-1/3 py-4 px-4 text-center font-medium text-gray-700 hover:text-gray-900 focus:outline-none dark:text-gray-300 dark:hover:text-white">
                League Rules
              </button>
              <button id="replay-tab" class="tab-button w-1/3 py-4 px-4 text-center font-medium text-gray-700 hover:text-gray-900 focus:outline-none dark:text-gray-300 dark:hover:text-white">
                Replay Upload Rules
              </button>
            </div>
            <div id="replay-content" class="tab-content hidden">
              <!-- Replay Upload Rules Section -->
              <div class="w-full mt-6">
                <div class="card">
                  <div class="card-body">
                    <ul class="list-disc pl-5">
                      <li>File must be a valid <code>.rep</code> replay file and not exceed 2MB.</li>
                      <li>Only 2v2 or 3v3 games are accepted (must be exactly 2 or 3 players per team).</li>
                      <li>All players in the replay must be registered with their in-game username before uploading.</li>
                      <li>Duplicate replays are not allowed.</li>
                      <li>Games must have a winner (draws are not accepted).</li>
                      <li>Games shorter than 2 minutes and 5 seconds are not accepted.</li>
                      <li>Replays older than 48 hours from the game time are not accepted.</li>
                      <li>Only replays played on maps starting with <code>OP SFL-</code> or <code>SFLClan</code> are accepted. You can find these two maps in your profile page</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab Content -->
          <div id="gameplay-content" class="tab-content">
            <!-- Gameplay Rules Section -->
            <div class="w-full">
              <div class="card">
                <div class="card-body">
                  <ul class="list-disc pl-5">
                    <li>No stacked worker attack or repair.</li>
                    <li>No cargo glitch.</li>
                    <li>No allied Spider Mines.</li>
                    <li>Once a player types "gg" or any similar form, that is a declaration of surrender/loss.
                      <ul class="list-decimal pl-5">
                        <li>If a single player surrenders but their respective team continues to play, the surrendering player must disable vision for all players & is not allowed to chat. The player must also remove all potentially relevant factors in the game, such as floating buildings or Overlords.</li>
                      </ul>
                    </li>
                  </ul>
                </div>
                <div class="card-header my-4">
                  <h2 class="text-xl font-bold">Protoss-only Game Rules (e.g. PPP vs PPP)</h2>
                </div>
                <div class="card-body">
                  <ul class="list-disc pl-5">
                    <li>Only 1 Pylon & 1 Photon Cannon is allowed to be built at the choke of each respective player's base.
                      <ul class="list-decimal pl-5">
                        <li>If there are multiple Photon Cannons at the choke, the game can be paused to address the player. Once the game is resumed, the player must immediately destroy the Photon Cannon.</li>
                      </ul>
                    </li>
                    <li>No buildings can be used to block the players' choke.</li>
                    <li>Photon Cannons cannot be built outside of players' bases. Pylons & other buildings are allowed outside of players' bases.</li>
                    <li>There are no limits to Photon Cannons inside a player's own base. However, Photon Cannons built inside the base must not be able to reach the choke of the base except for the one allowance.</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div id="league-content" class="tab-content hidden">
            <!-- League Rules Section -->
            <div class="w-full mt-6">
              <div class="card">
                <div class="card-body">
                  <ul class="list-disc pl-5">
                    <li>Bad manner & toxicity will not be tolerated.</li>
                    <li>All games will be mirror matchups (ex. PPP vs PPP, PPZ vs PPZ, PTZ vs PTZ).</li>
                    <li>Attempts at balancing teams should be kept honest amongst all included players unless teams are specified beforehand.</li>
                    <li>Minimum turn-rate 12 games.</li>
                    <li>If a player is disconnected due to lag or internet connection issues before 2 minutes, the game is voided.</li>
                    <li>Pausing mid-game is allowed within reason & players are recommended to countdown prior to resuming gameplay.</li>
                    <li>All players must be registered with their in-game username prior to playing games & submitting replays. The involved players assume responsibility if the game is voided due to a player within the game not being registered.</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <script>
    // Get references to the tab buttons and content sections
    const gameplayTab = document.getElementById("gameplay-tab");
    const leagueTab = document.getElementById("league-tab");
    const replayTab = document.getElementById("replay-tab");
    const gameplayContent = document.getElementById("gameplay-content");
    const leagueContent = document.getElementById("league-content");
    const replayContent = document.getElementById("replay-content");

    // Add event listeners to handle tab switching
    gameplayTab.addEventListener("click", () => {
      gameplayTab.classList.add("border-b-2", "border-indigo-500");
      leagueTab.classList.remove("border-b-2", "border-indigo-500");
      replayTab.classList.remove("border-b-2", "border-indigo-500");
      gameplayContent.classList.remove("hidden");
      leagueContent.classList.add("hidden");
      replayContent.classList.add("hidden");
    });

    leagueTab.addEventListener("click", () => {
      leagueTab.classList.add("border-b-2", "border-indigo-500");
      gameplayTab.classList.remove("border-b-2", "border-indigo-500");
      replayTab.classList.remove("border-b-2", "border-indigo-500");
      leagueContent.classList.remove("hidden");
      gameplayContent.classList.add("hidden");
      replayContent.classList.add("hidden");
    });

    replayTab.addEventListener("click", () => {
      replayTab.classList.add("border-b-2", "border-indigo-500");
      gameplayTab.classList.remove("border-b-2", "border-indigo-500");
      leagueTab.classList.remove("border-b-2", "border-indigo-500");
      replayContent.classList.remove("hidden");
      gameplayContent.classList.add("hidden");
      leagueContent.classList.add("hidden");
    });
  </script>

</x-app-layout>
