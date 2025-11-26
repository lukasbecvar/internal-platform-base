# Refactoring Audit
- **Eliminovat N+1 a full-table operace** – `BanManager::getBannedUsers` načte všechny uživatele a pro každý volá `isUserBanned()` (`src/Manager/BanManager.php:194-219`). Přidej `BannedRepository::findActiveBans()` s joinem a stránkováním. `AuthManager::getOnlineUsersList` dělá totéž (`src/Manager/AuthManager.php:620-670`).
