@props(['season', 'format' => null, 'player' => null])

{{-- Zip of the games ticked with x-replay-download's "Select" boxes. The picks
     are kept per season/format/player for this tab, so they survive paging. --}}
<div class="js-zip-selected items-center gap-2 text-xs" style="display: none"
    data-scope="{{ implode(':', [$season, $format, $player]) }}"
    data-base="{{ route('replays.download-zip', array_filter(['season' => $season, 'format' => $format, 'player' => $player])) }}"
    data-max="{{ \App\Http\Controllers\ReplayZipController::MAX_PICKED }}">
    <a href="#" class="inline-block px-3 py-1 bg-emerald-600 text-white rounded hover:bg-emerald-700 transition font-semibold">
        Download selected (<span data-count>0</span>) .zip
    </a>
    <button type="button" data-clear class="text-gray-500 dark:text-gray-400 hover:underline">Clear</button>
</div>
<script>
(box => document.addEventListener('DOMContentLoaded', () => {
    const key = 'sfl-zip-picks:' + box.dataset.scope;
    const max = Number(box.dataset.max);
    const boxes = document.querySelectorAll('.js-zip-pick');
    let picked;
    try { picked = JSON.parse(sessionStorage.getItem(key)) || []; } catch { picked = []; }

    const render = () => {
        sessionStorage.setItem(key, JSON.stringify(picked));
        box.style.display = picked.length ? 'inline-flex' : 'none';
        box.querySelector('[data-count]').textContent = picked.length;
        const url = new URL(box.dataset.base);
        picked.forEach(id => url.searchParams.append('games[]', id));
        box.querySelector('a').href = url;
        boxes.forEach(c => { c.checked = picked.includes(c.value); });
    };

    boxes.forEach(c => {
        c.closest('.js-zip-pick-label').style.display = 'inline-flex';
        c.addEventListener('change', () => {
            if (c.checked && picked.length >= max) {
                alert(`You can pick up to ${max} games at a time.`);
            } else {
                picked = c.checked ? [...new Set([...picked, c.value])] : picked.filter(id => id !== c.value);
            }
            render();
        });
    });
    box.querySelector('[data-clear]').addEventListener('click', () => { picked = []; render(); });
    render();
}))(document.currentScript.previousElementSibling);
</script>
