@echo off
setlocal
set "ROOT=C:\laragon\www"
set "HERE=%~dp0"

echo ============================================
echo SyncHub Task Comment Delete Runtime Installer
echo ============================================
echo.

if not exist "%ROOT%\markito-local\modules\synchub\controllers\Api.php" (
  echo ERROR: Markito Api.php not found at expected path.
  pause
  exit /b 1
)
if not exist "%ROOT%\seen-local\modules\synchub\controllers\Api.php" (
  echo ERROR: Seen Api.php not found at expected path.
  pause
  exit /b 1
)

copy /Y "%ROOT%\markito-local\modules\synchub\controllers\Api.php" "%ROOT%\markito-local\modules\synchub\controllers\Api.php.bak-before-task-comment-delete" >nul
copy /Y "%ROOT%\seen-local\modules\synchub\controllers\Api.php" "%ROOT%\seen-local\modules\synchub\controllers\Api.php.bak-before-task-comment-delete" >nul

copy /Y "%HERE%files\markito\Api.php" "%ROOT%\markito-local\modules\synchub\controllers\Api.php" >nul
if errorlevel 1 goto :copyfail
copy /Y "%HERE%files\seen\Api.php" "%ROOT%\seen-local\modules\synchub\controllers\Api.php" >nul
if errorlevel 1 goto :copyfail

echo Files copied to runtime paths.
echo.

echo [VERIFY] old remove_comment in Markito receiver:
findstr /n /c:"$ok = $this->tasks_model->remove_comment($localCommentId, true);" "%ROOT%\markito-local\modules\synchub\controllers\Api.php"
if errorlevel 1 (echo OK - old call NOT FOUND) else (echo ERROR - old call still exists & goto :fail)

echo.
echo [VERIFY] old remove_comment in Seen receiver:
findstr /n /c:"$ok = $this->tasks_model->remove_comment($localCommentId, true);" "%ROOT%\seen-local\modules\synchub\controllers\Api.php"
if errorlevel 1 (echo OK - old call NOT FOUND) else (echo ERROR - old call still exists & goto :fail)

echo.
where php >nul 2>&1
if errorlevel 1 (
  echo PHP CLI not found in PATH. Copy completed; syntax check skipped.
) else (
  echo [PHP LINT] Markito
  php -l "%ROOT%\markito-local\modules\synchub\controllers\Api.php"
  if errorlevel 1 goto :fail
  echo [PHP LINT] Seen
  php -l "%ROOT%\seen-local\modules\synchub\controllers\Api.php"
  if errorlevel 1 goto :fail
)

echo.
echo ============================================
echo SUCCESS - Runtime files replaced correctly.
echo Now restart Laragon: Stop All, then Start All.
echo ============================================
pause
exit /b 0

:copyfail
echo ERROR: Could not copy one of the files. Close editors or run as Administrator.
goto :fail

:fail
echo.
echo INSTALL FAILED. Backups are available beside each Api.php file.
pause
exit /b 1
