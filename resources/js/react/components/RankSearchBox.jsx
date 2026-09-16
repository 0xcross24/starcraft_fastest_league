import React from "react";
import ReactDOM from "react-dom/client";
import { SearchBox } from "react-instantsearch-dom";
import { createPortal } from "react-dom";


export default function RankSearchBox({ target }) {
  const el = typeof target === "string" ? document.getElementById(target) : null;
  if (!el) return null;

  const ui = (
    <div className="mb-4">
      <SearchBox
        translations={{ placeholder: "Search players..." }}
        classNames={{
          root: "relative w-full max-w-md mb-4",
          form: "relative w-full",
          input:
            "w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg " +
            "text-gray-900 dark:text-white dark:bg-gray-800 " +
            "placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 " +
            "appearance-none",
          submit:
            "absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300",
          reset:
            "absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300",
        }}
      />
    </div>
  );

  return createPortal(ui, el);
}

