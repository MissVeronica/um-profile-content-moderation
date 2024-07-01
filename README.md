# UM Profile Content Moderation
Extension to Ultimate Member for Profile Content Moderation. User Profile edit will set the User into Admin Review which the Admin can release and accept or deny the Profile updates. Text changes are highlighted with bold words before and after profile update. Rollback to profile field content before update is possible. Unapproved Profile updates may or may not be allowed to login as of version 3.3.0

## UM Settings
UM Settings -> General -> Users
1. Content Moderation - Profile Forms - Select single or multiple Profile Forms for Content Moderation.
2. Content Moderation - User Roles - Select the User Role(s) to be included in Content Moderation.
3. Content Moderation - Admin Disable - Disable Admin updates of Users from Content Moderation.
4. Content Moderation - Allow Login - Click to disable UM status logincheck of Users not approved yet in Content Moderation
5. Content Moderation - User Pending Notification - Select the User Pending Notification Email template.
6. Content Moderation - User Accept Notification - Select the User Accept Notification Email template. 
7. Content Moderation - User Denial Notification - Select the User Denial Notification Email template.
8. Content Moderation - User Rollback Notification - User Rollback Notification Email template.
9. Content Moderation - Admin Notification - Select the Admin Notification Email template.

## UM Admin Menu
1. Additional UM sub-menu "Content Moderation" for listing of all Users waiting for profile content moderation.
2. Available UM Bulk User Actions: Approve Profile Update, Deny Profile Update, Rollback Profile Update, Deactivate
3. "Review Profile Content Moderation" Modal with before/after content of updated fields with a dropdown "Moderation" link

## UM Email Templates
1. Template for the "Content Moderation - User Pending Notification"
2. Template for the "Content Moderation - User Accept Notification"
3. Template for the "Content Moderation - User Denial Notification"
4. Template for the "Content Moderation - User Rollback Notification"
5. Template for the "Content Moderation - Admin Notification"
6. Placeholder {content_moderation} to display User profile updates in Admin Notification.
7. Example of placeholder text formatting: style="text-align: left; line-height: 20px; font-size: 16px"

## Updates
1. Version 1.1.0 Addition of User Denial Notification Email and changed dropdown menu in UM sub-menu "Content Moderation".
2. Version 2.0.0 Four new email templates. Backend users modal with before/after content of updated fields.
3. Version 2.1.0 Modal notice of sent denial email and timestamp.
4. Version 2.2.0 Improved User Interface. 
5. Version 2.2.1 Setting status approved after accept of updates.
6. Version 2.2.2 Review pending number not altered in the top bar
7. Version 2.2.3 Update of top bar
8. Version 3.0.0 Profile content rollback. Highlighted profile text changes.
9. Version 3.1.0 Disable Admin user profile updates checkbox, Code improvements
10. Version 3.2.0 New email placeholder {content_moderation}
11. Version 3.3.0 Bypass UM logincheck for unapproved users, Dashboard status incl reset of approved users with left settings.
12. Version 3.4.0 Supports UM 2.8.3
13. Version 3.4.1 Dashboard column 3
14. Version 3,5.0/3.5.1 Supports UM 2.8.5
15. Version 3.5.2 Code improvements 

## Account File Manager
1. Profile/Cover photo updates and other uploaded files can be displayed with the <a href="https://github.com/MissVeronica/um-account-file-manager">UM Account File Manager</a> plugin

## Installation
1. Download the zip file and install as a WP Plugin, activate the plugin.
2. New Email Templates will be copied by the plugin to the UM Template folder for emails when plugin is active.
