MONEY BIOR HARDWARE MANAGEMENT SYSTEM  (PHP + MySQL + XAMPP)

All data is now saved in a MySQL database (not in the browser), so it is kept
safely and shared on every computer that opens the system.

USE WITHOUT XAMPP (for a store / client)
  Just double-click index.html (or START_HERE.html). The badge at the top shows
  "Offline mode - saved on this computer". Record data as normal; it stays in that
  browser on that computer. Use the same browser each time, and press
  Reports -> "Download Data Backup" regularly.
  When XAMPP is running and you log in at http://localhost/MoneyBior_Database_Project/index.html
  the badge turns green ("Online - XAMPP database") and records saved offline are
  copied into MySQL automatically. (Logins created offline stay on that computer.)

OPEN THE PROJECT (easiest way)
  Double-click  Start_MoneyBior.bat
  It copies the project into C:\xampp\htdocs (first time only), starts Apache and
  MySQL, and opens the project in your browser.

OPEN THE PROJECT (links)
  - Open_MoneyBior.url   : double-click to jump straight to the project.
  - START_HERE.html      : double-click, then click "Open my project".
  - Direct address       : http://localhost/MoneyBior_Database_Project/index.html
  (Apache and MySQL must be running in the XAMPP Control Panel for these links to work.)

MANUAL INSTALL
  1. Copy this whole folder (MoneyBior_Database_Project) into  C:\xampp\htdocs\
     (replace the old folder if you have one).
  2. Open the XAMPP Control Panel and Start  Apache  and  MySQL.
  3. Open http://localhost/MoneyBior_Database_Project/index.html  (press Ctrl+F5).
  The database "moneybior_hardware" and all its tables are created AUTOMATICALLY the
  first time the page loads. You do not need to import anything.
  (database.sql is included only if you prefer to import it by hand in phpMyAdmin.)
  If your MySQL has a root password, set it at the top of api.php ($pass).
  You can see your saved data any time in http://localhost/phpmyadmin/ -> moneybior_hardware.

FIRST USE
  1. On the login page click "New User" (the User Form - the only place users are
     registered). Fill in User ID, Full name, Username, Role and Password, then
     click Register User.
  2. Click Login and sign in.
  3. Forgot your password? Click Reset on the login page and enter your Username and
     the User ID you registered with.
  There is no built-in default account; the first user you register is your login.

WHAT IS IN THE SYSTEM
  - Dashboard with a photo background and live counts
  - Stock / Products, Customers, Suppliers, Product Requests, Sales / Transactions
    (each page has its form and its saved list, with Delete buttons)
  - Registered Records page: products, customers, suppliers, users, requests and
    sales in one place, with a search box
  - Reports: view each report, export it to CSV, print it, download a JSON backup
  - Selling a product that is in stock reduces its quantity automatically, and a sale
    larger than the stock is refused
  Duplicate IDs are rejected by the database.

CHANGE THE PHOTOS
  The login and dashboard pages show a photo from the internet when online, and
  images\dashboard-bg.jpg when offline. Replace that file with your own picture
  (keep the same name) to use it as the offline background.

NOTES
  - Do not open index.html by double-clicking it; always use the localhost address.
  - Public registration: the login-page User Form works for anyone who can reach the
    page. On a shared network, set ALLOW_PUBLIC_REGISTRATION to false at the top of
    api.php so new users can only be added by someone logged in.
  - Data saved earlier in the old browser-only version is not moved automatically;
    re-enter it or use the forms to add it to the database.
  - For real deployment also set a MySQL password, use HTTPS and take backups.
