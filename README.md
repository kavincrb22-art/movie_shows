# TicketNew - Movie Ticket Booking System
## PHP + MySQL Clone (Based on TicketNew Screenshots)

---

## 📁 File Structure

```
ticketnew/
├── config/
│   └── db.php           ← Database config & helpers
├── index.php            ← Homepage (hero, now showing, upcoming)
├── movies.php           ← Movies listing with filters
├── movie.php            ← Movie detail + show timings
├── seats.php            ← Seat selection layout
├── booking.php          ← Review booking + payment modal
├── confirmation.php     ← Booking confirmed page
├── orders.php           ← My orders / booking history
├── profile.php          ← User profile (mobile app style)
├── auth.php             ← Login / OTP / Logout handler
├── verify_otp.php       ← OTP verification page
├── set_city.php         ← AJAX city switcher
├── database.sql         ← Full DB schema + sample data
└── README.md
```

---

## ⚙️ Setup Instructions

### Step 1: Create Database
```sql
-- In phpMyAdmin or MySQL CLI:
SOURCE /path/to/ticketnew/database.sql;
```

### Step 2: Configure Database
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Your MySQL username
define('DB_PASS', '');         // Your MySQL password
define('DB_NAME', 'ticketnew_db');
```

### Step 3: Place Files
Copy the `ticketnew/` folder to:
- XAMPP: `C:/xampp/htdocs/ticketnew/`
- WAMP:  `C:/wamp/www/ticketnew/`
- Linux: `/var/www/html/ticketnew/`

### Step 4: Open in Browser
```
http://localhost/ticketnew/
```

---

## 🔑 Features Implemented

| Feature | Status |
|---------|--------|
| Homepage with hero banner | ✅ |
| Now Showing movies grid | ✅ |
| Upcoming movies carousel | ✅ |
| Filter by language/genre | ✅ |
| City selector modal | ✅ |
| Movie detail + show timings | ✅ |
| Date selector (7 days) | ✅ |
| Interactive seat layout | ✅ |
| Booking review page | ✅ |
| Payment modal (Card/UPI/Netbanking) | ✅ |
| Booking confirmation + ref | ✅ |
| My Orders history | ✅ |
| User profile page | ✅ |
| Mobile OTP login | ✅ |
| Demo OTP shown in UI | ✅ |

---

## 📱 Pages Overview

| Page | URL | Description |
|------|-----|-------------|
| Home | `/index.php` | Landing page |
| Movies | `/movies.php` | Browse all movies |
| Movie Detail | `/movie.php?id=1` | Shows & timings |
| Seat Selection | `/seats.php?show_id=1` | Pick seats |
| Review Booking | `/booking.php` | Summary + pay |
| Confirmation | `/confirmation.php?id=1` | Success page |
| Orders | `/orders.php` | Booking history |
| Profile | `/profile.php` | User settings |
| Login | `/index.php?login=1` | OTP login |

---

## 🛠 Tech Stack
- **PHP 7.4+** (MySQLi)
- **MySQL 5.7+**
- **HTML5 / CSS3 / Vanilla JS**
- **Google Fonts (Poppins)**
- No frameworks needed!

---

## 💡 Production Notes

1. **OTP SMS**: Replace demo OTP in `auth.php` with a real SMS API (Twilio, MSG91, etc.)
2. **Payment**: Integrate Razorpay/PayU SDK instead of the mock checkout
3. **Images**: Add real movie poster images to `assets/` folder
4. **Sessions**: Use secure session config in production
5. **HTTPS**: Always use SSL in production
