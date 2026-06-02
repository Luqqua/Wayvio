<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Home Page
    |--------------------------------------------------------------------------
    |
    | resources/views/home.blade.php
    |
    */

    'Log in' => 'Anmelden',
    'Register' => 'Registrieren',

    'Dashboard' => 'Dashboard',
    'Copyright' => 'Urheberrecht',
    'Made with' => 'Gemacht mit',
    'by' => 'von',


    'HOME.MESSAGE' => '
    <p>Nehmen Sie die Kontrolle über Ihre Online-Präsenz mit <a href="https://wayvio.example/"><strong>Wayvio</strong></a>
    , der datenschutzorientierten, Open-Source <strong>Link-Management-Plattform</strong>. 
    Erstellen Sie eine anpassbare Profilseite, um <strong> alle Ihre wichtigen Links an einem Ort zu verwalten</strong> 
    und Ihren Besuchern ein nahtloses Browsing-Erlebnis zu bieten.</p>
    ',


    /*
    |--------------------------------------------------------------------------
    | Demo Page/Home Page Example Page
    |--------------------------------------------------------------------------
    |
    | resources/views/demo.blade.php
    |
    */
    
    'Example page' => 'Beispielseite',


    /*
    |--------------------------------------------------------------------------
    | Authentication Pages
    |--------------------------------------------------------------------------
    |
    | Login, Register, Forgot Password, Reset Password etc.
    | This includes authentication emails like password reset and email verification.
    | resources/views/auth
    |
    */

    # Login Page
    'Sign In' => 'Anmelden',
    'Login to stay connected' => 'Melden Sie sich an, um in Verbindung zu bleiben',
    'Email' => 'Email',
    'Password' => 'Passwort',
    'Remember Me' => 'Angemeldet bleiben',
    'Forgot Password?' => 'Passwort vergessen?',
    'or sign in with other accounts?' => 'oder mit anderen Konten anmelden?',
    'Don’t have an account?' => 'Sie haben noch kein Konto?',
    'Click here to sign up' => 'Klicken Sie hier, um sich zu registrieren',


    # Reset password
    'Forgot your password?' => 'Haben Sie Ihr Passwort vergessen?',
    'No problem' => 'Kein Problem. Teilen Sie uns einfach Ihre E-Mail-Adresse mit und wir senden Ihnen per E-Mail einen Link zum Zurücksetzen Ihres Passworts, mit dem Sie ein neues Passwort auswählen können.',
    'Email Password Reset Link' => 'Link zum Zurücksetzen des Passworts per E-Mail senden',


    # Register Page
    'Sign Up' => 'Anmeldung',
    'Register to stay connected' => 'Registrieren Sie sich, um in Verbindung zu bleiben',
    'Display Name' => 'Anzeigename',
    'Confirm Password' => 'Bestätige das Passwort',
    'Already have an account?' => 'Sie haben bereits ein Konto?',
    'Click here to sign in' => 'Hier klicken um sich anzumelden',


    # Pending verification by admin
    'Verification Status' => 'Verifizierungsstatus',
    'auth_pending' => 'Die Verifizierung Ihres Kontos steht noch aus',
    'auth_unverified' => 'Ihr Konto ist derzeit nicht verifiziert und erfordert eine manuelle Verifizierung durch einen Administrator.',
    'Log out' => 'Ausloggen',


    # Password confirmation
    'auth_password' => 'Dies ist ein sicherer Bereich der Anwendung. Bitte bestätigen Sie Ihr Passwort, bevor Sie fortfahren.',
    'Confirm' => 'Bestätigen',


    # Password Reset
    'Reset Password' => 'Passwort zurücksetzen',
    'Enter a new password' => 'Geben Sie ein neues Kennwort ein',


    # Test email
    'Test E-Mail' => 'Test-Email',


    # Signup notification email
    'A new user has registered on' => 'Ein neuer Benutzer hat sich registriert',
    'and is awaiting verification' => 'und wartet auf die Bestätigung',
    'The user' => 'Der Benutzer',
    'with the email' => 'mit der E-Mail',
    'has registered a new account on' => 'hat ein neues Konto registriert',
    'and is awaiting confirmation by an admin' => 'und wartet auf die Bestätigung durch einen Administrator.',
    'Click' => 'Klicken',
    'here' => 'Hier',
    'to verify the user' => 'um den Benutzer zu verifizieren.',
    'Manage Users' => 'Benutzerverwaltung',


    # Email verification email
    'auth_thanks' => 'Danke für\'s Registrieren! Bevor Sie beginnen, können Sie Ihre E-Mail-Adresse überprüfen, indem Sie auf den Link klicken, den wir Ihnen gerade per E-Mail zugesandt haben. Wenn Sie die E-Mail nicht erhalten haben, senden wir Ihnen gerne eine neue zu. Wenn Sie die E-Mail nicht innerhalb weniger Minuten sehen, überprüfen Sie Ihren Junk-Mail- oder Spam-Ordner.',
    'auth_verification' => 'Ein neuer Bestätigungslink wurde an die E-Mail-Adresse gesendet, die Sie bei der Registrierung angegeben haben.',
    'Resend Verification Email' => 'Bestätigungsmail erneut senden',
    'Two-factor authentication' => 'Zwei-Faktor-Authentifizierung',
    'Two-factor setup created' => 'Die Zwei-Faktor-Authentifizierung wurde eingerichtet. Scannen Sie den Code unten.',
    'Two-factor setup not initialized' => 'Die Zwei-Faktor-Authentifizierung ist nicht initialisiert.',
    'Invalid two-factor code' => 'Der eingegebene Zwei-Faktor-Code ist ungültig.',
    'Two-factor authentication enabled' => 'Zwei-Faktor-Authentifizierung wurde aktiviert.',
    'Two-factor authentication disabled' => 'Zwei-Faktor-Authentifizierung wurde deaktiviert.',
    'New recovery codes generated' => 'Neue Wiederherstellungscodes wurden erstellt.',
    'Recovery code used. Consider generating new codes' => 'Wiederherstellungscode verwendet. Erwägen Sie, neue Codes zu erzeugen.',
    'Enable two-factor authentication' => 'Zwei-Faktor-Authentifizierung aktivieren',
    'Disable two-factor authentication' => 'Zwei-Faktor-Authentifizierung deaktivieren',
    'Scan this QR code or use the setup key below' => 'Scannen Sie diesen QR-Code oder verwenden Sie den Setup-Schlüssel unten.',
    'Setup Key' => 'Setup-Schlüssel',
    'Recovery Codes' => 'Wiederherstellungscodes',
    'Store these codes somewhere safe. Each code can be used once.' => 'Bewahren Sie diese Codes an einem sicheren Ort auf. Jeder Code kann einmal verwendet werden.',
    'Confirm and enable' => 'Bestätigen und aktivieren',
    'Generate new recovery codes' => 'Neue Wiederherstellungscodes erzeugen',
    'Authentication code' => 'Authentifizierungscode',
    'Enter the code from your authenticator app or a recovery code' => 'Geben Sie den Code aus Ihrer Authenticator-App oder einen Wiederherstellungscode ein.',
    'Verify' => 'Bestätigen',
    'Pending email change to' => 'Ausstehende E-Mail-Änderung zu',
    'Check your inbox to confirm the change' => 'Prüfen Sie Ihren Posteingang, um die Änderung zu bestätigen.',
    'Password must be at least 10 characters with uppercase and a number' => 'Das Passwort muss mindestens 10 Zeichen lang sein und mindestens einen Großbuchstaben und eine Zahl enthalten.',
    'Password updated successfully' => 'Passwort erfolgreich aktualisiert.',
    'We sent a verification link to your new email address' => 'Wir haben einen Bestätigungslink an Ihre neue E-Mail-Adresse gesendet.',
    'We could not send a verification link to the new email address' => 'Der Bestätigungslink konnte nicht an die neue E-Mail-Adresse gesendet werden.',
    'The new email must be different' => 'Die neue E-Mail-Adresse muss sich von der aktuellen unterscheiden.',
    'Invalid or expired email change link' => 'Der Link zur E-Mail-Änderung ist ungültig oder abgelaufen.',
    'Email already in use' => 'Diese E-Mail-Adresse wird bereits verwendet.',
    'Email updated successfully' => 'E-Mail-Adresse erfolgreich aktualisiert.',
    'Current email address' => 'Aktuelle E-Mail-Adresse',
    'New email address' => 'Neue E-Mail-Adresse',
    'Email change description' => 'Ihre aktuelle E-Mail-Adresse bleibt aktiv, bis Sie den Bestätigungslink in der neuen E-Mail-Adresse anklicken.',
    'Email pending description' => 'Bis zur Bestätigung bleibt Ihre bisherige E-Mail-Adresse im System aktiv.',
    'Send verification link' => 'Bestätigungslink senden',
    'Profile email change daily limit exceeded' => 'Sie können Ihre E-Mail-Adresse höchstens 4 Mal innerhalb von 24 Stunden ändern. Bitte versuchen Sie es später erneut.',
    'Profile password change daily limit exceeded' => 'Sie können Ihr Passwort höchstens 4 Mal innerhalb von 24 Stunden ändern. Bitte versuchen Sie es später erneut.',
    'Captcha validation failed' => 'Captcha-Überprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.',
    'At least 10 characters' => 'Mindestens 10 Zeichen',
    'Enabled' => 'Aktiviert',
    'Disabled' => 'Deaktiviert',
    'Profile updated' => 'Profil aktualisiert.',
    'Current Password' => 'Aktuelles Passwort',
    'New Password' => 'Neues Passwort',


    /*
    |--------------------------------------------------------------------------
    | Styling Slide In
    |--------------------------------------------------------------------------
    |
    | resources/views/layouts/sidebar.blade.php
    |
    */

    'Settings' => 'Einstellungen',
    'Scheme' => 'Farbschema',
    'Auto' => 'Auto',
    'Dark' => 'Dunkel',
    'Light' => 'Hell',
    'Color Customizer' => 'Farbanpassung',
    'Sidebar Color' => 'Farbe der Seitenleiste',
    'Default' => 'Standard',
    'Color' => 'Farbe',
    'Transparent' => 'Transparent',
    'Sidebar Types' => 'Seitenleistentypen',
    'Mini' => 'Mini',
    'Hover' => 'Schwebend',
    'Boxed' => 'Verpackt',
    'Sidebar Active Style' => 'Aktiver Stil der Seitenleiste',
    'Rounded One Side' => 'Eine Seite abgerundet',
    'Rounded All' => 'Alles abgerundet',
    'Pill One Side' => 'Pille eine Seite',
    'Pill All' => 'Pille Alle',


    /*
    |--------------------------------------------------------------------------
    | Site customization
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/site.blade.php
    |
    */

    'Home' => 'Startseite',
    'Add Link' => 'Block hinzufügen',
    'Administration' => 'Verwaltung',
    'Admin' => 'Administrator',
    'Config' => 'Konfig',
    'Manage Users' => 'Benutzerverwaltung',
    'Footer Pages' => 'Fußzeilen',
    'Site Customization' => 'Seitenanpassung',
    'Site Logo' => 'Site-Logo',
    'Personalization' => 'Personalisierung',
    'Links' => 'Blöcke',
    'Appearance' => 'Aussehen',
    'Themes' => 'Themen',
    'Header' => 'Header',
    'Header description' => 'Add a photo header behind your profile picture.',
    'Header image' => 'Header image',
    'Header helper' => 'Header: bedeckt den oberen Bereich deiner Seite und die obere Hälfte deines Profilbildes.',
    'Hero helper' => 'Hero: erscheint oben auf deiner Seite über Profilbild, Beschreibung und Icons.',
    'Header default theme notice' => 'Available only on solid color backgrounds and themes that support headers; not available when background images are used.',
    'Show header on page' => 'Show header on page',
    'Upload image' => 'Upload image',
    'Remove header image' => 'Entfernen',
    'Site logo' => 'Seitenlogo',
    'Favicon' => 'Favicon',
    'Home message' => 'Startseitennachricht',


    /*
    |--------------------------------------------------------------------------
    | Navbar
    |--------------------------------------------------------------------------
    |
    | resources/views/layouts/sidebar.blade.php
    |
    */
    
    'View Page' => 'Seite anzeigen',
    'hub.publish.panel_title' => 'Seitensichtbarkeit',
    'hub.publish.panel_description' => 'Steuere, ob Besucher diese Seite aufrufen können.',
    'hub.publish.status_unpublished' => 'Nicht veröffentlicht',
    'hub.publish.status_published' => 'Veröffentlicht',
    'hub.publish.confirm_publish_title' => 'Seite veröffentlichen?',
    'hub.publish.confirm_publish_body' => 'Deine Seite wird öffentlich sichtbar.',
    'hub.publish.confirm_unpublish_title' => 'Seite verstecken?',
    'hub.publish.confirm_unpublish_body' => 'Deine Seite wird für Besucher nicht mehr erreichbar.',
    'hub.publish.action_publish' => 'Veröffentlichen',
    'hub.publish.action_unpublish' => 'Verstecken',
    'hub.publish.only_visible_to_you' => 'Nur für dich sichtbar',
    'hub.publish.publish_here_hint' => 'Veröffentliche deine Seite :link.',
    'hub.publish.here_link_label' => 'hier',
    'hub.publish.not_available' => 'Diese Seite ist nicht verfügbar',
    'hub.publish.success_published' => 'Seite veröffentlicht.',
    'hub.publish.success_unpublished' => 'Seite versteckt.',
    'hub.publish.suspended' => 'Hub ist gesperrt',
    'hub.publish.error_unavailable' => 'Veröffentlichungs-Einstellungen sind noch nicht verfügbar. Bitte Migrationen ausführen.',
    'Share your profile' => 'Teile dein Profil',
    'Share your profile:' => 'Teile dein Profil:',
    'Error sharing:' => 'Fehler beim Teilen:',
    'Text copied to clipboard!' => 'Text in die Zwischenablage kopiert!',
    'Error copying text:' => 'Fehler beim Kopieren des Textes:',
    'QR Code' => 'QR-Code',
    'Scan QR Code' => 'QR-Code scannen',
    'QR code could not be generated' => 'QR-Code konnte nicht generiert werden',
    'Reason:' => 'Grund:',

    # QR Code dropdown
    'Close' => 'Schließen',
    'Dismiss' => 'Verwerfen',
    
    # Notification dropdown
    'All Notifications' => 'Alle Benachrichtigungen',

    # Updater dropdown
    'Updater' => 'Updater',
    'Beta Mode' => 'Beta-Modus',
    'Local version' => 'Lokale Version',
    'Latest beta' => 'Neueste Beta',
    'Run updater' => 'Updaten',
    'Update available' => 'Update verfügbar',
    'Up to date' => 'Auf dem neuesten Stand',
    'Check again' => 'Erneut überprüfen',

    # User section in navbar
    'Administrator' => 'Administrator',
    'Verified user' => 'Verifizierter Benutzer',
    'User' => 'Benutzer',
    'Profile' => 'Profil',
    'Styling' => 'Styling',
    'Logout' => 'Ausloggen',


    /*
    |--------------------------------------------------------------------------
    | Dashboard Page
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/index.blade.php
    |
    */

    # Header with image
    'Hi' => 'Hi',
    'stranger' => 'Fremder',
    'welcome' => 'Willkommen bei :appName!',
    'Set a handle' => 'Legen Sie einen Handle fest',

    # Dashboard Page
    'Total Links:' => 'Gesamte links:',
    'Link Clicks:' => 'Linkklicks:',
    'View/Edit Links' => 'Links anzeigen/bearbeiten',
    'Top Links:' => 'Top-Links:',
    'You haven’t added any links yet' => 'Sie haben noch keine Links hinzugefügt.',
    'clicks' => 'Klicks',
    'Site statistics:' => 'Seitenstatistik:',
    'Total links' => 'Links',
    'Total clicks' => 'Klicks',
    'Total users' => 'Benutzer',
    'Registrations:' => 'Registrierungen:',
    'Last 30 days' => 'Letzte 30 Tage',
    'Last 7 days' => 'Letzten 7 Tage',
    'Last 24 hours' => 'Letzte 24 Stunden',
    'Active users:' => 'Aktive Benutzer:',
    
    

    /*
    |--------------------------------------------------------------------------
    | Button Editor
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/button-editor.blade.php
    |
    */

    'Button Editor' => 'Button-Editor',
    'Back' => 'Zurück',
    'Custom Button' => 'Benutzerdefinierter Button',
    'CSS' => 'CSS',
    'background' => 'Hintergrund',
    'gradient' => 'Gradient',
    'Show CSS' => 'CSS anzeigen',
    'Custom CSS' => 'Benutzerdefiniertes CSS',
    'Save' => 'Speichern',
    'Reset to default' => 'Zurücksetzen',
    'Result' => 'Ergebnis:',
    'Custom Icon' => 'Benutzerdefiniertes Symbol',
    'Custom Alert' => 'Der Short Code Ihres benutzerdefinierten Symbols enthält nicht die Zeichenfolge „fa-“. Verwenden Sie immer Symbole im Format: fa-ghost, zum Beispiel.',
    'cb.description.1-4' => 'Benutzerdefinierte Symbole können über Font Awesome zu Schaltflächen hinzugefügt werden. Sie können jedes Symbol aus der unten stehenden Liste verwenden. Klicken Sie auf die Schaltfläche "Alle Symbole anzeigen", um auf diese Liste zuzugreifen. Jedes Symbol in dieser Liste hat einen Kurzcode, den Sie kopieren und im Feld für benutzerdefinierte Symbole eingeben können.',
    'cb.description.2-4' => 'Jeder Kurzcode für ein Symbol besteht aus einem Präfix und einem Hauptteil. Wenn der Kurzcode kein Markensymbol ist, können Sie den Code einfach im Format "fa-Symbolname" eingeben. Die Formatierung "fa-..." ist hier wichtig. Zum Beispiel "fa-code".',
    'cb.description.3-4' => 'Wenn der Kurzcode ein Markensymbol ist, ist es wichtig, vor dem Kurzcode einen "fab" einzuschließen. Auch hier gilt immer noch die Formatierung "fa-...". Zum Beispiel "fab fa-github".',
    'cb.description.4-4' => 'Um Ihren Symbolen Farbe zu verleihen, können Sie einfach den Farbnamen ausschreiben oder den HEX-Wert vor dem Symbol eingeben, gefolgt von einem Semikolon. Hier ist es wichtig, die Farbe vor dem Symbolkurzcode anzugeben und der Farbcode muss mit einem Semikolon enden.<br>Sie finden eine Liste der verfügbaren Farben <a href="https://www.w3schools.com/cssref/css_colors.asp" target="_blank">hier</a>.',
    'Style' => 'Stil',
    'Prefix' => 'Präfix',
    'Icon' => 'Symbol',
    'Short Code' => 'Short Code',
    'Regular' => 'Regulär',
    'Brands' => 'Marken',
    'Color name' => 'Farbname',
    'Color HEX' => 'Farbe HEX',
    'Color HEX1' => 'Farbe HEX',
    'Update icon' => 'Symbol aktualisieren',
    'See all icons' => 'Alle Symbole anzeigen',
    'be.preview_title' => 'Beispiel',
    'be.text_color_hint' => 'Hinweis: Diese Farbe gilt auch für Icon und Rahmen.',
    'be.global_style_note' => 'dieser Stil wird global auf benutzerdefinierte Links und Embed-Fallback-Links angewendet.',
    'be.template_button_defaults_hint' => 'Aktives Template: :template. Wenn du den Button zurücksetzt und speicherst, wird der voreingestellte Button dieser Vorlage verwendet.',
    'be.template_button_locked_hint' => 'Aktives Template: :template. Dieses Template sperrt Button-Styling; gespeicherte Button-Styles bleiben erhalten, werden aber auf der öffentlichen Seite nicht angewendet.',
    'be.text_color' => 'Textfarbe',
    'be.button_text_color_mode' => 'Button-Textfarbenmodus',
    'be.white' => 'Weiß',
    'be.black' => 'Schwarz',
    'be.custom' => 'Individuell',
    'be.use_accent_color' => 'Akzentfarbe übernehmen',
    'be.font' => 'Schriftart',
    'be.coming_soon' => 'Demnächst verfügbar',
    'be.background_and_gradient' => 'Hintergrund & Verlauf',
    'be.gradient' => 'Verlauf',
    'be.color_1' => 'Farbe 1',
    'be.color_2' => 'Farbe 2',
    'be.color_3' => 'Farbe 3',
    'be.add_color_3' => 'Farbe 3 hinzufügen',
    'be.border' => 'Rahmen',
    'be.enable_border' => 'Rahmen aktivieren',
    'be.thickness' => 'Stärke',
    'be.radius' => 'Radius',


    /*
    |--------------------------------------------------------------------------
    | Edit Link Page
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/edit-link.blade.php
    |
    */

    'Edit' => 'Bearbeiten',
    'Add' => 'Neu',
    'Block' => 'Block',
    'Blocks' => 'Blöcke:',
    'Select Block' => 'Block wählen',
    'Toggle Dropdown' => 'Dropdown-Liste umschalten',
    'Cancel' => 'Abbrechen',
    'Save and Add More' => 'Speichern & weiter',
    'Click to change link blocks' => 'Blöcke ändern',
    'Click for a list of available link blocks' => 'Liste der verfügbaren Blöcke',


    /*
    |--------------------------------------------------------------------------
    | Links Page
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/links.blade.php
    |
    */

    'My Links' => 'Meine Blöcke',
    'Add new Link' => 'Neuen Block hinzufügen',
    'No Link Added' => 'Sie haben noch keine Blöcke hinzugefügt.',
    'Download' => 'Herunterladen',
    'Clicks' => 'Klicks',
    'Preview' => 'Vorschau:',
    'No compatible browser' => 'Ihr Browser ist nicht kompatibel',
    'Page Icons' => 'Seitensymbole',
    'Save links' => 'Blöcke speichern',

    # Tooltips
    'Customize' => 'Anpassen',
    'Delete' => 'Löschen',
    'Clear icon cache' => 'Symbol-Cache leeren',
    
    'confirm_delete' => 'Sind Sie sicher, dass Sie :title löschen möchten?',


    /*
    |--------------------------------------------------------------------------
    | "My Profile"/Appearance Page
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/page.blade.php
    |
    */

    'My Profile' => 'Visitenkarte',
    'Profile Picture' => 'Logo',
    'Page URL' => 'Seiten-URL',
    'No page url notice' => 'Sie haben keine Seiten-URL',
    'Display name' => 'Name',
    'Name' => 'Name',
    'Page Description' => 'Kurzbeschreibung',
    'Show checkmark' => 'Häkchen anzeigen',
    'disableverified' => 'Sie sind ein verifizierter Benutzer. Mit dieser Einstellung können Sie Ihr Häkchen auf Ihrer Seite ausblenden.',
    'Show share button' => 'Teilen-Button anzeigen',
    'disablesharebutton' => 'Mit dieser Einstellung können Sie die Schaltfläche „Teilen“ auf Ihrer Seite ausblenden.',
    'Open links in new tab' => 'Links in neuem Tab öffnen',
    'openlinksnewtab' => 'Diese Einstellung bestimmt, ob Ihre Links auf Ihrer Seite im gleichen oder einem neuen Tab geöffnet werden.',
    'page.show_profile_picture_title' => 'Logo auf Website anzeigen',
    'page.show_profile_picture_description' => 'Deaktivieren Sie dies, wenn Sie das Logo auf Ihrer Visitenkarte ausblenden möchten, ohne das hochgeladene Bild zu entfernen.',
    'page.profile_layout_title' => 'Anordnung von Logo, Titel & Beschreibung',
    'page.profile_layout_description' => 'Wählen Sie zwischen Link in Bio mit Fokus auf das Logo, Business mit Fokus auf den Titel und Business Header mit Fokus auf die Beschreibung.',
    'page.profile_layout_standard' => 'Link in Bio (Fokus Logo)',
    'page.profile_layout_business' => 'Business (Fokus Titel)',
    'page.profile_layout_business_header_focus_description' => 'Business Header (Fokus Beschreibung)',
    'page.profile_layout_locked' => 'Dieses Template verwendet eine feste Kopfbereichs-Anordnung.',
    'page.business_hero_only_notice' => 'In Business-Layouts ist nur Hero verfügbar. Der Header-Modus ist deaktiviert.',
    'page.text_accent_color_title' => 'Text- & Akzentfarbe',
    'page.text_accent_color_description' => 'Legt die Farbe für Namen, Beschreibung, Footer-Links, Teilen-Button, Button-Ränder und Seiten-Icons fest.',
    'page.text_accent_color_mode_aria' => 'Text- und Akzentfarbenmodus',
    'page.colors_section_title' => 'Farben',
    'page.text_accent_color_scope_hint' => 'Gilt für Text, Icons und UI-Elemente - Buttons werden separat im Button-Editor gestaltet.',
    'page.custom_color' => 'Individuelle Farbe',
    'page.use_button_text_color' => 'Button-Textfarbe übernehmen',
    'page.hide_branding_title' => 'Branding ausblenden',
    'page.hide_branding_description' => 'Blendet den Branding-Hinweis im Footer für diese öffentliche Seite aus. Wenn für Ihre Agentur kein eigenes Logo konfiguriert ist, wird standardmäßig Wayvio verwendet.',
    'page.hide_branding_available_from_basic' => 'Ab Basic',
    'page.hide_branding_upsell_notice' => 'Verfügbar ab Basic. Upgrade dein Abo, um das Branding auszublenden.',
    'page.hide_branding_upsell_link' => 'Zu Subscriptions',
    'forms.tier_locked.basic' => 'Kontaktformular verfügbar ab Basic.',
    'Cookie settings' => 'Cookie-Einstellungen',
    'imprint.default_title' => 'Impressum',
    'imprint.country_germany' => 'Deutschland',
    'imprint.intro_tmg' => 'Angaben gemäß § 5 TMG',
    'imprint.group.provider' => 'Anbieter',
    'imprint.group.contact' => 'Kontakt',
    'imprint.group.legal' => 'USt-ID & weitere rechtliche Angaben',
    'imprint.represented_by' => 'Vertreten durch',
    'imprint.vat_id' => 'Umsatzsteuer-Identifikationsnummer',
    'imprint.business_id' => 'Wirtschafts-Identifikationsnummer',
    'imprint.register' => 'Register',
    'imprint.register_court' => 'Registergericht',
    'imprint.register_number' => 'Registernummer',
    'imprint.supervisory_authority' => 'Aufsichtsbehörde',
    'imprint.chamber' => 'Kammer',
    'imprint.professional_title' => 'Berufsbezeichnung',
    'imprint.professional_state' => 'Verliehen in',
    'imprint.professional_rules' => 'Berufsrechtliche Regelungen',
    'imprint.liquidation' => 'Abwicklung / Liquidation',
    'imprint.adr' => 'Alternative Streitbeilegung',
    'imprint.odr' => 'EU-Online-Streitbeilegung',
    'imprint.av_member_state' => 'Audiovisuelle Mediendienste (Mitgliedstaat)',
    'imprint.av_authority' => 'Audiovisuelle Mediendienste (Aufsichtsbehörde)',
    'imprint.back_to_page' => '← Zurück zur Seite',
    'imprint.editor.notice_title' => 'Wichtiger Hinweis:',
    'imprint.editor.notice_intro' => 'Die vorgefertigten Impressum-Felder orientieren sich an',
    'imprint.editor.notice_link_text' => 'IHK Leipzig: Pflichtangaben im Internet - Die Impressumspflicht',
    'imprint.editor.notice_line1' => 'Es wird keine Haftung oder Garantie für Vollständigkeit und rechtliche Richtigkeit übernommen.',
    'imprint.editor.notice_line2' => 'Das Impressum wird von dir in eigener Verantwortung angelegt und gepflegt.',
    'imprint.editor.section.basic' => 'Grundangaben',
    'imprint.editor.section.advanced' => 'Erweiterte Angaben',
    'imprint.editor.toggle_advanced' => 'Erweiterte Angaben ein-/ausblenden',
    'imprint.editor.card.name' => 'Name',
    'imprint.editor.card.address' => 'Anschrift',
    'imprint.editor.card.contact' => 'Kontakt',
    'imprint.editor.card.contact_form' => 'Kontaktformular',
    'imprint.editor.card.vat' => 'Umsatzsteuer-ID',
    'imprint.editor.card.name_extended' => 'Name (erweitert)',
    'imprint.editor.card.register' => 'Registereintrag (optional)',
    'imprint.editor.card.supervisory' => 'Aufsicht & reglementierte Berufe (optional)',
    'imprint.editor.card.tax_company' => 'Steuer- und Unternehmensangaben (optional)',
    'imprint.editor.card.dispute_av' => 'Streitbeilegung & audiovisuelle Dienste (optional)',
    'imprint.editor.card.additional' => 'Flexibles Zusatzfeld (optional)',
    'imprint.editor.label.company_name' => 'Name des Unternehmens / Name',
    'imprint.editor.label.phone_optional' => 'Telefon',
    'imprint.editor.label.enable_imprint_contact_form' => 'Impressum-Kontaktformular aktivieren',
    'imprint.editor.label.vat_optional' => 'Umsatzsteuer-Identifikationsnummer (USt-IdNr.) (optional)',
    'imprint.editor.label.legal_form_optional' => 'Rechtsform (optional)',
    'imprint.editor.label.represented_by_optional' => 'Vertretungsberechtigte Person(en) (optional)',
    'imprint.editor.label.register_type' => 'Registerart / Registereintrag',
    'imprint.editor.label.register_court' => 'Registergericht',
    'imprint.editor.label.register_number' => 'Registernummer',
    'imprint.editor.label.supervisory_authority' => 'Zuständige Aufsichtsbehörde',
    'imprint.editor.label.chamber' => 'Zuständige Kammer',
    'imprint.editor.label.professional_title' => 'Berufsbezeichnung',
    'imprint.editor.label.professional_state' => 'Staat der Verleihung der Berufsbezeichnung',
    'imprint.editor.label.professional_rules' => 'Berufsrechtliche Regelungen / Verlinkung',
    'imprint.editor.label.business_id' => 'Wirtschafts-Identifikationsnummer',
    'imprint.editor.label.liquidation' => 'Abwicklung / Liquidation (bei AG, KGaA, GmbH)',
    'imprint.editor.label.adr_notice' => 'Hinweis zur alternativen Streitbeilegung',
    'imprint.editor.label.odr_url' => 'Link zur EU-Online-Streitbeilegung (ODR)',
    'imprint.editor.label.av_member_state' => 'Audiovisuelle Mediendienste: Sitzland / Mitgliedstaat',
    'imprint.editor.label.av_authority' => 'Audiovisuelle Mediendienste: zuständige Regulierungs- und Aufsichtsbehörde',
    'imprint.editor.label.additional_text' => 'Weitere rechtliche Angaben (Freitext)',
    'imprint.editor.hint.company_name_required' => 'Pflichtfeld für die Anbieterkennzeichnung.',
    'imprint.editor.hint.contact_minimum_two' => 'Nutze mindestens 2 Kontaktwege (z. B. E-Mail, Telefon oder Kontaktformular).',
    'imprint.editor.hint.contact_form_note' => 'Zeigt ein gesichertes Wayvio-Formular auf der öffentlichen Impressumsseite. Nachrichten erscheinen unter Forms. Verfügbar ab Basic; im Free-Tier ist das Formular nicht sichtbar.',
    'imprint.editor.hint.vat_optional_note' => 'Optional. Die normale Steuernummer gehört nicht ins Impressum.',
    'imprint.editor.hint.adr_example' => 'Beispiel aus dem IHK-Merkblatt: „Zur Teilnahme an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle sind wir nicht verpflichtet und nicht bereit.“',
    'imprint.editor.placeholder.register_type' => 'z. B. Handelsregister, Vereinsregister',
    'legal.banner.title' => 'Rechtliches',
    'legal.banner.subtitle' => 'Impressum und Datenschutzerklärung getrennt verwalten.',
    'legal.hub_context_changed' => 'Hub-Kontext wurde geändert. Öffnen Sie Legal für den aktiven Hub erneut, bevor Sie speichern.',
    'legal.editor_unavailable' => 'Der Legal-Editor ist nicht verfügbar.',
    'legal.editor_logic_missing' => 'Die Legal-Editor-Logik wurde nicht gefunden.',
    'legal.imprint.saved' => 'Impressum wurde aktualisiert.',
    'legal.privacy.section_title' => 'Datenschutzerklärung (Layer-Modell)',
    'legal.privacy.disclaimer_title' => 'Hinweis:',
    'legal.privacy.disclaimer_text' => 'Wayvio stellt nur Vorlagen und technische Werkzeuge bereit. Für Vollständigkeit, Richtigkeit und Aktualität deiner Datenschutzerklärung bist du selbst verantwortlich. Wayvio leistet keine Rechtsberatung und übernimmt keine Haftung.',
    'legal.privacy.live_output' => 'Live-Ausgabe',
    'legal.privacy.layer1.title' => 'Verantwortliche Stelle und Kontaktdaten',
    'legal.privacy.layer1.description' => 'Trage hier die Pflichtangaben für den Verantwortlichen ein. Diese Daten werden automatisch in die Datenschutzerklärung übernommen.',
    'legal.privacy.layer1.sync_description' => 'Bei aktivem Schalter wird der Wert automatisch aus dem Impressum übernommen. Für abweichende Angaben den Schalter deaktivieren.',
    'legal.privacy.layer1.sync_from_imprint' => 'Aus dem Impressum übernehmen',
    'legal.privacy.layer1.source.imprint' => 'Quelle: Impressum',
    'legal.privacy.layer1.source.custom' => 'Quelle: Eigener Wert',
    'legal.privacy.layer1.label.controller_name' => 'Name / Firma *',
    'legal.privacy.layer1.label.street' => 'Straße und Hausnummer *',
    'legal.privacy.layer1.label.postal_code' => 'PLZ *',
    'legal.privacy.layer1.label.city' => 'Ort *',
    'legal.privacy.layer1.label.country' => 'Land *',
    'legal.privacy.layer1.label.email' => 'E-Mail *',
    'legal.privacy.layer1.label.phone_optional' => 'Telefon (optional)',
    'legal.privacy.layer1.label.dpo_name_optional' => 'Datenschutzbeauftragter Name (optional)',
    'legal.privacy.layer1.label.dpo_contact_optional' => 'Datenschutzbeauftragter Kontakt (optional)',
    'legal.privacy.layer1.action.save' => 'Angaben speichern',
    'legal.privacy.layer1.saved' => 'Datenschutz-Angaben gespeichert.',
    'legal.privacy.layer2.title' => 'Datenschutzerklärung bearbeiten',
    'legal.privacy.layer2.description' => 'Passe den Text an dein Angebot an und prüfe die Vorschau. Mit „Vorlage neu erzeugen“ setzt du auf den aktuellen Standard zurück.',
    'legal.privacy.layer2.notice_title' => 'Abschnitt 1 wird automatisch erzeugt.',
    'legal.privacy.layer2.notice_text' => 'Name, Adresse, E-Mail und optional Telefon kommen aus „Verantwortliche Stelle und Kontaktdaten“. Bei aktivem Schalter gilt der Impressumswert, sonst der in Layer 1 gespeicherte eigene Wert.',
    'legal.privacy.layer2.notice_cta' => 'Abschnitt 1 jetzt bearbeiten',
    'legal.privacy.layer2.label.privacy_text' => 'Datenschutzerklärung',
    'legal.privacy.layer2.label.privacy_text_section2' => 'Datenschutzerklärung ab Abschnitt 2',
    'legal.privacy.layer2.action.save' => 'Text speichern',
    'legal.privacy.layer2.action.regenerate' => 'Vorlage neu erzeugen',
    'legal.privacy.layer2.saved' => 'Datenschutzerklärung gespeichert.',
    'legal.privacy.layer2.regenerated' => 'Datenschutz-Vorlage neu erzeugt.',
    'legal.privacy.layer3.title' => 'Embed-Dienste festlegen',
    'legal.privacy.layer3.description' => 'Lege fest, ob der Embed-Abschnitt automatisch von Wayvio gepflegt wird oder ob du ihn selbst übernimmst. Das ist der letzte Schritt vor der Veröffentlichung.',
    'legal.privacy.layer3.option.auto' => 'Option A - Automatisch (zentral gepflegter Embed-Abschnitt)',
    'legal.privacy.layer3.option.self' => 'Option B - Selbst verantwortlich (inkl. Änderungen bei Embed-Diensten, Technik und Rechtslage)',
    'legal.privacy.layer3.self_ack' => 'Ich bestätige, dass ich bei Option B die Vollständigkeit selbst sicherstelle.',
    'legal.privacy.layer3.self_ack_required' => 'Bitte bestätigen Sie den Hinweis bei „Selbst verantwortlich“.',
    'legal.privacy.layer3.action.save' => 'Einstellungen speichern',
    'legal.privacy.layer3.saved' => 'Embed-Einstellungen gespeichert.',
    'legal.privacy.action.save_all' => 'Datenschutzerklärung speichern',
    'legal.privacy.saved_all' => 'Datenschutzerklärung gespeichert.',
    'legal.privacy.layer2.locale_hint' => 'Der Text wird in deiner aktuellen Kontosprache gespeichert. Nach einem Sprachwechsel „Vorlage neu erzeugen" klicken.',
    'legal.privacy.preview_rule' => 'Vorschau-Regel: Abschnitt 1 wird immer automatisch aus Layer 1 erzeugt; dieser Editor steuert Abschnitt 2 und folgende.',


    /*
    |--------------------------------------------------------------------------
    | Personal Settings Page
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/profile.blade.php
    |
    */

    'Account Settings' => 'Account Einstellungen',
    'Language preference' => 'Spracheinstellungen',
    'Language preference description' => 'Wählen Sie die Sprache für Ihr Konto. Bei „Automatisch“ wird die Browsersprache verwendet.',
    'Automatic (browser)' => 'Automatisch (Browser)',
    'English' => 'Englisch',
    'German' => 'Deutsch',
    'Language updated successfully' => 'Sprache erfolgreich aktualisiert.',
    'Current Plan' => 'Aktueller Plan',
    'Expires' => 'Läuft ab',
    'Active' => 'Aktiv',
    'In grace period' => 'In der Schonfrist',
    'Expired' => 'Abgelaufen',
    'Free' => 'Kostenlos',
    'No expiry (free or lifetime)' => 'Kein Ablaufdatum (kostenlos oder lebenslang)',
    'Change email' => 'E-Mail Ändern',
    'Change password' => 'Kennwort ändern',
    'Export user data' => 'Benutzerdaten exportieren',
    'Export your user data' => 'Exportieren Sie Ihre Benutzerdaten, um sie auf eine andere Instanz zu übertragen.',
    'Export all data' => 'Alle Daten exportieren',
    'Export links only' => 'Nur Links exportieren',
    'Import user data' => 'Benutzerdaten importieren',
    'import.user.alert' => 'Sind Sie sicher, dass Sie diese Datei importieren möchten? Diese Aktion wird alle Ihre aktuellen Daten, einschließlich Links, ersetzen!',
    'Import your user data from another instance' => 'Importieren Sie Ihre Benutzerdaten von einer anderen Instanz.',
    'Import your user data' => 'Importieren Sie Ihre Benutzerdaten von einer anderen Instanz.',
    'Import' => 'Importieren',
    'Delete your account' => 'Lösche deinen Account',
    'You are about to delete' => 'Sie sind dabei, Ihr Konto zu löschen!',
    'You are about to delete This action cannot be undone' => 'Sie sind dabei, Ihr Konto zu löschen! Diese Aktion kann nicht rückgängig gemacht werden.',
    'Delete account' => 'Konto löschen',
    'Delete account description' => 'Diese Aktion löscht Ihr Konto und die zugehörigen Daten dauerhaft. Zur Bestätigung sind Ihr aktuelles Passwort und eine ausdrückliche Bestätigung erforderlich.',
    'Delete account export note' => 'wenn Sie vor dem Löschen noch eine Sicherung herunterladen möchten.',
    'Delete account popup title' => 'Bestätigen Sie die endgültige Kontolöschung.',
    'Delete account popup subscription warning' => 'Alle bestehenden Abos werden sofort gekündigt.',
    'Delete account popup refund warning' => 'Es erfolgt keine Rückerstattung bereits gezahlter Beträge.',
    'Delete account popup access warning' => 'Nach dem Löschen ist kein weiterer Zugriff auf das Konto möglich.',
    'Delete account popup data warning' => 'Alle zugehörigen Kontodaten werden dauerhaft gelöscht.',
    'Type DELETE to confirm' => 'Geben Sie DELETE zur Bestätigung ein',
    'Delete account two-factor description' => 'Bei aktivierter Zwei-Faktor-Authentifizierung ist zusätzlich ein aktueller Code oder ein Wiederherstellungscode erforderlich.',
    'You may only delete your own account' => 'Sie können nur Ihr eigenes Konto löschen.',
    'This account cannot be deleted' => 'Dieses Konto kann nicht gelöscht werden.',
    'Account deletion blocked by active subscription' => 'Das Konto konnte nicht gelöscht werden, weil die Abo-Kündigung aktuell nicht bestätigt werden konnte. Bitte versuchen Sie es erneut.',
    'We could not delete your account right now' => 'Ihr Konto konnte derzeit nicht gelöscht werden. Bitte versuchen Sie es erneut.',
    'Account deleted successfully' => 'Ihr Konto wurde dauerhaft gelöscht.',
    
    # Alerts
    'Profile updated successfully!' => 'Profil erfolgreich aktualisiert!',
    'An error occurred while updating your profile.' => 'Beim Aktualisieren Ihres Profils ist ein Fehler aufgetreten.',
    
    'That handle has already been taken' => 'Dieser Name ist bereits vergeben.',
    'The selected file must be an image' => 'Die ausgewählte Datei muss ein Bild sein.',
    'The image must be' => 'Unterstützte Formate:',
    'The image size should not exceed 2MB' => 'Die Bildgröße darf 2 MB nicht überschreiten.',
    'Agency logo' => 'Agentur-Logo',
    'Hero/header image' => 'Hero/Header-Bild',
    'upload.limit.notice' => 'Limit: max. :size MB, max. :width x :height px.',
    'upload.validation.max' => ':label darf :size MB nicht überschreiten.',
    'upload.validation.dimensions' => ':label darf maximal :width x :height px haben.',
    'upload.request.too_large' => 'Der Upload ist zu groß. Bitte halten Sie die ausgewiesenen Limits ein (je nach Bildtyp bis :size MB).',
    'Please select an image' => 'Bitte wählen Sie ein Bild aus.',


    /*
    |--------------------------------------------------------------------------
    | Themes Page
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/theme.blade.php
    |
    */

    'Select a theme' => 'Design',
    'Select theme' => 'Thema wählen',
    'Template' => 'Vorlage',
    'Custom background' => 'Benutzerdefinierter Hintergrund',
    'Background image' => 'Hintergrundbild',
    'Custom image' => 'Benutzerdefiniertes Bild',
    'No image selected' => 'Kein Bild ausgewählt',
    'Remove background' => 'Hintergrund entfernen',
    'Background base' => 'Hintergrundbasis',
    'Solid color' => 'Einfarbig',
    'Solid background color' => 'Einfarbige Hintergrundfarbe',
    'iPhone edge color' => 'iPhone Randfarbe',
    'iPhone edge color helper' => 'Auf iPhones oben und unten im Browser sichtbar, auf Desktops unsichtbar.',
    'Template helper' => 'Verwenden Sie eine vorhandene Vorlage als Hintergrund.',
    'Base color helper' => 'Wird verwendet, wenn kein Bild vorhanden ist oder der Bildmodus deaktiviert ist.',
    'Base layer' => 'Basis-Ebene',
    'Image helper text' => 'Uploads bleiben privat und erscheinen im Seitenhintergrund.',
    'Gradient layer' => 'Verlaufs-Ebene',
    'Enable gradient' => 'Verlauf aktivieren',
    'gradient_accent_hint' => 'Wird automatisch aus der Basisfarbe generiert.',
    'Color stop' => 'Farbstopp',
    'Gradient direction' => 'Verlaufsrichtung',
    'Gradient solid only' => 'Der Verlauf ist nur für einfarbige Hintergründe verfügbar.',
    'To bottom' => 'Nach unten',
    'To top' => 'Nach oben',
    'To right' => 'Nach rechts',
    'To left' => 'Nach links',
    'To top right' => 'Nach oben rechts',
    'To top left' => 'Nach oben links',
    'To bottom right' => 'Nach unten rechts',
    'To bottom left' => 'Nach unten links',
    'Add third color' => 'Dritte Farbe hinzufügen',
    'Remove third color' => 'Dritte Farbe entfernen',
    'Transparent overlay' => 'Transparente Überlagerung',
    'Overlay color' => 'Überlagerungsfarbe',
    'Overlay opacity' => 'Deckkraft der Überlagerung',
    'Overlay helper text' => 'Fügt einen halbtransparenten Schatten über Basis- und Verlaufsebene hinzu.',
    'Live preview' => 'Live-Vorschau',
    'Background preview helper' => 'Änderungen werden sofort angezeigt.',
    'Manage themes' => 'Themen verwalten',
    'Loading...' => 'Wird geladen...',
    'Upload themes' => 'Hochladen',
    'Delete themes' => 'Themen löschen',
    'Download themes' => 'Laden Sie Themen herunter',
    'Delete a theme' => 'Löschen Sie ein Thema',


    /*
    |--------------------------------------------------------------------------
    | Theme Updater
    |--------------------------------------------------------------------------
    |
    | resources/views/studio/theme-updater.blade.php
    |
    */

    'Theme Updater' => 'Theme-Updater',
    'Theme name' => 'Themenname:',
    'Update status' => 'Update Status:',
    'Version' => 'Ausführung:',
    'Error!' => 'Fehler!',
    'Update manually' => 'Manuell aktualisieren',
    'Update all themes' => 'Aktualisieren Sie alle Themen',


    /*
    |--------------------------------------------------------------------------
    | Edit User Page
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/edit-user.blade.php
    |
    */
    
    'Edit User' => 'Benutzer bearbeiten',
    'Logo' => 'Logo',
    'Page description' => 'Seitenbeschreibung',
    'Role' => 'Rolle',


    /*
    |--------------------------------------------------------------------------
    | Links Page
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/links.blade.php
    |
    */

    'Title' => 'Titel',


    /*
    |--------------------------------------------------------------------------
    | Links Page (Admin)
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/links.blade.php
    |
    */

    'Link' => 'Link',


    /*
    |--------------------------------------------------------------------------
    | PHP info Page
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/phpinfo.blade.php
    |
    */

    'Information about PHP’s configuration' => 'Informationen zur PHP-Konfiguration',
    'Outputs information about the current state of PHP' => 'Gibt Informationen über den aktuellen Zustand von PHP aus',


    /*
    |--------------------------------------------------------------------------
    | Delete themes page
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/theme.blade.php
    |
    */

    'Delete theme' => 'Theme löschen',


    /*
    |--------------------------------------------------------------------------
    | Manage Users Page
    |--------------------------------------------------------------------------
    |
    | resources/views/panel/users.blade.php
    |
    */

    'Users:' => 'Benutzer:',
    'Search user' => 'Benutzer suchen',
    'ID' => 'ID',
    'E-Mail' => 'Email',
    'Page' => 'Seite',
    'Created at' => 'Hinzugefügt am',
    'Last seen' => 'Zuletzt gesehen',
    'Status' => 'Status',
    'Action' => 'Aktion',
    'N/A' => 'N / A',
    'Pending' => 'Ausstehend',
    'Verified' => 'Verifiziert',
    'Approved' => 'Zugelassen',
    'Add new user' => 'Neuen Benutzer hinzufügen',

    # Tooltips
    'tt.Delete' => 'Löschen',
    'tt.Impersonate' => 'Benutzer nachahmen',
    'tt.Edit' => 'Bearbeiten',
    'tt.All links' => 'Alle Links',

    'confirm.delete.user' => 'Sind Sie sicher, dass Sie diesen Benutzer löschen möchten? \\nDiese Aktion kann nicht rückgängig gemacht werden!',

    # Date Format
    'date.format' => 'd/m/Y',

    'days ago' => 'Vor Tagen',
    '1 day ago' => 'Vor 1 Tag',
    'Today' => 'Heute',
    '1 year ago' => 'vor 1 Jahr',
    'years ago' => 'Jahre zuvor',


    /*
    |--------------------------------------------------------------------------
    | Config Page
    |--------------------------------------------------------------------------
    |
    | resources/views/components/config/
    | resources/views/panel/config-editor.blade.php
    |
    */

    'Advanced Config' => 'Erweiterte Konfiguration',
    'Take Backup' => 'Backup',
    'All Backups' => 'Alle Backups',
    'Diagnosis' => 'Diagnose',

    'Alternative Config Editor' => 'Alternativer Konfigurationseditor',
    'Use the Alternative Config Editor to edit the config directly' => 'Verwenden Sie den Alternative Konfigurationseditor, um die Konfiguration direkt zu bearbeiten',

    'PHP info' => 'PHP-Info',
    'Display debugging information about your PHP setup' => 'Zeigt Debugging-Informationen zu Ihrem PHP-Setup an',

    'Jump directly to:' => 'Direkt springen zu:',

    'Application' => 'Anwendung',
    'Panel settings' => 'Panel-Einstellungen',
    'Security' => 'Sicherheit',
    'Advanced' => 'Expertenoptionen',
    'SMTP' => 'SMTP',
    'Footer links' => 'Fußzeilen-Links',
    'Debug' => 'Debug',
    'Language' => 'Sprache',

    'default' => 'Standard',
    'Apply' => 'Anwenden',

    'AC.description' => 'Ermöglicht die Bearbeitung des Frontends Ihrer Website. Diese Datei ermöglicht unter anderem die Anpassung von: Startseite, Links, Titeln, Google Analytics und Meta-Tags.',
    'Advanced Configuration file.' => 'Erweiterte Konfigurationsdatei.',
    'Restore defaults' => 'Standardeinstellungen wiederherstellen',

    'Backup' => 'Sicherung',
    'You can back up your entire instance:' => 'Sie können Ihre gesamte Instanz sichern:',
    'The backup system won’t save more than two backups at a time' => 'Das Backup-System speichert nicht mehr als zwei Backups gleichzeitig.',
    'Backup Instance' => 'Backup-Instanz',

    'wtrue' => 'Alles funktioniert wie erwartet!',
    'wfalse' => 'In diese Datei kann nicht geschrieben werden. Dies kann den ordnungsgemäßen Betrieb beeinträchtigen.',
    'utrue' => 'Ihre Sicherheit ist gefährdet. Auf diese Datei kann jeder zugreifen. Es besteht sofortiger Handlungsbedarf!',
    'ufalse' => 'Alles funktioniert wie erwartet!',
    'unull' => 'Etwas ist schief gelaufen. Dies kann normal sein, wenn Sie hinter einem Proxy- oder Docker-Container arbeiten.',
    'Debugging information' => 'Debugging-Informationen',
    'security.risk' => 'Ihre Sicherheit ist gefährdet. Einige Dateien können von jedem eingesehen werden. Sofortiges Handeln ist erforderlich! Klicken Sie auf diese Nachricht, um mehr zu erfahren.',
    'security.risk.1-3' => 'Hier können Sie leicht überprüfen, ob kritische Systemdateien von extern zugänglich sind. Es ist wichtig, dass auf diese Dateien nicht zugegriffen werden kann, da ansonsten Benutzerdaten wie Passwörter durchsickern könnten. Einträge, die mit einem',
    'security.risk.2-3' => 'gekennzeichnet sind, können von extern nicht zugegriffen werden. Einträge, die mit einem',
    'security.risk.3-3' => 'gekennzeichnet sind, können von jedem eingesehen werden und erfordern sofortiges Handeln zum Schutz Ihrer Daten.',
    'Hover for more' => 'Mauszeiger über Symbole bewegen.',
    'Write access' => 'Schreibzugriff',
    'Write access.description.1-3' => 'Hier können Sie leicht überprüfen, ob wichtige Systemdateien beschreibbar sind. Dies ist wichtig, damit jede Funktion ordnungsgemäß funktioniert. Einträge, die mit einem',
    'Write access.description.2-3' => 'markiert sind, funktionieren wie erwartet. Einträge, die mit einem',
    'Write access.description.3-3' => 'markiert sind, tun es nicht.',
    'File' => 'Datei',
    'Dependencies' => NULL,
    'Required PHP modules' => 'Erforderliche PHP-Module.',
    'PHP Extension' => 'PHP-Erweiterung',
    'No backups found' => 'Keine Backups gefunden',
    'Backup your instance' => 'Sichern Sie Ihre Instanz',

    'Go back' => 'Geh zurück',

    'Strings with a # in front of them are comments and wont affect anything' => 'Zeichenfolgen mit einem # davor sind Kommentare und haben keinerlei Auswirkungen.',

    'Download your updater backups:' => 'Laden Sie Ihre Updater-Backups herunter:',
    'The server will never store more that two backups at a time' => 'Der Server speichert nie mehr als zwei Backups gleichzeitig.',

    'SMTP.title' => 'Verwenden Sie den integrierten SMTP-Server',
    'SMTP.description' => 'Verwendet den von Wayvio bereitgestellten SMTP-Server. Möglicherweise nicht 100 % zuverlässig. Muss deaktiviert werden, um einen benutzerdefinierten SMTP-Server zu verwenden.',
    'SMTP.description.alt' => '(Speichern Sie die Änderungen mit „Änderungen übernehmen“ unten)',
    'Enable' => 'Aktiv',
    'Custom SMTP server:' => 'Benutzerdefinierter SMTP-Server:',
    'Host' => 'Host',
    'Port' => 'Port',
    'Username' => 'Nutzername',
    'Encryption type' => 'Verschlüsselungstyp',
    'From address' => 'Absenderadresse',
    'Apply changes' => 'Änderungen übernehmen',
    'Test E-Mail setup:' => 'E-Mail-Setup testen:',
    'Send Test E-Mail' => 'Test-E-Mail senden',

    'Debug.title' => 'Debug-Modus',
    'Debug.description' => 'Sollte in einer Produktionsumgebung deaktiviert sein. Nützlich zum Debuggen während des Setups.',

    'DISPLAY_FOOTER_HOME.title' => 'Link zur Startseite in Fußzeile anzeigen',
    'DISPLAY_FOOTER_HOME.description' => 'Aktivieren Sie den Home-Fußzeilenlink.',
    'REGISTER_AUTH.title' => 'Aktivieren Sie die E-Mail-Bestätigung',
    'REGISTER_AUTH.description' => 'Legt fest, ob Benutzer ihre E-Mail-Adresse bei der Registrierung bestätigen müssen.',
    'ALLOW_REGISTRATION.title' => 'Registrierung aktivieren',
    'ALLOW_REGISTRATION.description' => 'Legt fest, ob sich Benutzer auf Ihre Anwendung registrieren können.',
    'NOTIFY_EVENTS.title' => 'Benachrichtigungen über Ereignisse',
    'NOTIFY_EVENTS.description' => 'Zeigt eine Benachrichtigung an, wenn ein Ereignis ausgeführt wird.',
    'NOTIFY_UPDATES.title' => 'Bei Updates benachrichtigen',
    'NOTIFY_UPDATES.description' => 'Zeigt eine Benachrichtigung an, wenn ein neues Update verfügbar ist.',
    'DISPLAY_FOOTER.title' => 'Fußzeile anzeigen',
    'DISPLAY_FOOTER.description' => 'Legt fest, ob die Footer-Links angezeigt werden sollen.',
    'DISPLAY_CREDIT.title' => 'Signatur auf Benutzerseiten anzeigen',
    'DISPLAY_CREDIT.description' => 'Legt fest, ob die Signatur auf den Benutzerseiten angezeigt werden soll.',
    'DISPLAY_CREDIT_FOOTER.title' => 'Signatur in der Fußzeile anzeigen',
    'DISPLAY_CREDIT_FOOTER.description' => 'Legt fest, ob die Signatur in der Fußzeile angezeigt werden soll.',
    'HOME_URL.title' => 'Legen Sie eine Benutzerseite als Startseite fest',
    'HOME_URL.description' => 'Legen Sie eine Benutzerseite als Startseite fest. Dadurch wird die vorherige Startseite nach example.com/home verschoben.',
    'ALLOW_USER_HTML.title' => 'Erweiterte Syntax in Benutzerbeschreibungen zulassen',
    'ALLOW_USER_HTML.description' => 'Dadurch können Benutzer spezielle Formatierungen wie Überschriften und Links in ihrer Seitenbeschreibung verwenden. Dies gilt allgemein als sicher.',
    'APP_NAME.title' => 'Anwendungsname',
    'APP_NAME.description' => 'Legt den Titel Ihrer App fest. Durch eine Änderung wird jeder aktive Benutzer abgemeldet.',
    'APP_KEY.title' => 'APP_KEY',
    'APP_KEY.description' => 'APP_KEY',
    'APP_URL.title' => 'APP_URL',
    'APP_URL.description' => 'APP_URL',
    'ENABLE_BUTTON_EDITOR.title' => 'Aktivieren Sie den Button-Editor',
    'ENABLE_BUTTON_EDITOR.description' => 'Legt fest, ob Benutzer ihre eigenen Buttons mit CSS anpassen dürfen.',
    'APP_DEBUG.title' => 'APP_DEBUG',
    'APP_DEBUG.description' => 'APP_DEBUG',
    'APP_ENV.title' => 'APP_ENV',
    'APP_ENV.description' => 'APP_ENV',
    'LOG_CHANNEL.title' => 'LOG_CHANNEL',
    'LOG_CHANNEL.description' => 'LOG_CHANNEL',
    'LOG_LEVEL.title' => 'LOG_LEVEL',
    'LOG_LEVEL.description' => 'LOG_LEVEL',
    'MAINTENANCE_MODE.title' => 'Aktivieren Sie den Wartungsmodus',
    'MAINTENANCE_MODE.description' => 'Zeigt auf allen öffentlichen Seiten eine Wartungsmeldung an. Dadurch werden die Anmeldeseite deaktiviert.',
    'MAIL_MAILER.title' => 'MAIL_MAILER',
    'MAIL_MAILER.description' => 'MAIL_MAILER',
    'MAIL_HOST.title' => 'MAIL_HOST',
    'MAIL_HOST.description' => 'MAIL_HOST',
    'MAIL_PORT.title' => 'MAIL_PORT',
    'MAIL_PORT.description' => 'MAIL_PORT',
    'MAIL_USERNAME.title' => 'MAIL_USERNAME',
    'MAIL_USERNAME.description' => 'MAIL_USERNAME',
    'MAIL_PASSWORD.title' => 'MAIL_PASSWORD',
    'MAIL_PASSWORD.description' => 'MAIL_PASSWORD',
    'MAIL_ENCRYPTION.title' => 'MAIL_ENCRYPTION',
    'MAIL_ENCRYPTION.description' => 'MAIL_ENCRYPTION',
    'MAIL_FROM_ADDRESS.title' => 'MAIL_FROM_ADDRESS',
    'MAIL_FROM_ADDRESS.description' => 'MAIL_FROM_ADDRESS',
    'JOIN_BETA.title' => 'Nehmen Sie am Beta-Programm teil',
    'JOIN_BETA.description' => 'Ermöglicht die Verwendung von Betaversionen beim Update. Lesen Sie mehr darüber <a target=\'_blank\' href=\'https://wayvio.example/b\'>hier</a>.',
    'SKIP_UPDATE_BACKUP.title' => 'Update-Backups überspringen',
    'SKIP_UPDATE_BACKUP.description' => 'Überspringt Backups beim Aktualisieren. Es wird empfohlen, diese Option immer deaktiviert zu lassen. <br>Sie kann jedoch bei einigen Konfigurationen zu Fehlern führen.',
    'CUSTOM_META_TAGS.title' => 'Aktivieren Sie benutzerdefinierte Meta-Tags',
    'CUSTOM_META_TAGS.description' => 'Ermöglicht die Verwendung benutzerdefinierter Meta-Tags im Kopf aller Seiten. Definiert in der erweiterten Konfiguration.',
    'FORCE_HTTPS.title' => 'Erzwinge HTTPS-Links',
    'FORCE_HTTPS.description' => 'Stellt sicher, dass alle Links standardmäßig HTTPS verwenden. Es wird empfohlen, diese Option zu aktivieren, wenn Sie einen Reverse-Proxy verwenden.',
    'ALLOW_CUSTOM_CODE_IN_THEMES.title' => 'Benutzerdefinierten Code in Themes zulassen',
    'ALLOW_CUSTOM_CODE_IN_THEMES.description' => 'Ermöglicht die Verwendung von benutzerdefiniertem Code in Themes. Wenn Sie Themes aus unbekannten Quellen verwenden, kann dies ein Sicherheitsrisiko darstellen.',
    'ENABLE_ADMIN_BAR_USERS.title' => 'Aktivieren Sie die Adminleiste für alle Benutzer',
    'ENABLE_ADMIN_BAR_USERS.description' => 'Wenn aktiviert, wird die Adminleiste für alle authentifizierten Benutzer auf ihrer Linkseite angezeigt.',
    'ENABLE_THEME_UPDATER.title' => 'Aktivieren Sie den Theme-Updater',
    'ENABLE_THEME_UPDATER.description' => 'Legt fest, ob der Theme-Updater aktiv sein soll.',
    'ENABLE_SOCIAL_LOGIN.title' => 'Aktivieren Sie Social Login',
    'ENABLE_SOCIAL_LOGIN.description' => 'Aktiviert Oauth. Diese Option erfordert eine weitere Einrichtung. Lesen Sie mehr darüber <a target=\'_blank\' href=\'https://wayvio.example/social-login\'>hier</a>.',
    'USE_THEME_PREVIEW_IFRAME.title' => 'Verwenden Sie einen iframe als Theme-Vorschau',
    'USE_THEME_PREVIEW_IFRAME.description' => 'Legt fest, ob ein interner Iframe als Vorschau für die Themenseite verwendet werden soll.',
    'FORCE_ROUTE_HTTPS.title' => 'Leiten Sie alle Seiten auf HTTPS um',
    'FORCE_ROUTE_HTTPS.description' => 'Diese Option NICHT mit einem Reverse-Proxy verwenden!',
    'DISPLAY_FOOTER_TERMS.title' => 'Link zur Fußzeile der Nutzungsbedingungen',
    'DISPLAY_FOOTER_TERMS.description' => 'Fußzeilenlink „Bedingungen“ aktivieren.',
    'DISPLAY_FOOTER_PRIVACY.title' => 'Link zur Datenschutz-Fußzeile',
    'DISPLAY_FOOTER_PRIVACY.description' => 'Aktivieren Sie den Link „Datenschutz“.',
    'DISPLAY_FOOTER_CONTACT.title' => 'Link zur Kontaktfußzeile',
    'DISPLAY_FOOTER_CONTACT.description' => 'Kontaktlink aktivieren.',
    'TITLE_FOOTER_HOME.title' => '<div style="margin-top:-40px"></div>',
    'TITLE_FOOTER_HOME.description' => 'Titel des Home-Fußzeilen-Links.',
    'TITLE_FOOTER_TERMS.title' => '<div style="margin-top:-40px"></div>',
    'TITLE_FOOTER_TERMS.description' => 'Titel des Bedingungslinks.',
    'TITLE_FOOTER_PRIVACY.title' => '<div style="margin-top:-40px"></div>',
    'TITLE_FOOTER_PRIVACY.description' => 'Titel des Datenschutzlinks.',
    'TITLE_FOOTER_CONTACT.title' => '<div style="margin-top:-40px"></div>',
    'TITLE_FOOTER_CONTACT.description' => 'Titel des Kontaktlinks.',
    'HOME_FOOTER_LINK.title' => '<div style="margin-top:-40px">URL des Home-Fußzeilen-Links</div>',
    'HOME_FOOTER_LINK.description' => 'Geben Sie eine beliebige URL ein, um Ihre Home-Link-URL umzuleiten.<br>Leer lassen, um den Standardlink zu verwenden.',
    'ALLOW_CUSTOM_BACKGROUNDS.title' => 'Benutzerdefinierte Hintergründe zulassen',
    'ALLOW_CUSTOM_BACKGROUNDS.description' => 'Ermöglichen Sie Benutzern das Hochladen benutzerdefinierter Hintergrundbilder für Nutzerseiten.',
    'ALLOW_USER_IMPORT.title' => 'Erlauben Sie Benutzern, Profile aus anderen Instanzen zu importieren',
    'ALLOW_USER_IMPORT.description' => 'Ermöglicht Benutzern das Importieren ihres Profils und ihrer Links aus einer externen Datei.',
    'ALLOW_USER_EXPORT.title' => 'Erlauben Sie Benutzern, ihr Profil zu exportieren',
    'ALLOW_USER_EXPORT.description' => 'Ermöglicht Benutzern den Export ihrer eigenen Links und ihres Profils.',
    'MANUAL_USER_VERIFICATION.title' => 'Manuelle Nutzerüberprüfung',
    'MANUAL_USER_VERIFICATION.description' => 'Legt fest, ob Administratoren neu registrierte Benutzer manuell überprüfen müssen.',
    'ADMIN_EMAIL.title' => 'Admin-E-Mail',
    'ADMIN_EMAIL.description' => 'Wird zum Versenden von Benachrichtigungs-E-Mails verwendet.',
    'HIDE_VERIFICATION_CHECKMARK.title' => 'Verifizierungshäkchen ausblenden',
    'HIDE_VERIFICATION_CHECKMARK.description' => 'Versteckt das Verifizierungsabzeichen, das auf Admin- und VIP-Seiten angezeigt wird.',
    'ENABLE_REPORT_ICON.title' => 'Report-Icon aktivieren',
    'ENABLE_REPORT_ICON.description' => 'Zeigt ein Symbol auf Benutzerseiten an, das es Benutzern ermöglicht, Seiten zu melden.',
    'LOCALE.title' => 'App Lokalisierung',
    'LOCALE.description' => 'Ändern Sie die Sprache Ihrer Anwendung.',


    /*
    |--------------------------------------------------------------------------
    | Installer
    |--------------------------------------------------------------------------
    |
    | resources/views/installer/installer.blade.php
    |
    */

    # Title Tag
    'Wayvio setup' => 'Wayvio-Setup',

    'Setup Wayvio' => 'Richten Sie Wayvio ein',
    'Welcome to the setup for Wayvio!' => 'Willkommen beim Setup für Wayvio!',
    'This setup will:' => 'Dieses Setup wird:',
    'Check the server dependencies' => '1. Überprüfen Sie die Serverabhängigkeiten',
    'Setup the database' => '2. Richten Sie die Datenbank ein',
    'Create the admin user' => '3. Erstellen Sie den Admin-Benutzer',
    'Configure the app' => '4. Konfigurieren Sie die App',
    'Choose a language' => 'Wählen Sie eine Sprache',
    'setup.disclaimer' => 'Es gelten unsere',
    'Terms and Conditions' => 'Allgemeinen Geschäftsbedingungen',

    'Next' => 'Weiter',
    'Yes' => 'Ja',
    'No' => 'NEIN',
    'Finish setup' => 'Beenden Sie die Einrichtung',

    'Setup failed' => 'Die Einrichtung ist fehlgeschlagen',
    'An error has occured. Please try again' => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.',
    'Depending on your database type:' => 'Abhängig von Ihrem Datenbanktyp:',
    'Try again' => 'Versuchen Sie es erneut',

    'Dependency check' => 'Abhängigkeitsprüfung',
    'Required PHP modules:' => 'Erforderliche PHP-Module:',

    'Select a database type' => 'Wählen Sie einen Datenbanktyp aus',
    'Under most circumstances, we recommend using SQLite' => 'In den meisten Fällen empfehlen wir die Verwendung von SQLite.',
    'MySQL requires a separate, empty MySQL database' => 'MySQL erfordert eine separate, leere MySQL-Datenbank.',

    'Database type:' => 'Datenbanktyp:',
    'Database host:' => 'Datenbankhost:',
    'Database port:' => 'Datenbankport:',
    'Database name:' => 'Name der Datenbank:',
    'Database username:' => 'Datenbankbenutzername:',
    'Database password:' => 'Datenbankpasswort:',

    'Create an admin account' => 'Erstellen Sie ein Administratorkonto.',
    'Admin email:' => 'Admin-E-Mail:',
    'Admin password:' => 'Administrator-Passwort:',
    'Handle:' => 'Handle:',
    'Name:' => 'Name:',

    'Configure your page' => 'Konfigurieren Sie Ihre Seite',
    'Enable registration:' => 'Registrierung aktivieren:',
    'Enable email verification:' => 'E-Mail-Bestätigung aktivieren:',
    'Set your page as Home Page' => 'Legen Sie Ihre Seite als Startseite fest',
    'This will move the Home Page to /home' => 'Dadurch wird die Startseite nach /home verschoben',
    'App Name:' => 'App Name:',


    /*
    |--------------------------------------------------------------------------
    | Updater/Update-Backup
    |--------------------------------------------------------------------------
    |
    | resources/views/update.blade.php
    |
    */

    # Title Tag
    'Update Wayvio' => 'Wayvio aktualisieren',

    'Latest beta version' => 'Neueste Beta-Version',
    'Installed beta version' => 'Installierte Beta-Version',
    'none' => 'keiner',
    'You need to update to the latest mainline release' => 'Sie müssen auf die neueste Hauptversion aktualisieren',
    'You’re running the latest mainline release' => 'Sie verwenden die neueste Hauptversion',

    'update.manually' => 'Sie können Ihre Installation automatisch aktualisieren oder das Update herunterladen und manuell installieren:',
    'update.windows' => 'Windows-Benutzer können den alternativen Updater verwenden. Dieser Updater erstellt kein Backup. Verwendung nach eigenem Ermessen.',
    'Update automatically' => 'Automatisch aktualisieren',

    'Updating' => 'Aktualisierung',
    'Creating backup' => 'Backup erstellen',
    'Preparing update' => 'Update wird vorbereitet',
    'No new version' => 'Keine neue Version',
    'There is no new version available' => 'Es ist keine neue Version verfügbar',
    'Admin Panel' => 'Administrationsmenü',
    'Finishing up' => 'Beenden',
    'Success!' => 'Erfolg!',
    'The update was successful' => 'Das Update war erfolgreich, Sie können nun zum Admin-Panel zurückkehren.',
    'View the release notes' => 'Sehen Sie sich die Versionshinweise an',
    'Run again' => 'Erneut updaten',
    'Error' => 'Error',
    'Something went wrong with the update' => 'Beim Update ist ein Fehler aufgetreten',

    
    /*
    |--------------------------------------------------------------------------
    | Backup
    |--------------------------------------------------------------------------
    |
    | resources/views/backup.blade.php
    |
    */

    # Title Tag
    'Backup.title' => 'Sicherung',

    'Backup' => 'Backup',

    'The backup system won’t save more than two backups at a time' => 'Das Sicherungssystem speichert nicht mehr als zwei Backups.',
    'Backup Instance' => 'Instanz sichern',
    'Creating backup' => 'Backup wird erstellt',
    'The backup was successful' => 'Die Backup wurde erfolgreich erstellt. Sie können nun zum Administrationsbereich zurückkehren oder alle Ihre Backups anzeigen.',    


    /*
    |--------------------------------------------------------------------------
    | Page Blocks
    |--------------------------------------------------------------------------
    |
    | Parts are stored in the database.
    | resources/views/studio/edit-link.blade.php
    |
    */

    # predefined
    'block.title.predefined' => 'Vordefinierte Website',
    'block.description.predefined' => 'Liste vordefinierter Websites mit automatisch passendem Branding.',

    # link
    'block.title.link' => 'Benutzerdefinierten Link',
    'block.description.link' => 'Erstellen Sie einen benutzerdefinierten Link, der zu einer beliebigen Website führt. Passen Sie den Stil und das Symbol der Schaltfläche an oder verwenden Sie das Favicon von der Website als Schaltflächensymbol.',

    # vcard
    'block.title.vcard' => 'Vcard',
    'block.description.vcard' => 'Erstellen Sie eine elektronische Visitenkarte oder laden Sie sie hoch.',

    # email
    'block.title.email' => 'E-Mail-Addresse',
    'block.description.email' => 'Fügen Sie eine E-Mail hinzu, die einen Systemdialog zum Verfassen einer neuen E-Mail öffnet.',

    # telephone
    'block.title.telephone' => 'Telefonnummer',
    'block.description.telephone' => 'Fügen Sie eine Telefonnummer hinzu, die einen Systemdialog zum Einleiten eines Telefonanrufs öffnet.',

    # heading
    'block.title.heading' => 'Überschrift',
    'block.description.heading' => 'Verwenden Sie Überschriften, um Ihre Links zu organisieren und sie in Gruppen zu unterteilen.',

    # spacer
    'block.title.spacer' => 'Abstandshalter',
    'block.description.spacer' => 'Fügen Sie Ihrer Linkliste Leerzeichen hinzu. Sie können wählen, wie hoch.',

    # text
    'block.title.text' => 'Text',
    'block.description.text' => 'Fügen Sie Ihrer Seite statischen Text hinzu, der nicht anklickbar ist.',

    # opening_hours
    'block.title.opening_hours' => 'Öffnungszeiten',
    'block.description.opening_hours' => 'Deine Öffnungszeiten, übersichtlich gruppiert.',

    'opening_hours.aria_label' => 'Öffnungszeiten',
    'opening_hours.closed' => 'Geschlossen',

    'opening_hours.day.monday' => 'Montag',
    'opening_hours.day.tuesday' => 'Dienstag',
    'opening_hours.day.wednesday' => 'Mittwoch',
    'opening_hours.day.thursday' => 'Donnerstag',
    'opening_hours.day.friday' => 'Freitag',
    'opening_hours.day.saturday' => 'Samstag',
    'opening_hours.day.sunday' => 'Sonntag',

    'opening_hours.day_short.monday' => 'Mo',
    'opening_hours.day_short.tuesday' => 'Di',
    'opening_hours.day_short.wednesday' => 'Mi',
    'opening_hours.day_short.thursday' => 'Do',
    'opening_hours.day_short.friday' => 'Fr',
    'opening_hours.day_short.saturday' => 'Sa',
    'opening_hours.day_short.sunday' => 'So',

    'opening_hours.editor.internal_title' => 'Interner Name (nur für die Übersicht)',
    'opening_hours.editor.internal_title_placeholder' => 'z. B. Öffnungszeiten',
    'opening_hours.editor.internal_title_hint' => 'Wird nur für die Studio-Übersicht verwendet.',
    'opening_hours.editor.group_days' => 'Gleiche Tage zusammenfassen',
    'opening_hours.editor.visual_style' => 'Design Style',
    'opening_hours.editor.visual_style_hint' => 'Unified block presets for consistent styling across all custom content blocks.',
    'opening_hours.editor.days_heading' => 'Wochentage',
    'opening_hours.editor.open_toggle' => 'Geöffnet / Geschlossen',
    'opening_hours.editor.open' => 'Geöffnet',
    'opening_hours.editor.closed' => 'Geschlossen',
    'opening_hours.editor.from' => 'Von',
    'opening_hours.editor.to' => 'Bis',
    'opening_hours.editor.add_break' => 'Pause hinzufügen',
    'opening_hours.editor.remove_break' => 'Pause entfernen',
    'opening_hours.editor.time_validation' => 'Zeit fehlt oder ungültig.',
    'opening_hours.editor.range_validation' => 'Die Schließzeit muss nach der Öffnungszeit liegen.',
    'opening_hours.editor.open_day_requires_slot' => 'Für geöffnete Tage ist mindestens ein gültiger Zeitslot erforderlich.',

    # hub_contact_form
    'hub_contact_form.editor.internal_title' => 'Interner Name (nur für die Übersicht)',
    'hub_contact_form.editor.internal_title_placeholder' => 'z. B. Kontaktformular Startseite',
    'hub_contact_form.editor.internal_title_hint' => 'Nur für die Übersicht.',
    'hub_contact_form.editor.public_title' => 'Titel im Formular',
    'hub_contact_form.editor.public_title_hint' => 'Wird im Formular angezeigt.',
    'hub_contact_form.editor.description_hint' => 'Kurze Beschreibung.',
    'hub_contact_form.editor.visual_style' => 'Design Style',
    'hub_contact_form.editor.visual_style_hint' => 'Clean, Glass, Bold.',

    # custom blocks
    'block.title.gastro_service' => 'Leistungen',
    'block.description.gastro_service' => 'Mobile-first Liste für Menüs, Produkte oder Services mit Preisen.',
    'block.title.hub_contact_form' => 'Kontaktformular',
    'block.description.hub_contact_form' => 'Natives Kontaktformular. Sicher, verschlüsselt und DSGVO-ready.',
    'block.title.separator' => 'Separator',
    'block.description.separator' => 'Trennlinie für mehr Struktur.',
    'block.title.smart_embed' => 'Smart Embed',
    'block.description.smart_embed' => '2-Click-Embed für YouTube, Spotify, Maps & Co. mit Consent-Layer.',
    'block.title.social_proof' => 'Social Proof',
    'block.description.social_proof' => 'Testimonials ohne externe Skripte - schafft 100% Vertrauen.',
    'block.title.usp_cards' => 'USP Cards',
    'block.description.usp_cards' => 'Deine Benefits auf einen Blick in schicken Cards.',

    'common.internal_title' => 'Interner Name (nur für die Übersicht)',
    'common.internal_title_hint' => 'Nur für die Übersicht.',
    'common.visual_style' => 'Design Style',
    'common.visual_style_hint' => 'Clean, Glass, Bold.',
    'common.remove' => 'Entfernen',
    'common.title' => 'Titel',
    'common.name' => 'Name',
    'common.description_optional' => 'Beschreibung (optional)',
    'common.icon' => 'Icon',
    'common.example' => 'Beispiel',
    'common.example_cappuccino' => 'z. B. Cappuccino',
    'common.example_why_choose_us' => 'z. B. Warum wir?',
    'common.example_fast_launch' => 'z. B. Schneller Start',

    'gastro_service.editor.internal_title_placeholder' => 'z. B. Speisekarte',
    'gastro_service.editor.items' => 'Einträge',
    'gastro_service.editor.item' => 'Eintrag',
    'gastro_service.editor.add_item' => 'Eintrag hinzufügen',
    'gastro_service.editor.description_placeholder' => 'z. B. mit Hafermilch erhältlich',
    'gastro_service.editor.price_optional' => 'Preis (optional)',
    'gastro_service.editor.price_hint' => 'Erlaubt: 4,90, 12.50 oder "ab 9 €"',
    'gastro_service.editor.price_placeholder' => 'z. B. 4,90 oder ab 9 €',
    'gastro_service.editor.badges' => 'Badges',
    'gastro_service.badge.vegan' => 'Vegan',
    'gastro_service.badge.organic' => 'Bio',
    'gastro_service.badge.spicy' => 'Scharf',
    'gastro_service.badge.new' => 'Neu',

    'social_proof.editor.notice' => 'Achte darauf, dass du die Einwilligung zur Namensnennung hast. Im Zweifel nur Initialen nutzen.',
    'social_proof.editor.internal_title_placeholder' => 'z. B. Kundenstimmen',
    'social_proof.editor.testimonials' => 'Testimonials',
    'social_proof.editor.testimonial' => 'Testimonial',
    'social_proof.editor.add_testimonial' => 'Testimonial hinzufügen',
    'social_proof.editor.name_placeholder' => 'z. B. M. K.',
    'social_proof.editor.rating' => 'Bewertung (1-5 Sterne)',
    'social_proof.editor.review_text' => 'Bewertungstext',
    'social_proof.editor.review_placeholder' => 'z. B. Sehr professioneller Service und schnelle Kommunikation.',
    'social_proof.editor.avatar_optional' => 'Profilbild URL (optional)',
    'social_proof.editor.avatar_placeholder' => 'https://example.com/avatar.jpg oder /assets/img/avatar.jpg',

    'usp_cards.editor.cards_max_3' => 'USP Cards (max 3)',
    'usp_cards.editor.add_card' => 'Card hinzufügen',
    'usp_cards.editor.card' => 'Card',
    'usp_cards.editor.benefit_hint' => 'Kurzer Satz zum Vorteil',

    'hub_contact_form.editor.default_title' => 'Kontakt',
    'hub_contact_form.editor.description' => 'Kurze Beschreibung',

    'smart_embed.editor.external_url' => 'Externe URL',
    'smart_embed.editor.service' => 'Service',
    'smart_embed.editor.internal_title_placeholder' => 'z. B. Produktvideo',
    'smart_embed.service.youtube' => 'YouTube',
    'smart_embed.service.instagram' => 'Instagram',
    'smart_embed.service.google_maps' => 'Google Maps',
    'smart_embed.service.spotify' => 'Spotify',
    'smart_embed.service.calendly' => 'Calendly',
    'smart_embed.service.tally' => 'Tally',
    'smart_embed.service.gumroad' => 'Gumroad',
    'smart_embed.service.kit' => 'Kit',
    'smart_embed.service.resmio_booking' => 'Resmio - Buchung',
    'smart_embed.service.resmio_menu' => 'Resmio - Speisekarte',

    'link.editor.use_custom_icon' => 'Eigenes Icon wählen',
    'link.editor.mark_18' => 'Als 18+ markieren',
    'link.editor.choose_icon' => 'Icon wählen',
    'link.icon.auto' => 'Auto (Standard/Favicon)',
    'link.icon.external_link' => 'Externer Link',
    'link.icon.link' => 'Link',
    'link.icon.website' => 'Website',
    'link.icon.profile' => 'Profil',
    'link.icon.email' => 'E-Mail',
    'link.icon.phone' => 'Telefon',
    'link.icon.chat' => 'Chat',
    'link.icon.play' => 'Play',
    'link.icon.music' => 'Music',
    'link.icon.photo' => 'Foto',
    'link.icon.video' => 'Video',
    'link.icon.shop' => 'Shop',
    'link.icon.payment' => 'Zahlung',
    'link.icon.support' => 'Support',
    'link.icon.featured' => 'Featured',
    'link.icon.book' => 'Book',
    'link.icon.news' => 'News',
    'link.icon.event' => 'Event',
    'link.icon.location' => 'Ort',
    'link.icon.download' => 'Download',
    'link.icon.document' => 'Dokument',
    'link.icon.launch' => 'Launch',
    'link.icon.ideas' => 'Ideen',
    'link.icon.business' => 'Business',
    'link.icon.education' => 'Bildung',
    'link.icon.gaming' => 'Gaming',
    'link.icon.audio' => 'Audio',
    'link.icon.code' => 'Code',
    'link.icon.share' => 'Teilen',
    'link.icon.info' => 'Info',
    'link.icon.help' => 'Hilfe',
    'link.icon.secure' => 'Sicher',



    /*
    |--------------------------------------------------------------------------
    | Page Items
    |--------------------------------------------------------------------------
    |
    | resources/views/components/pageitems/
    |
    */

    'Default Email' => 'Standard-E-Mail',
    'Custom Title' => 'Benutzerdefinierter Titel',
    'Leave blank for default title' => 'Für den Standardtitel leer lassen',
    'E-Mail address' => 'E-Mail-Addresse',
    'Enter your E-Mail' => 'Geben sie ihre E-Mail Adresse ein',

    'Heading Text:' => 'Überschriftentext:',

    'URL' => 'URL',
    'Show website icon on button' => 'Website-Symbol anzeigen',

    'Select a predefined site' => 'Wählen Sie eine vordefinierte Site aus',
    'Enter the link URL' => 'Geben Sie die Link-URL ein',

    'Spacing height' => 'Abstandshöhe',

    'Phone' => 'Telefon',
    'Telephone number' => 'Telefonnummer',
    'Enter your telephone number' => 'Geben Sie Ihre Telefonnummer ein',

    'Text to display' => 'Text, der angezeigt werden soll',

    'Vcard' => 'Vcard',
    'First Name' => 'Vorname',
    'Middle Name' => 'Zweiter Vorname',
    'Last Name' => 'Familienname, Nachname',
    'Suffix' => 'Suffix',
    'Work' => 'Arbeiten',
    'Organization' => 'Organisation',
    'Work URL' => 'Arbeits-URL',
    'Emails' => 'E-Mails',
    'Enter your personal email' => 'Geben Sie Ihre persönliche E-Mail-Adresse ein',
    'Work Email' => 'Arbeits Email',
    'Enter your work email' => 'Geben Sie Ihre geschäftliche E-Mail-Adresse ein',
    'Phones' => 'Telefone',
    'Home Phone' => 'Festnetztelefon',
    'Work Phone' => 'Arbeitshandy',
    'Cell Phone' => 'Handy',
    'Home Address' => 'Heimatadresse',
    'Label' => 'Etikett',
    'Street' => 'Straße',
    'City' => 'Stadt',
    'State/Province' => 'Staat/Provinz',
    'Zip/Postal Code' => 'Postleitzahl',
    'Country' => 'Land',
    'Work Address' => 'Arbeitsadresse',

    'URL to the video' => 'URL zum Video',


    /*
    |--------------------------------------------------------------------------
    | Maintenance Page
    |--------------------------------------------------------------------------
    |
    | resources/views/mainenance.blade.php
    |
    */

    'Maintenance Mode' => 'Wartungsmodus',
    'We are performing scheduled site maintenance at this time' => 'Wir führen derzeit planmäßige Wartungsarbeiten an der Website durch.',
    'Please check back with us later' => 'Bitte schauen Sie später noch einmal bei uns vorbei.',
    'Admin options:' => 'Admin-Optionen:',
    'Turn off' => 'Abschalten',
    'Warn.Disable.Maintenance' => 'Sie sind dabei, den Wartungsmodus zu deaktivieren. Bist du dir sicher?',


    /*
    |--------------------------------------------------------------------------
    | Wayvio (Links) Page
    |--------------------------------------------------------------------------
    |
    | resources/views/littlelink.blade.php
    |
    */

    'Share this page' => 'Teile diese Seite',
    'Share' => 'Teilen',
    'Copy URL to clipboard' => 'URL in die Zwischenablage kopieren',
    'URL has been copied to your clipboard!' => 'Die URL wurde in Ihre Zwischenablage kopiert!',

    'Delete User' => 'Benutzer löschen',
    'Block User' => 'Benutzer blockieren',
    'Users Theme' => 'Theme',
    'Search User' => 'Nach Benutzer Suchen',
    
    'Edit my profile' => 'Profil editieren',

    /*
    |--------------------------------------------------------------------------
    | Footer
    |--------------------------------------------------------------------------
    |
    | Added to the bottom of certain pages.
    | resources/views/layouts/footer.blade.php
    |
    */

    'Learn more about Wayvio' => 'Erfahren Sie mehr über Wayvio',
    'Learn more' => 'Erfahren Sie mehr',

    /*
    |--------------------------------------------------------------------------
    | Notification messages
    |--------------------------------------------------------------------------
    |
    | All internal notifications.
    | resources/views/layouts/notifications.blade.php
    |
    */

    'No notifications' => 'Keine Benachrichtigungen',

    # Security Risk Notification
    'Your security is at risk!' => 'Ihre Sicherheit ist gefährdet!',
    'Immediate action is required!' => 'Sofortiges Handeln ist erforderlich!',
    'security.msg1' => 'Ihre Sicherheit ist gefährdet.',
    'security.msg2' => 'Einige Dateien können von jedem eingesehen werden. Sofortiges Handeln ist erforderlich!',
    'security.msg3' => 'Einige wichtige Dateien sind öffentlich zugänglich und gefährden Ihre Sicherheit. Bitte ergreifen Sie sofort Maßnahmen, um den öffentlichen Zugriff auf diese Dateien zu widerrufen und unbefugten Zugriff auf Ihre sensiblen Informationen zu verhindern.',
    'security.msg4' => 'Erfahren Sie mehr',    

    # Help Us Out Notification
    'Hide this notification' => 'Diese Benachrichtigung ausblenden',
    'Help Us Out' => 'Helfen Sie uns',
    'Enjoying Wayvio?' => 'Gefällt Ihnen Wayvio?',
    'Support Wayvio' => 'Unterstützen Sie Wayvio',
    'support.msg1' => 'Wenn Ihnen die Verwendung von Wayvio gefällt, würden wir es sehr schätzen, wenn Sie sich einen Moment Zeit nehmen könnten, um',
    'support.msg2' => 'unserem Projekt auf GitHub einen Stern zu geben',
    'support.msg3' => 'Ihre Unterstützung wird uns helfen, ein größeres Publikum zu erreichen und die Qualität unseres Projekts zu verbessern.',
    'support.msg4' => 'Wenn Sie in der Lage sind,',
    'support.msg5' => 'einen finanziellen Beitrag zu leisten</a>, würde uns selbst ein kleiner Betrag helfen, die Kosten für die Aufrechterhaltung und Verbesserung von Wayvio zu decken.',
    'support.msg6' => 'Vielen Dank für Ihre Unterstützung und dafür, Teil der Wayvio-Community zu sein!',
    

    /*
    |--------------------------------------------------------------------------
    | Footer Links
    |--------------------------------------------------------------------------
    |
    */

    'footer.Home' => 'Startseite',
    'footer.Terms' => 'AGB',
    'footer.Privacy' => 'Datenschutz',
    'footer.Contact' => 'Kontakt',


    /*
    |--------------------------------------------------------------------------
    | Report Page
    |--------------------------------------------------------------------------
    |
    */

    'report_violation' => 'Verstoß melden',
    'report_short' => 'Melden',
    'url_label' => 'URL der gemeldeten Website',
    'report_type_label' => 'Art des Reports',
    'hate_speech' => 'Hassrede oder Belästigung',
    'violence_threats' => 'Gewalt oder Drohungen',
    'illegal_activities' => 'Illegale Aktivitäten',
    'copyright_infringement' => 'Verletzung von Urheberrechten',
    'misinformation_fake_news' => 'Fehlinformationen oder Fake News',
    'identity_theft' => 'Identitätsdiebstahl',
    'drug_related_content' => 'Inhalte im Zusammenhang mit Drogen',
    'weapons_harmful_objects' => 'Waffen oder schädliche Objekte',
    'child_exploitation' => 'Kindesausbeutung',
    'fraud_scams' => 'Betrug oder Betrugsversuche',
    'privacy_violation' => 'Verletzung der Privatsphäre',
    'impersonation' => 'Identitätswechsel',
    'other_specify' => 'Sonstiges (bitte angeben)',
    'additional_comments_label' => 'Zusätzliche Kommentare',
    'submit_button' => 'Absenden',
    
    'report_mail_admin_subject' => 'Profilmeldung',
    'report_mail_admin_report' => 'Ein Profil wurde gemeldet',
    
    'report_mail_reported_profile' => 'Gemeldetes Profil',
    'report_mail_reported_url' => 'Gemeldete URL',
    'report_mail_type' => 'Art',
    'report_mail_message' => 'Nachricht',
    
    'report_mail_report_submitted_by' => 'Report eingereicht von',
    'report_mail_reported_by' => 'Gemeldet von',
    'report_mail_profile' => 'Profil',
    
    'report_mail_button_profile' => 'Auf Benutzerseite anzeigen',
    'report_mail_button_delete' => 'Gemeldeten Benutzer löschen',
    
    'report_error' => 'Profil konnte nicht gemeldet werden',
    'report_self_error' => 'Du kannst dein eigenes Profil nicht melden',
    'report_rate_limit_error' => 'Dieses Profil wurde von deiner Verbindung zu oft gemeldet. Bitte versuche es später erneut',
    'report_success' => 'Profil wurde erfolgreich gemeldet',


    #=============================================================================#
    # Laravel internal translations                                               #
    #=============================================================================#


    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Diese Anmeldeinformationen stimmen nicht mit unseren Unterlagen überein.',
    'password' => 'Das angegebene Passwort ist falsch.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :Sekunden Sekunden erneut.',


    /*
    |--------------------------------------------------------------------------
    | Pagination Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used by the paginator library to build
    | the simple pagination links. You are free to change them to anything
    | you want to customize your views to better match your application.
    |
    */

    'previous' => '&laquo; Vorherige',
    'next' => 'Nächste &raquo;',


    /*
    |--------------------------------------------------------------------------
    | Subscription / Billing
    |--------------------------------------------------------------------------
    */

    'Cancellation is scheduled for :date.' => 'Die Kündigung ist für den :date geplant.',
    'Current: :current. Scheduled: :scheduled on :date.' => 'Aktuell: :current. Geplant: :scheduled ab :date.',
    'Next monthly total: :amount.' => 'Neuer monatlicher Gesamtbetrag: :amount.',
    'Cancel subscription' => 'Abo kündigen',
    'Cancellation takes effect at the end of the current billing period. Your access remains active until then.' => 'Die Kündigung wird zum Ende des aktuellen Abrechnungszeitraums wirksam. Dein Zugang bleibt bis dahin aktiv.',
    'Cancel now' => 'Jetzt kündigen',


];
