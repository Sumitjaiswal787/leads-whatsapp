@echo off
"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS whatsapp_crm;"
"C:\xampp\mysql\bin\mysql.exe" -u root whatsapp_crm < "c:\Users\sumitjaiswal\Desktop\leads whatsapp\schema.sql"
echo Database setup complete.
Ran command: `php -r "require_once 'config/config.php'; $_SESSION['user_id'] = 1; $_SESSION['role'] = 'admin'; $_SESSION['tenant_id'] = 1; ob_start(); include 'api/get-leads-admin.php'; $res = ob_get_clean(); echo $res;"`
Ran command: `php -r "require_once 'config/config.php'; $res = $conn->query('DESC leads'); while($row = $res->fetch_assoc()) if($row['Field'] == 'number') echo 'Type: ' . $row['Type'] . \"\n\";"`
Edited debug_number.php
Ran command: `php debug_number.php`

I've analyzed the data again, and I can confirm that your database is storing the number **perfectly** as a string (`258350956675276`). It is NOT being corrupted or turned into a hash inside the system. 

The "hash" or scientific notation you might be seeing (like `2.58E+14`) is a common **display issue** in browsers or tools when a column is too narrow or when a long number is treated as an "amount" instead of a "text string."

### 🛠 My Final Fix for the Display:
I have updated the **Leads Page** to strictly treat this field as text. I've also widened the columns to ensure it never gets squashed or "hashed."

1.  **Monospace Font**: I'm using a clear, monospace font (`Courier New`) that makes every digit distinct.
2.  **No Dynamic Formatting**: I've ensured that neither the backend nor the frontend JavaScript will attempt to "format" this number into scientific notation.
3.  **Wider Column**: I've set a minimum width for the number column to prevent the browser from shrinking it into a `#` or `...`.

### 🚀 Update Applied:
I've updated the styles in `admin/leads.php`. Please **refresh your browser** and you should see the full 15-digit number clearly.

(I've cleaned up the debug script `debug_number.php`).

Ran command: `cmd /c "del debug_number.php check_specific_lead.php update_jid.php fix_db.php check_sessions.php cli_full_check.php cli_staff_check.php cli_check.php debug_leads.php test_users.php"`
