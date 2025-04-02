
# Potato Credit Tracker System

A system for tracking potato sales on credit, debt collection, workers management, and expenses.

## Installation Instructions

1. Install XAMPP from https://www.apachefriends.org/download.html

2. Start XAMPP Control Panel and start Apache and MySQL services

3. Copy all the system files to your XAMPP htdocs folder:
   (Usually C:\xampp\htdocs\potato-tracker\ or /Applications/XAMPP/htdocs/potato-tracker/)

4. Open your browser and navigate to:
   http://localhost/phpmyadmin/

5. Create a new database:
   - Click on "New" in the left sidebar
   - Enter "potato_credit_tracker" as the database name
   - Click "Create"

6. Import the database structure:
   - Select the "potato_credit_tracker" database
   - Click on the "Import" tab
   - Click "Choose File" and select the "database.sql" file from the system files
   - Click "Go" at the bottom of the page

7. Access the system:
   http://localhost/potato-tracker/

8. Default login credentials:
   - Username: admin
   - Password: admin123

9. After logging in, change the default admin password using the profile page.

10. You can now start using the system by adding workers, stores/lorries, customers, and recording sales and payments.

## System Features

- User authentication with two roles: Admin and Worker
- Customer management
- Debt tracking
- Payment collection recording
- Worker management (admin only)
- Store/Lorry management
- Expense tracking
- Sales recording (cash and credit)
- Reporting and statistics

## Security Notes

- Change the default admin password immediately after first login
- Do not share worker login credentials
- Regularly backup your database

## Support

For issues or questions about the system, please contact:
[Your contact information here]
