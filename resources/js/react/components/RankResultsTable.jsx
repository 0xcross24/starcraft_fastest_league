import React from "react";
import Table from "./Table";

const columns = [
  { key: "id", label: "#" },
  { key: "player_name", label: "Player" },
  { key: "rank", label: "Rank" },
  { key: "elo", label: "Elo" },
  { key: "record", label: "Record" },
];

export default function RankResultsTable() {
  return (
    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
      <div className="p-6 text-gray-900 dark:text-gray-100">
        <div className="w-full">
          <div className="card">
            <Table columns={columns} />
          </div>
        </div>
      </div>
    </div>
  );
}
