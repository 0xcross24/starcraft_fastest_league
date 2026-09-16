import React from "react";
import ReactDOM from "react-dom/client";
import { instantMeiliSearch } from "@meilisearch/instant-meilisearch";
import { InstantSearch } from "react-instantsearch-dom";

const searchClient = instantMeiliSearch("http://127.0.0.1:7700", "");

// Mount into #searchbox-root if it exists
const registry = [
  {
    targetId: "rank-searchbox",
    importer: () => import("./components/RankSearchBox.jsx"),
    indexName: "users"
  },
  {
    targetId: "ranking-table",
    importer: () => import("./components/RankResultsTable.jsx"),
    indexName: "users"
  }
];

(async function mountReactSearchWidgets() {
  // Find which registrations actually have a container on the page
  const activeRegs = registry.filter((r) => !!document.getElementById(r.targetId));
  if (!activeRegs.length) return; // nothing to mount

  // Dynamically import only the components needed for this page
  const loaded = await Promise.all(
    activeRegs.map(async (r) => {
      const module = await r.importer();
      console.log("Loaded component for", r.targetId, ":", module);
      return { ...r, Component: module.default };
    })
  );

  // Group loaded components by indexName (we'll create one InstantSearch provider per index)
  // We read data-index from the container to allow per-container override.
  const groups = loaded.reduce((acc, item) => {
    const el = document.getElementById(item.targetId);
    const idx = (el && el.dataset.index) || item.indexName || "users";
    acc[idx] = acc[idx] || [];
    acc[idx].push(item);
    return acc;
  }, {});

   // Build a React tree: one InstantSearch per indexName, with components as children
  function Root() {
    return (
      <>
        {Object.entries(groups).map(([indexName, items]) => (
          <InstantSearch key={indexName} searchClient={searchClient} indexName={indexName}>
            {items.map((it) => (
              // note: we pass targetId so the component knows where to portal
              <it.Component key={it.targetId} target={it.targetId} />
            ))}
          </InstantSearch>
        ))}
      </>
    );
  }

  // mount once into a hidden container appended to body
  const mountEl = document.createElement("div");
  mountEl.id = "react-search-root";
  document.body.appendChild(mountEl);
  ReactDOM.createRoot(mountEl).render(<Root />);
})();
