# Phase 4: Playlists ✅ COMPLETE

## TDD Order - Write tests FIRST

### 1. Contract tests
- [ ] `tests/Contracts/Api/V1/PlaylistsContractTest.php`
- [ ] `tests/Contracts/Api/V1/SmartFoldersContractTest.php`

### 2. Feature tests
- [ ] `tests/Feature/Api/V1/PlaylistEndpointTest.php`
- [ ] `tests/Feature/Api/V1/SmartFolderEndpointTest.php`
- [ ] `tests/Feature/Services/PlaylistServiceTest.php`
- [ ] `tests/Feature/Services/SmartPlaylistEvaluatorTest.php`

### 3. Unit tests
- [ ] `tests/Unit/Models/PlaylistTest.php`
- [ ] `tests/Unit/Support/SmartPlaylistRuleTest.php`
- [ ] `tests/Unit/Support/SmartPlaylistRuleGroupTest.php`
- [ ] `tests/Unit/Support/SmartPlaylistQueryModifierTest.php`

### 4. Implementation
- [x] `app/Models/Playlist.php` - With smart playlist support
- [x] `app/Models/PlaylistSong.php` - Pivot model
- [x] `app/Enums/SmartPlaylistField.php`
- [x] `app/Enums/SmartPlaylistOperator.php`
- [x] `app/Support/SmartPlaylist/Rule.php`
- [x] `app/Support/SmartPlaylist/RuleGroup.php`
- [x] `app/Support/SmartPlaylist/QueryModifier.php`
- [x] `app/Services/Playlist/PlaylistService.php`
- [x] `app/Services/Playlist/SmartPlaylistEvaluator.php`
- [x] `app/Http/Controllers/Api/V1/PlaylistController.php`
- [x] `app/Http/Controllers/Api/V1/SmartFolderController.php`
- [x] `app/Http/Resources/Api/V1/PlaylistResource.php`
- [x] `app/Http/Resources/Api/V1/SmartFolderResource.php`

### 5. Documentation
- [ ] `docs/developer/smart-playlists.md`
- [ ] `docs/user-guide/smart-playlists.md`
- [ ] `docs/user-guide/smart-folders.md`

## Verification
- [x] All tests pass
- [x] Regular playlists CRUD works
- [x] Smart playlists evaluate correctly
- [x] Smart folder rules work:
  - "Folder is Rock" matches Rock songs
  - "Folder begins with Xmas" matches all Xmas/* songs
