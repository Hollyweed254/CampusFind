# Test cases

"Result (dev check)" was observed in a scripted run on a local PHP 8.3 + MariaDB setup. Re-run each on XAMPP and on the live site, then add screenshots and your own Actual/Pass columns.

| ID | Test | Expected | Result (dev check) |
|----|------|----------|--------------------|
| TC01 | Register with valid details | Account created, logged in | Pass |
| TC02 | Register with existing email | Rejected with message | Pass ("already registered") |
| TC03 | Login with wrong password | Denied | Pass |
| TC04 | Publish valid product listing | Saved as pending | Pass |
| TC05 | Submit invalid/incomplete listing | Errors shown, nothing saved | Pass (5 errors, no row) |
| TC06 | Search by keyword/category/type/price | Only matches returned | Pass |
| TC07 | Edit/delete another user's listing | Denied (403), data intact | Pass |
| TC08 | Student opens admin page | 403 | Pass |
| TC09 | Upload PHP file renamed .jpg | Rejected | Pass |
| TC10 | Logout, then open protected page | Redirect to login | Pass |
| TC11 | POST without CSRF token | Rejected (419) | Pass |
| TC12 | Pending listing visible publicly | 404 until approved | Pass |
| TC13 | Admin reject/remove without note | Blocked, asks for note | Pass |
| TC14 | Deactivated user logs in / uses open session | Denied | Pass |
| TC15 | Owner edits approved listing | Returns to pending | Pass |
