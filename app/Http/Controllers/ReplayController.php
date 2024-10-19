<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
            $filePath = $request->file('file')->store('uploads', 'public');
            
            // Return success response
            $scriptPath = '/var/www/html/screp'; // Update with the actual path to your script
            exec("$scriptPath /var/www/html/public/storage/$filePath", $output, $return_var);

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
                        ];
                    }
                }

                // Return the specific data to the view or as a response
                return response()->json([
                    'success' => true,
                    'data' => $teams,
                    'output' => $output,
                ]);
            } else {
                // Return an error if JSON decoding fails
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse JSON output.',
                ]);
            }
                dd([
                    'output' => $output,
                    'return_var' => $return_var,
                    'data' => $desiredData,
                ]);  // Use $filePath to see the stored file path
                return back()->with('success', 'File uploaded successfully. Script executed.')->with('file', $filePath);
            } else {
                return back()->with('error', 'File uploaded successfully, but script execution failed.');
            }
        } else {
            return back()->with('error', 'The uploaded file is not valid.');
        }
    }
}
