# Refactoring Audit
- **Eliminovat N+1 a full-table operace** – `BanManager::getBannedUsers` načte všechny uživatele a pro každý volá `isUserBanned()` (`src/Manager/BanManager.php:194-219`). Přidej `BannedRepository::findActiveBans()` s joinem a stránkováním. `AuthManager::getOnlineUsersList` dělá totéž (`src/Manager/AuthManager.php:620-670`).
- **Bezpečné DB operace** – `DatabaseManager::tableTruncate` skládá SQL řetězec z názvů DB/tabulky (`src/Manager/DatabaseManager.php:70-88`) – stačí crafted POST a uživatel může smazat libovolnou tabulku. Použij `Connection::quoteIdentifier` nebo metody SchemaManageru; API by nikdy nemělo přijímat název databáze od uživatele.

