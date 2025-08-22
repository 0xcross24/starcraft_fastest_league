// This file contains the dropdown menu logic for season tabs
// and handles navigation to the correct season/format on click.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.season-dropdown').forEach(function (dropdown) {
        var menu = dropdown.querySelector('.dropdown-menu');
        var hideTimeout;

        function showMenu() {
            clearTimeout(hideTimeout);
            menu.classList.remove('hidden');
        }

        function hideMenu(e) {
            // Only hide if mouse is leaving the dropdown entirely (not to a child)
            var toElement = e.relatedTarget;
            if (dropdown.contains(toElement)) {
                return;
            }
            hideTimeout = setTimeout(function () {
                menu.classList.add('hidden');
            }, 150);
        }

        dropdown.addEventListener('mouseover', showMenu);
        dropdown.addEventListener('mouseout', hideMenu);
        menu.addEventListener('mouseover', showMenu);
        menu.addEventListener('mouseout', hideMenu);
    });
});
