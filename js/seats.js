/* TicketNew - Seat Selection JavaScript */

let selectedSeats = [];
const MAX_SEATS = 10;

/* ═══════════════════════════════════════════════════
   SEAT COUNT MODAL  — How many seats?
   ═══════════════════════════════════════════════════ */
(function initSeatCountModal() {

      /* ── PNG vehicle images (managed via admin) ── */
  var VEHICLES = {
    1: /* Bicycle */
    '<img src="assets/vehicle_1.png" alt="Bicycle" class="scm-vehicle-img">',
    2: /* Scooter */
    '<img src="assets/vehicle_2.png" alt="Scooter" class="scm-vehicle-img">',
    3: /* Auto Rickshaw */
    '<img src="assets/vehicle_3.png" alt="Auto Rickshaw" class="scm-vehicle-img">',
    4: /* Mini Car */
    '<img src="assets/vehicle_4.png" alt="Mini Car" class="scm-vehicle-img">',
    5: /* Sedan */
    '<img src="assets/vehicle_5.png" alt="Sedan" class="scm-vehicle-img">',
    6: /* SUV */
    '<img src="assets/vehicle_6.png" alt="SUV" class="scm-vehicle-img">',
    7: /* Van */
    '<img src="assets/vehicle_7.png" alt="Van" class="scm-vehicle-img">',
    8: /* Van */
    '<img src="assets/vehicle_7.png" alt="Van" class="scm-vehicle-img">',
    9: /* Bus */
    '<img src="assets/vehicle_9.png" alt="Bus" class="scm-vehicle-img">',
    10: /* Bus */
    '<img src="assets/vehicle_10.png" alt="Bus" class="scm-vehicle-img">'
  };

  var currentVehicleEl = null;
  var chosenCount = 1;

  function showVehicle(n) {
    var container = document.getElementById('scmVehicle');
    if (!container) return;
    var svg = VEHICLES[n] || VEHICLES[1];
    // Animate out old, animate in new
    if (currentVehicleEl) {
      currentVehicleEl.classList.remove('visible');
    }
    container.innerHTML = svg;
    var el = container.querySelector('img') || container.querySelector('svg');
    el.style.cssText = 'max-width:220px;max-height:130px;position:absolute;bottom:0;opacity:0;transform:translateX(40px);transition:opacity .3s ease, transform .35s cubic-bezier(.34,1.56,.64,1);';
    currentVehicleEl = el;
    // Force reflow then show
    requestAnimationFrame(function () {
      el.style.opacity = '1';
      el.style.transform = 'translateX(0)';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modal   = document.getElementById('seatCountModal');
    var nums    = document.querySelectorAll('.scm-num');
    var selBtn  = document.getElementById('scmSelectBtn');

    if (!modal) return;

    showVehicle(1);

    nums.forEach(function (btn) {
      btn.addEventListener('click', function () {
        nums.forEach(function (b) { b.classList.remove('active'); });
        this.classList.add('active');
        chosenCount = parseInt(this.dataset.n, 10);
        showVehicle(chosenCount);
      });
    });

    selBtn.addEventListener('click', function () {
      modal.classList.add('hidden');
      // Auto-select the first N bestseller (available) seats
      var available = Array.from(document.querySelectorAll('.seat.available'));
      var toSelect = Math.min(chosenCount, available.length);
      for (var i = 0; i < toSelect; i++) {
        available[i].click();
      }
    });

    modal.addEventListener('click', function (e) {
      if (e.target === modal) modal.classList.add('hidden');
    });
  });
})();

document.addEventListener('DOMContentLoaded', function () {
    // Attach click handlers to all bookable seats
    document.querySelectorAll('.seat:not(.occupied)').forEach(function (seat) {
        seat.addEventListener('click', function () {
            const seatId = parseInt(this.dataset.id);
            const seatLbl = this.dataset.label;
            const price = parseFloat(this.dataset.price);

            if (this.classList.contains('selected')) {
                // Deselect
                this.classList.remove('selected');
                selectedSeats = selectedSeats.filter(function (s) { return s.id !== seatId; });
            } else {
                if (selectedSeats.length >= MAX_SEATS) {
                    showToast('You can select up to ' + MAX_SEATS + ' seats at a time.');
                    return;
                }
                this.classList.add('selected');
                selectedSeats.push({ id: seatId, label: seatLbl, price: price });
            }
            updateTicketBar();
        });
    });
});

function updateTicketBar() {
    const count = selectedSeats.length;
    const total = selectedSeats.reduce(function (sum, s) { return sum + s.price; }, 0);
    const labels = selectedSeats.map(function (s) { return s.label; }).sort().join(', ');
    const btn = document.getElementById('proceedBtn');
    const cntEl = document.getElementById('selectedCount');
    const totEl = document.getElementById('selectedTotal');
    const idsEl = document.getElementById('seatIdsInput');
    const totInp = document.getElementById('totalInput');

    if (cntEl) cntEl.textContent = count > 0 ? labels : '';
    if (totEl) totEl.textContent = count > 0 ? '₹' + total.toFixed(2) : 'Select seats';
    if (idsEl) idsEl.value = selectedSeats.map(function (s) { return s.id; }).join(',');
    if (totInp) totInp.value = total.toFixed(2);

    if (btn) {
        btn.disabled = (count === 0);
        btn.classList.toggle('ready', count > 0);
        btn.textContent = count > 0 ? 'Book Now' : 'Book Now';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('bookingForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (selectedSeats.length === 0) {
                e.preventDefault();
                showToast('Please select at least one seat.');
            }
        });
    }
});

function showToast(msg) {
    let toast = document.getElementById('tn-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'tn-toast';
        toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#1a1a1a;color:#fff;padding:10px 20px;border-radius:20px;font-size:0.82rem;font-family:Poppins,sans-serif;z-index:9999;opacity:0;transition:opacity 0.2s;';
        document.body.appendChild(toast);
    }
    toast.textContent = msg;
    toast.style.opacity = '1';
    setTimeout(function () { toast.style.opacity = '0'; }, 2500);
}