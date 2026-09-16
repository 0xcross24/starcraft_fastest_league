import React from "react";
import { connectHits } from "react-instantsearch-dom";

// Component to render one hit = one row
function HitRow({ hit, columns }) {
  return (
    <tr className="border-b border-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
      {columns.map((col) => (
        <td
          key={col.key}
          className="border border-gray-300 text-center px-4 py-2"
        >
          {hit[col.key]}
        </td>
      ))}
    </tr>
  );
}

// Hits table body
const HitsTableBody = ({ hits, columns }) => (
  <tbody>
    {hits.map((hit) => (
      <HitRow key={hit.objectID || hit.id} hit={hit} columns={columns} />
    ))}
  </tbody>
);

// Wrap with connectHits so we get `hits` injected
const CustomHitsTableBody = connectHits(HitsTableBody);

// Reusable table component
export default function Table({ columns }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse border border-gray-200 mb-4">
        <thead className="bg-gray-200 dark:bg-gray-700">
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                className="px-4 py-2 text-center text-gray-700 dark:text-gray-200"
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <CustomHitsTableBody columns={columns} />
      </table>
    </div>
  );
}
