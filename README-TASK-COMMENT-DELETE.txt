SyncHub Task Comment DELETE Fix

Target:
C:\laragon\www\markito-local\modules\synchub\controllers\Api.php

What changed:
- Removed Tasks_model::remove_comment() from the SyncHub task_comment delete receiver.
- Resolves local comment by sync_uuid from entity_origin/entity_map.
- Performs trusted direct local DB delete inside a transaction.
- Cleans task_comment rows from synchub_entity_origin and synchub_entity_map by sync_uuid.
- Delete is idempotent and returns success when already absent.
- No Perfex core files were modified.

After extraction:
1) Restart Laragon.
2) Create a new task comment in Seen and confirm it syncs to Markito.
3) Delete it from Seen.
4) Confirm Seen queue action=delete becomes success.
5) Confirm comment is absent in Markito and both mapping rows are removed.
