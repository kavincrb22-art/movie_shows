/* TicketNew - Payment / Booking JavaScript */

// ===== CHECKOUT MODAL =====
function openCheckout() {
    document.getElementById('checkoutModal')?.classList.add('open');
}
function closeCheckout() {
    document.getElementById('checkoutModal')?.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('checkoutModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeCheckout();
        });
    }
});

// ===== ACCORDION TOGGLE =====
function toggleSection(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isOpen = el.classList.contains('active');
    // Close all sections first
    document.querySelectorAll('.payment-body').forEach(function (b) {
        b.classList.remove('active');
    });
    // Open clicked one if it was closed
    if (!isOpen) el.classList.add('active');
}

// ===== QR CODE GENERATOR (canvas placeholder) =====
function generateQR() {
    const box = document.querySelector('.qr-box');
    if (!box) return;
    box.innerHTML = '<canvas id="qrCanvas" style="border-radius:8px;"></canvas>';
    const canvas = document.getElementById('qrCanvas');
    canvas.width = 130;
    canvas.height = 130;
    const ctx = canvas.getContext('2d');
    // White background
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, 130, 130);
    // Finder pattern TL
    drawFinder(ctx, 4, 4);
    // Finder pattern TR
    drawFinder(ctx, 4 + 80, 4);
    // Finder pattern BL
    drawFinder(ctx, 4, 4 + 80);
    // Data cells
    ctx.fillStyle = '#000';
    for (let i = 0; i < 13; i++) {
        for (let j = 0; j < 13; j++) {
            if (Math.random() > 0.5 && !(i < 3 && j < 3) && !(i > 9 && j < 3) && !(i < 3 && j > 9)) {
                ctx.fillRect(4 + i * 9, 4 + j * 9, 7, 7);
            }
        }
    }
    // Label
    const label = document.createElement('p');
    label.style.cssText = 'font-size:0.7rem;color:#888;margin-top:6px;text-align:center;';
    label.textContent = 'Scan with any UPI app';
    box.appendChild(label);
}

function drawFinder(ctx, x, y) {
    ctx.fillStyle = '#000';
    ctx.fillRect(x, y, 27, 27);
    ctx.fillStyle = '#fff';
    ctx.fillRect(x + 3, y + 3, 21, 21);
    ctx.fillStyle = '#000';
    ctx.fillRect(x + 6, y + 6, 15, 15);
}

// ===== CARD NUMBER FORMATTING =====
document.addEventListener('DOMContentLoaded', function () {
    const cardNumInput = document.querySelector('input[placeholder="Card Number"]');
    if (cardNumInput) {
        cardNumInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 16);
            this.value = v.replace(/(.{4})/g, '$1 ').trim();
        });
    }
    const expiryInput = document.querySelector('input[placeholder="Expiry Date (MM/YY)"]');
    if (expiryInput) {
        expiryInput.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 4);
            if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
            this.value = v;
        });
    }
    // Footer PAY button — submit the form inside the currently open payment section
    const footerBtn = document.querySelector('.pay-footer-btn');
    if (footerBtn) {
        footerBtn.addEventListener('click', function () {
            const openBody = document.querySelector('.payment-body.active');
            if (!openBody) {
                alert('Please select a payment method first.');
                return;
            }
            const form = openBody.querySelector('form');
            if (form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    }
});
