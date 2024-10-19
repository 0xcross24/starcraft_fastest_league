<?php

namespace App\Http\Controllers;

use App\Models\Replay;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReplayController extends Controller
{
    public function index() {
        return view('replays.index');
    }

    public function upload(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|file|max:2048', // Accept any file type, limited to 2MB
        ]);

        // Check if the file is valid
        if ($request->file('file')->isValid()) {
            if ($request->file('file')->getClientOriginalExtension() !== 'rep') {
                return back()->with('error', 'Only .rep files are allowed.');
            }

            // Store the file in the 'uploads' directory on the 'public' disk
            $fileName = $request->file('file')->store('uploads', 'public');
            $filePath = "/var/www/html/public/storage/$fileName";
            
            // Return success response
            $scriptPath = '/var/www/html/screp'; // Update with the actual path to your script
            exec("$scriptPath $filePath", $output, $return_var);

            // Debug the file path with dd
            if ($return_var === 0) {
                $jsonOutput = implode("\n", $output);
                $data = json_decode($jsonOutput, true); // Decodes JSON into an associative array
                

            // Check if the JSON decoding was successful
            if (json_last_error() === JSON_ERROR_NONE) {

                $teams = [
                    'Team1' => [],
                    'Team2' => [],
                ];
                
                // Extract specific data from the JSON output
                $players = $data['Header']['Players'];
                $startTime = $data['Header']['StartTime'];
                $winnerTeam = $data['Computed']['WinnerTeam'];

                foreach ($players as $player) {
                    // Check if Team exists in the player array
                    $teamNumber = $player['Team'] ?? null;
                
                    // Only proceed if Team exists
                    if ($teamNumber) {
                        $teamKey = 'Team' . $teamNumber; // e.g., "Team1", "Team2"
                        
                        // Add player details to the corresponding team
                        $teams[$teamKey][] = [
                            'ID' => $player['ID'],
                            'Name' => $player['Name'],
                            'StartTime' => $startTime,
                            'IsWinner' => ($teamNumber == $winnerTeam),
                            'Team' => $teamNumber,
                        ];
                    }
                }

                // Store the JSON output into database
                return $this->store($teams, $filePath);
            } else {
                // Return an error if JSON decoding fails
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse JSON output.',
                ]);
            }
                return back()->with('success', 'File uploaded successfully. Script executed.')->with('file', $filePath);
            } else {
                return back()->with('error', 'File uploaded successfully, but script execution failed.');
            }
        } else {
            return back()->with('error', 'The uploaded file is not valid.');
        }
    }

    public function store($data, $filePath) {

        // Hash the filePath to ensure there's no duplicate replay files being uploaded
        $hashedFile = hash_file('sha256', $filePath);
        $uuid = Str::uuid()->toString();

        // Check if a replay with this hash already exists
        if (Replay::where('hash', $hashedFile)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate replay file detected.',
                'hash' => $hashedFile,
                'file_name' => $filePath,
            ]);
        }

        foreach ($data as $id) {
            foreach ($id as $player) {
                // Validate player information
                $playerName = $player['Name'] ?? null;
                $startTime = $player['StartTime'] ?? null;
                $startTimeFormatted = date('Y-m-d H:i:s', strtotime($startTime));
                $isWinner = $player['IsWinner'] ?? false; // Assuming IsWinner is boolean
        
                // Determine winning team based on player’s IsWinner status
                $winningTeam = $isWinner ? 1 : 0; // Adjust based on your team's logic
                if ($playerName) {
                    // Store replay for each player
                    Replay::create([
                        'replay_id' => $uuid,
                        'player_name' => $playerName,
                        'winning_team' => $winningTeam,
                        'start_time' => $startTimeFormatted,
                        'replay_file' => $filePath,
                        'team' => $player['Team'], // Assuming team is based on player ID or adjust as needed
                        'hash' => $hashedFile, // Store the hash
                    ]);

                } else {
                    return response()->json([
                        'success' => false,
                    ]);
                }
            }
        }
        return $this->display($uuid);
    }

    public function display($uuid) {
        $replays = Replay::where('replay_id', $uuid)->get();
    
        return view('replays.results', compact('replays'));
    }
}
