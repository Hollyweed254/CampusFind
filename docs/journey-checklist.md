# User journey checklist

Accounts: admin `admin@campusfind.local` / `admin123`. Demo students (after seeding) `amina@demo.campusfind.local`, `brian@...`, `cynthia@...` / `password123`.
Use a normal window for one user and a private window for another. Take a screenshot of each Pass for your report.

| # | Journey | Steps | Expected | Pass |
|---|---------|-------|----------|------|
| 1 | Register | /register.php with valid details | Account created, logged in | [ ] |
| 2 | Register errors | Reuse an email; use a 5-character password; mismatch passwords | Clear error each time, nothing saved | [ ] |
| 3 | Login / logout | Wrong password, then correct; log out; open /my-listings.php | Wrong denied; after logout redirected to login | [ ] |
| 4 | Create listing | Student > Sell / Offer: product with 2 photos | "Submitted" notice; shows Pending Review in My listings | [ ] |
| 5 | Create errors | Empty title, product with no price, bad contact, a .txt renamed .jpg | Errors shown; nothing saved; bad upload rejected | [ ] |
| 6 | Pending is hidden | Log out, open the new listing URL | 404 / not found | [ ] |
| 7 | Admin approve | Admin > Listings > Approve | Listing now visible in Marketplace and homepage | [ ] |
| 8 | Search | Marketplace: keyword, type, category, price range, sort | Only matching listings; count correct; pagination works | [ ] |
| 9 | Contact seller | Logged out: open listing; logged in: open listing | Logged out sees "Log in to contact seller"; logged in sees Contact Seller + number | [ ] |
| 10 | Edit listing | Owner edits price/photos | Saved; status returns to Pending Review | [ ] |
| 11 | Ownership | Other student opens /edit-listing.php?id=<not theirs> | 403 denied | [ ] |
| 12 | Mark sold / close / reopen / delete | My listings buttons | Status badge changes; delete asks to confirm and removes photos | [ ] |
| 13 | Report | Another student: Report a listing (twice) | Report saved once; second attempt says already reported | [ ] |
| 14 | Admin handles report | Admin > Reported: dismiss one; remove one | Report status updates; removed listing disappears from Marketplace | [ ] |
| 15 | Reject / remove reason | Admin rejects a pending listing; removes an approved one | Modal requires a reason; reason appears in dashboard log | [ ] |
| 16 | Admin access | Student opens /admin/dashboard.php | 403 denied | [ ] |
| 17 | Users | Admin deactivates a student; that student tries to log in | Login refused with deactivated message | [ ] |
| 18 | Reports | Admin > Reports: change dates, print | 3 reports update; totals consistent with Listings | [ ] |
| 19 | Mobile | Browser device mode (375px): home, marketplace, listing, create | No sideways scroll; search stacks; cards readable | [ ] |
| 20 | Theme cookie | Toggle moon icon, reload, close and reopen browser | Theme remembered | [ ] |
