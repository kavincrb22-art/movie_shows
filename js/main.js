/* TicketNew - Main JavaScript */

// ===== LOGIN MODAL =====
function openLoginModal() {
    document.getElementById('loginModal')?.classList.add('active');
}
function closeLoginModal() {
    document.getElementById('loginModal')?.classList.remove('active');
}

// ===== CITY MODAL =====
function openCityModal() {
    document.getElementById('cityModal')?.classList.add('active');
}
function closeCityModal() {
    document.getElementById('cityModal')?.classList.remove('active');
}
function selectCity(id, name) {
    fetch('set_city.php?id=' + id + '&name=' + encodeURIComponent(name))
        .then(() => location.reload());
}

// ===== CLOSE MODALS ON OVERLAY CLICK =====
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal-overlay').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('active');
        });
    });

    // City search filter
    const citySearchEl = document.getElementById('citySearch');
    if (citySearchEl) {
        citySearchEl.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('.city-link, .city-chip').forEach(function (el) {
                const txt = el.textContent.toLowerCase();
                el.closest('button, a').style.display = txt.includes(q) ? '' : 'none';
            });
        });
    }

    // Search input with debounce
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            if (q.length > 2) {
                searchTimer = setTimeout(function () {
                    location.href = 'movies.php?q=' + encodeURIComponent(q);
                }, 500);
            }
        });
    }
});

// ===== FILTER BUTTONS =====
function initFilterButtons() {
    document.querySelectorAll('.filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');
        });
    });
}
document.addEventListener('DOMContentLoaded', initFilterButtons);

function toggleFilters() {
  document.getElementById("filterMenu").classList.toggle("show");
}

function selectFilter(el) {
  el.classList.toggle("active");
}

// Close when clicking outside
window.onclick = function(e) {
  if (!e.target.matches('.filter-btn')) {
    let menu = document.getElementById("filterMenu");
    if (menu.classList.contains("show")) {
      menu.classList.remove("show");
    }
  }
}
