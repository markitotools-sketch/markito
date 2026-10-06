SyncHub Task Comment Delete FINAL Fix

Replace both files by extracting this ZIP into:
C:\laragon\www\

Affected files only:
- markito-local\modules\synchub\controllers\Api.php
- seen-local\modules\synchub\controllers\Api.php

Fix:
- Removes Tasks_model::remove_comment() from the task_comment API delete receiver.
- Resolves local comment by sync_uuid from entity_origin/entity_map.
- Deletes task comment directly in DB inside transaction.
- Cleans synchub_entity_map + synchub_entity_origin by sync_uuid.
- Delete is idempotent and returns HTTP 200 on already-clean state.
- No Perfex core files modified.

After replacement: Restart Laragon.
