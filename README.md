# Online Auction System For Bank Seized Vehicles

PHP + MySQL project for bike/car bank seized vehicle auction with Razorpay payment and advertisement module.

## Modules

- User: register, login, browse vehicles, place bids, pay winners via Razorpay, download invoice.
- Admin: login, add bank seized vehicles, monitor bids, close auctions, manage ads, verify payments, mark sold.

## Tech Stack

- PHP (XAMPP)
- MySQL
- HTML/CSS/JavaScript
- Node.js (for email via Nodemailer)
- Razorpay Checkout

## Setup (XAMPP)

1. Start Apache and MySQL from XAMPP Control Panel.
2. Create database/tables by importing:
   - `database/schema.sql`
3. Update config if needed:
   - `config/config.php`
4. Configure Razorpay keys in `config/config.php`:
   - `RAZORPAY_KEY_ID`
   - `RAZORPAY_KEY_SECRET`
5. Install Nodemailer dependency:
   - Open terminal in `mailer` folder
   - Run `npm install`
6. Configure SMTP details in `config/config.php`:
   - `SMTP_HOST`
   - `SMTP_PORT`
   - `SMTP_SECURE`
   - `SMTP_USER`
   - `SMTP_PASS`
   - `SMTP_FROM_EMAIL`
   - `SMTP_FROM_NAME`
7. Open in browser:
   - `http://localhost/auction`

## Default Admin Login

- Username: `admin`
- Password: `admin123`

## Important Notes

- Auction closes manually from admin panel (`admin/vehicles.php`).
- Highest bidder is selected automatically when auction closes.
- Winner pays from `user/my_wins.php`.
- Admin verifies paid entries in `admin/payments.php` and marks vehicle as SOLD.
- Upload folders:
  - `uploads/vehicles`
  - `uploads/ads`
## 10.2 Defaulter Details Stored

- Defaulter name
- Loan account number
- Bank name
- Loan amount
- Pending amount
- Seizure date
- Reason for seizure

Each defaulter is linked to one vehicle using a foreign key relationship.
