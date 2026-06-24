
# My Cryndel - Adversarial Test Plan
 
## Environment
- Local PHP dev server: http://localhost:8080 (with router.php)
- Test user: testuser1 / test123456
- PHP 8.1.2, data stored in VP/ JSON files
 
## Test 1: Branding & Title Rename
**Navigate**: http://localhost:8080/
**Steps**: Load homepage, inspect page title and header logo text
**Pass criteria**:
- Page title contains "My Cryndel" (not "HI Cryndel")
- Header logo text reads "My Cryndel"
- No visible occurrences of "HI Cryndel" anywhere on page
**Fail if**: Title or logo still says "HI Cryndel"
 
## Test 2: Navigation Links - New Sections Present
**Navigate**: http://localhost:8080/ (logged in as testuser1)
**Steps**: Inspect the header nav bar for new section links
**Pass criteria** (exact link text and icons):
- "Лента" link with fa-home icon, href="/"
- "Музыка" link with fa-music icon, href="/?action=music"
- "Стикеры" link with fa-sticky-note icon, href="/?action=stickers"
- "Кодинг" link with fa-code icon, href="/?action=coding"
- "Оформление" link with fa-palette icon, href="/?action=themes"
- "Профиль" link with user avatar, href="/testuser1"
- NO "Создать пост" or "Мои посты" links in header nav
**Fail if**: Any of the 5 new section links missing, or old "Создать"/"Мои посты" links still present
 
## Test 3: FAB Button (Floating Action Button)
**Navigate**: http://localhost:8080/ (logged in)
**Steps**: Scroll the feed page, look for floating + button in bottom-right corner
**Pass criteria**:
- A circular button with "+" icon visible at bottom-right of viewport
- Button has class "fab-button"
- Button links to /?action=create_post
- Button has green background (#10b981 or var(--primary))
**Fail if**: No floating button visible, or button links somewhere else, or button is in header instead of floating
 
## Test 4: Music Section Page
**Navigate**: http://localhost:8080/?action=music (logged in)
**Steps**: Verify page heading, upload button, search bar, and empty state
**Pass criteria**:
- Page title in browser tab: "Музыка | My Cryndel"
- h1 heading contains "Музыка" with fa-music icon
- "Загрузить" button visible (because user is logged in)
- Search input with placeholder "Поиск музыки..."
- Empty state shows "Пока нет музыки" (or similar)
**Fail if**: Missing heading, no upload button for logged-in user, or page shows error
 
## Test 5: Music Upload Form
**Navigate**: http://localhost:8080/?action=music&upload=1 (logged in)
**Steps**: Verify upload form fields and checkboxes render correctly
**Pass criteria**:
- Form is visible with title "Загрузить музыку"
- Fields present: Название (required), Юз (required), Иконка file+url, Музыка file+url, Описание, Теги
- Checkboxes present: "Я являюсь автором", "Создано с использованием ИИ", "18+", "Я знаком с правилами" (required)
- Submit button "Загрузить" present
- Form action points to /api.php
**Fail if**: Form missing, any required field missing, checkboxes not rendered
 
## Test 6: Stickers Section Page
**Navigate**: http://localhost:8080/?action=stickers (logged in)
**Steps**: Verify page heading, upload button, empty state
**Pass criteria**:
- Page title: "Стикеры | My Cryndel"
- h1 heading contains "Стикеры" with fa-sticky-note icon
- "Загрузить" button visible
- Search input with placeholder "Поиск стикеров..."
- Empty state: "Пока нет стикеров"
**Fail if**: Missing heading, no upload button, or page error
 
## Test 7: Coding Section Page
**Navigate**: http://localhost:8080/?action=coding (logged in)
**Steps**: Verify page heading, upload button, empty state
**Pass criteria**:
- Page title: "Кодинг | My Cryndel"
- h1 heading contains "Кодинг" with fa-code icon
- "Загрузить" button visible
- Search input with placeholder "Поиск кода, сборок..."
- Empty state: "Пока нет загрузок"
**Fail if**: Missing heading, no upload button, or page error
 
## Test 8: Themes/Оформление Section Page
**Navigate**: http://localhost:8080/?action=themes (logged in)
**Steps**: Verify page heading, create button, empty state
**Pass criteria**:
- Page title: "Оформление | My Cryndel"
- h1 heading contains "Оформление" with fa-palette icon
- "Создать" button visible
- Search input with placeholder "Поиск оформлений..."
- Empty state: "Пока нет оформлений"
**Fail if**: Missing heading, no create button, or page error
 
## Test 9: Registration Flow (New User)
**Navigate**: http://localhost:8080/?action=register
**Steps**: Fill and submit registration form with unique credentials
**Pass criteria**:
- Form title: "Регистрация"
- Fields present: username, email, password, confirm_password
- After submit with valid data: success message "Регистрация успешна!"
- User is auto-logged in (nav shows profile link)
**Fail if**: Form doesn't render, submission fails, or user not logged in after registration
 
## Test 10: Profile Page - Cover, Status, Stats
**Navigate**: http://localhost:8080/testuser1 (logged in as testuser1)
**Steps**: Verify profile page elements render
**Pass criteria**:
- Profile cover div with class "profile-cover" is present
- Username "@testuser1" displayed in profile-name
- Bio text visible: "Ой наш любимый игрок, нашего сервера Cryndel SMP"
- Stats section shows: Постов (0), Просмотров (0), Лайков (0), На сервере (date)
- Settings gear button visible (because viewing own profile)
- "Редактировать профиль" button visible
**Fail if**: Missing cover, stats, or settings button
 
## Test 11: Profile Settings - Status & Cover CSS
**Navigate**: http://localhost:8080/testuser1 (logged in, click settings gear)
**Steps**: Open settings tab, fill in status and cover CSS, save, verify they appear
**Pass criteria**:
- Settings form visible with "Статус (как в Discord)" input
- Cover CSS input with gradient presets (5 clickable gradient swatches)
- Widget checkboxes: "Виджет музыки", "Виджет стикеров", "Виджет сборки"
- After saving status "Играю в Minecraft": profile shows status dot + text "Играю в Minecraft"
- After saving cover CSS gradient: profile-cover div has inline style with the gradient
**Fail if**: Settings form missing, gradient presets not clickable, saved status not displayed on profile
 
## Test 12: Notifications Page
**Navigate**: http://localhost:8080/?action=notifications (logged in)
**Steps**: Verify notifications page renders
**Pass criteria**:
- Page title: "Уведомления | My Cryndel"
- h1 heading: "Уведомления" with fa-bell icon
- Empty state shows: "Нет уведомлений" with fa-bell-slash icon
**Fail if**: Page redirects to login (when logged in), or heading missing, or error
 
## Test 13: Notification Bell in Header
**Navigate**: http://localhost:8080/ (logged in)
**Steps**: Look for notification bell icon in header right section
**Pass criteria**:
- Bell icon (fa-bell) visible in header nav-right area
- Notification badge element present (may show 0 or be hidden)
- Clicking bell shows dropdown with "Уведомления" header and "Прочитать все" button
**Fail if**: No bell icon visible for logged-in user
 
## Test 14: Mobile Navigation Menu
**Navigate**: http://localhost:8080/ (logged in, resize to mobile width ~375px)
**Steps**: Click hamburger menu button, verify mobile nav links
**Pass criteria**:
- Hamburger button (fa-bars) visible in header at mobile width
- Clicking it opens mobile menu overlay
- Mobile menu contains links: Лента, Музыка, Стикеры, Кодинг, Оформление, Уведомления, Профиль
- Close button (fa-times) visible in mobile menu header
**Fail if**: Hamburger button missing, mobile menu doesn't open, or section links missing
 
## Test 15: Special Commands JS - Textarea Detection
**Navigate**: http://localhost:8080/?action=create_post (logged in)
**Steps**: Type trigger characters in the post content textarea
**Pass criteria**:
- Typing "§" in textarea triggers music command menu popup
- Typing ";" triggers sticker command menu popup  
- Typing "~" triggers themes/оформление command menu popup
- Typing "&" triggers coding command menu popup
- Each popup shows relevant content type label
**Fail if**: No popup appears on trigger character, or wrong popup type shown
 
## Test 16: Upload Music via API
**Navigate**: http://localhost:8080/?action=music&upload=1 (logged in)
**Steps**: Fill the music upload form with URL-based music and submit
**Pass criteria**:
- Fill: title="Test Song", use="test_song_1", music_url="https://example.com/song.mp3", check "agreed"
- Submit form
- After submit: music item appears in the music grid with title "Test Song" and author "@testuser1"
- JSON file created in VP/music/ directory
**Fail if**: Form submission fails, no item appears, or JSON not created
 
## Test 17: Profile Icon Tabs
**Navigate**: http://localhost:8080/testuser1 (logged in)
**Steps**: Verify profile tabs use icons instead of text labels
**Pass criteria**:
- Tab section visible below profile stats
- Tabs shown as icons (not text labels like "Посты")
- Hovering over icon tab shows tooltip with tab name
- Available tabs include posts and potentially music/stickers/coding/themes/likes
- Empty tabs hidden or show "тут пока пусто" message
**Fail if**: Tabs shown as plain text instead of icons, or tab section completely missing
 
## Test 18: Theme Upload Form (CSS Code)
**Navigate**: http://localhost:8080/?action=themes&upload=1 (logged in)
**Steps**: Verify theme creation form with CSS code textarea
**Pass criteria**:
- Form title: "Создать оформление"
- CSS code textarea field with monospace font and placeholder showing CSS example
- Title and CSS code fields marked as required
- Form action points to /api.php with action=upload_theme
**Fail if**: Form missing, CSS textarea not monospace, or missing required markers
 
## Test 19: Logged-Out State - Section Visibility
**Navigate**: http://localhost:8080/?action=music (not logged in)
**Steps**: Visit section pages without being logged in
**Pass criteria**:
- Music page renders with heading "Музыка"
- NO upload button visible (because not logged in)
- Nav shows "Вход" and "Регистрация" links instead of profile
**Fail if**: Upload button visible to anonymous user, or nav still shows profile link
 
## Test 20: API Endpoint - get_items
**Navigate**: (curl test)
**Steps**: Call api.php?action=get_items&type=music
**Pass criteria**:
- Returns valid JSON array (empty [] or with items)
- Content-Type header is application/json
**Fail if**: Returns non-JSON, error, or empty response (not valid JSON)