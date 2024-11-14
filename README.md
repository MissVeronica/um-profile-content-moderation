# UM Profile Content Moderation version 3.7.1

Extension to Ultimate Member for Profile Content Moderation. 
User Profile edit will set the User into Admin Review which the Admin can release and accept or deny the Profile updates. 
Text changes are highlighted with bold words before and after profile update. 
Rollback to profile field content before update is possible. 
Unapproved Profile updates may or may not be allowed to login as of version 3.3.0

Version 3.6.0 allows the User to continue without approval using old Profile data until approved by a Moderator when the User Profile real update is performed.

Registration Approval - Known issue from UM version 2.8.7

## UM Settings -> Extensions -> Profile Content Moderation
Plugin version update check each 24 hours with documentation link.
### Moderation Forms & Roles
1. * Profile Forms - Select single or multiple Profile Forms for Content Moderation.
2. * User Roles - Select the User Role(s) to be included in Content Moderation.
### UM Dashboard
3. * UM Dashboard Modal - Click to enable the UM Dashboard modal for Content Moderation.
### User Info
4. * Enable User Update Status - Click to enable a "days since update" colored Profile circle after the Profile page User name.
5. * Enter colors for the Profile circle - Enter colors either by color name or HEX code comma separated for each day's display of the "days since update" Profile circle. Default color is "white" and is displayed for 7 days.
6. * Enable transparency increase for the Profile circle - Click to enable increased transparency of the "days since update" Profile circle for each day after approved update.
7. * Enter size in pixels for the Profile circle - Enter size in pixels for the "days since update" Profile circle. Default value is 24 pixels.
### Moderation Process
Default: User set to UM "Admin Review" status with Profile updated. Option from version 3.6.0: Delayed Profile update until approved by a site Moderator.

Settings are only displayed for changes when the queue of users waiting for approval is empty.

8. * Delay User Profile update during Moderation - Click to enable the delay of the User Profile update until approved by a site Moderator.
9. * Delay User Profile update during Moderation - Settings are only displayed for changes when the queue of users waiting for approval is empty.
10. * Delay User Profile update URL at the Cog wheel menu - Enter an URL to a page where you explain the Content Moderation with delayed update procedure at your site. Link replaces "Edit Profile" when user is awaiting Content Moderation. Blank URL disables link and "Edit Profile" text.
11. * Delay User Profile update text at the Cog wheel menu - Enter a short URL text message. Default text is "Why Content Moderation". 
12. * Disable Admin updates Moderation - Click to disable Admin updates of Users from Content Moderation.
13. * Allow Users Login - Click to disable UM status logincheck of Users not approved yet in Content Moderation.
### Registration Approval - Known issue from UM version 2.8.7
14. * Enable Email Activation plus Admin Review - Click to enable Admin Review after successful Email Activation by the User. UM Setting Registration email Activation must be set in advance. NOTE This option no longer works as expected from UM version 2.8.7. Use the new plugin "Email Activation and Admin Review" https://github.com/MissVeronica/um-email-activation-admin-review
### Email Templates
15. * User Pending Notification - Select the User Pending Notification Email template.
16. * User Accept Notification - Select the User Accept Notification Email template. 
17. * User Denial Notification - Select the User Denial Notification Email template.
18. * User Rollback Notification - User Rollback Notification Email template.
19. * Admin Notification - Select the Admin Notification Email template.

## UM Admin Menu
1. Additional UM sub-menu "Content Moderation" for listing of all Users waiting for profile content moderation.
2. Available UM Bulk User Actions: Approve Profile Update, Deny Profile Update, Rollback Profile Update, Deactivate
3. "Review Profile Content Moderation" Modal with before/after content of updated fields with a dropdown "Moderation" link

## UM Dashboard
1. Optional Modal for the plugin
2. Button: Reset Moderation cache counters
3. Button: Reset any left User Profile update values and Moderation cache counters

## User Profile page
1. After User name: "days since update" colored Profile circle with increasing transparency each day after approval
2. New User Profile edit disabled until Moderator approved. Edit Link replaced with cusdtom info.

## UM Email Templates
1. Template for the "Content Moderation - User Pending Notification"
2. Template for the "Content Moderation - User Accept Notification"
3. Template for the "Content Moderation - User Denial Notification"
4. Template for the "Content Moderation - User Rollback Notification"
5. Template for the "Content Moderation - Admin Notification"
6. Placeholder {content_moderation} to display User profile updates in Admin Notification.
7. Example of placeholder text formatting: style="text-align: left; line-height: 20px; font-size: 16px"
8. For User Role dependant email content, use the "Email Parse Shortcode" plugin.
9. https://github.com/MissVeronica/um-email-parse-shortcode

## Translations & Text changes
1. Use the "Loco Translate" plugin.
2. https://wordpress.org/plugins/loco-translate/
3. For a few changes of text use the "Say What?" plugin with text domain content-moderation
4. https://wordpress.org/plugins/say-what/

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
16. Version 3.6.0 Settings moved to UM Extensions. Delay User Profile update during Moderation. User Update Status: "days since update". Email Activation plus Admin Review. Backend performance improvements. Plugin version update check each 24 hours. Translation ready.
17. Version 3.6.1 Fix when the plugin is the first UM Extension
18. Version 3.6.3 Support for "Admin User Profile updates" in "Delayed User Profile update" mode
19. Version 3.7.0 Support for UM 2.8.7. New design of UM Bulk User Actions. Fix for issue Profiles with Roles not selected for moderation when delayed update is active.
20. Version 3.7.1 Update for conflict in UM Bulk User Actions and Admin notices. Code improvements.

## Account File Manager
1. Profile/Cover photo updates and other uploaded files can be displayed with the <a href="https://github.com/MissVeronica/um-account-file-manager">UM Account File Manager</a> plugin

## Installation
1. Download the plugin ZIP file at the green Code button
2. Install as a WP Plugin, activate the plugin.
3. New Email Templates will be copied by the plugin to the UM Template folder for emails when plugin is active.
